<?php

namespace App\Services;

use App\Models\CreditPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** PayMongo hosted Checkout integration. All amounts are centavos. */
class PaymongoGateway
{
    private const API = 'https://api.paymongo.com';

    private function http()
    {
        return Http::acceptJson()->withBasicAuth((string) config('payments.paymongo.secret'), '')
            ->connectTimeout(3)->timeout(12);
    }

    /** @return list<string> */
    public function paymentMethodTypes(): array
    {
        return collect(config('payments.paymongo.payment_method_types', []))
            ->filter(fn ($type) => is_string($type) && in_array($type, ['card', 'gcash', 'paymaya'], true))
            ->values()->all();
    }

    public function ready(): bool
    {
        return (bool) config('payments.paymongo.enabled')
            && str_starts_with((string) config('payments.paymongo.secret'), 'sk_test_')
            && (bool) config('payments.paymongo.webhook_secret')
            && $this->paymentMethodTypes() !== [];
    }

    public function create(CreditPurchase $order): array
    {
        $returnUrl = route('credits.purchase', $order->id);
        $data = $this->http()->withHeaders(['Idempotency-Key' => $order->id])
            ->post(self::API.'/v2/checkout_sessions', ['data' => ['attributes' => [
                'line_items' => [['name' => $order->label.' — '.$order->credits.' credits', 'amount' => $order->amount, 'currency' => 'PHP', 'quantity' => 1]],
                'payment_method_types' => $this->paymentMethodTypes(),
                'reference_number' => $order->id,
                'metadata' => ['purchase_id' => $order->id],
                'success_url' => $returnUrl,
                'cancel_url' => $returnUrl.'?cancelled=1',
                'send_email_receipt' => false,
                'pass_on_fees' => false,
            ]]])->throw()->json('data');

        $checkout = $data['attributes']['checkout_url'] ?? null;
        if (empty($data['id']) || ($data['attributes']['livemode'] ?? true) !== false || !is_string($checkout)
            || parse_url($checkout, PHP_URL_SCHEME) !== 'https' || strtolower((string) parse_url($checkout, PHP_URL_HOST)) !== 'checkout.paymongo.com') {
            throw new \RuntimeException('Invalid PayMongo sandbox checkout response');
        }
        return ['provider_id' => $data['id'], 'checkout_url' => $checkout, 'status' => 'pending'];
    }

    // Credits are granted only after an authenticated PayMongo lookup confirms this exact checkout.
    public function paid(CreditPurchase $order): bool
    {
        $data = $this->http()->get(self::API.'/v1/checkout_sessions/'.rawurlencode($order->provider_id))->throw()->json('data');
        $attributes = $data['attributes'] ?? [];
        if (($data['id'] ?? null) !== $order->provider_id || ($attributes['reference_number'] ?? null) !== $order->id
            || ($attributes['metadata']['purchase_id'] ?? null) !== $order->id) throw new \RuntimeException('PayMongo checkout reference mismatch');
        if (($attributes['livemode'] ?? true) !== false) return false;
        $items = $attributes['line_items'] ?? [];
        if (count($items) !== 1 || ($items[0]['currency'] ?? '') !== 'PHP' || ($items[0]['amount'] ?? -1) !== $order->amount || ($items[0]['quantity'] ?? 0) !== 1) throw new \RuntimeException('PayMongo checkout amount mismatch');
        foreach ($attributes['payments'] ?? [] as $payment) {
            $paid = $payment['attributes'] ?? [];
            if (($paid['status'] ?? '') !== 'paid') continue;
            if (($paid['amount'] ?? -1) !== $order->amount || ($paid['currency'] ?? '') !== 'PHP'
                || !in_array($paid['source']['type'] ?? '', $this->paymentMethodTypes(), true)
                || ($paid['livemode'] ?? true) !== false || !empty($paid['refunds']) || !empty($paid['disputed'])) throw new \RuntimeException('PayMongo payment verification mismatch');
            return true;
        }
        if (($attributes['status'] ?? '') === 'expired') $order->update(['status' => 'expired']);
        return false;
    }

    public function webhookOrder(Request $request): ?string
    {
        $parts = [];
        foreach (explode(',', (string) $request->header('Paymongo-Signature')) as $part) { [$key, $value] = array_pad(explode('=', trim($part), 2), 2, ''); $parts[$key] = $value; }
        $time = $parts['t'] ?? '';
        abort_unless(ctype_digit($time) && abs(time() - (int) $time) <= 300, 400);
        $expected = hash_hmac('sha256', $time.'.'.$request->getContent(), (string) config('payments.paymongo.webhook_secret'));
        abort_unless(hash_equals($expected, $parts['te'] ?? ''), 400);
        // A callback wakes reconciliation; its body never grants credits by itself.
        $event = $request->input('data.attributes') ?? $request->input('data');
        if (!is_array($event) || ($event['type'] ?? '') !== 'checkout_session.payment.paid' || ($event['livemode'] ?? true) !== false) return null;
        $id = $event['data']['id'] ?? null;
        return is_string($id) ? $id : null;
    }
}
