<?php

namespace App\Services;

use App\Models\CreditPurchase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentGateway
{
    public function methods(): array
    {
        return [[
            'id' => 'paymongo',
            'name' => 'PayMongo',
            'available' => $this->ready('paymongo'),
            'minimum' => max(100, (int) config('payments.paymongo.minimum', 100)),
            'mode' => 'sandbox',
        ]];
    }

    public function ready(string $provider): bool
    {
        // Test credentials only. A separate reviewed release must opt into live payments.
        return !app()->environment('production') && $provider === 'paymongo' && app(PaymongoGateway::class)->ready();
    }

    public function create(CreditPurchase $order): array
    {
        if (!$this->ready($order->provider)) {
            throw ValidationException::withMessages(['provider' => 'PayMongo test checkout is not configured yet.']);
        }
        return app(PaymongoGateway::class)->create($order);
    }

    public function paid(CreditPurchase $order): bool
    {
        return $order->environment === 'sandbox' && $this->ready($order->provider) && $order->provider_id
            ? app(PaymongoGateway::class)->paid($order)
            : false;
    }

    public function webhookOrder(Request $request, string $provider): ?string
    {
        abort_unless($provider === 'paymongo' && $this->ready($provider), 503);
        return app(PaymongoGateway::class)->webhookOrder($request);
    }
}
