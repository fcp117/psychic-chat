<?php
namespace App\Services;
use App\Models\CreditPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PaymentGateway {
    private const PAYPAL='https://api-m.sandbox.paypal.com';
    private const MAYA='https://pg-sandbox.paymaya.com';
    public function methods(): array {
        return collect(['paypal'=>'PayPal','stripe'=>'Stripe','maya'=>'Maya (PayMaya)','gcash'=>'GCash'])->map(fn($name,$id)=>[
            'id'=>$id,'name'=>$name,'available'=>$this->ready($id),'minimum'=>max(100,(int)config('payments.'.$id.'.minimum',100)),'mode'=>'sandbox',
        ])->values()->all();
    }
    public function ready(string $provider): bool {
        if(!config('payments.'.$provider.'.enabled')) return false;
        return match($provider) {
            'gcash'=>app(GcashGateway::class)->ready(),
            'stripe'=>str_starts_with((string)config('payments.stripe.secret'),'sk_test_') && (bool)config('payments.stripe.webhook_secret'),
            'paypal'=>(bool)config('payments.paypal.client_id') && (bool)config('payments.paypal.secret') && (bool)config('payments.paypal.webhook_id'),
            'maya'=>(bool)config('payments.maya.public') && (bool)config('payments.maya.secret'),
            default=>false,
        };
    }
    private function http() { return Http::acceptJson()->connectTimeout(3)->timeout(12); }
    private function paypal() {
        $token=$this->http()->asForm()->withBasicAuth(config('payments.paypal.client_id'),config('payments.paypal.secret'))->post(self::PAYPAL.'/v1/oauth2/token',['grant_type'=>'client_credentials'])->throw()->json('access_token');
        if(!$token) throw new \RuntimeException('Missing PayPal token');
        return $this->http()->withToken($token);
    }
    private function amount(int $centavos): string { return intdiv($centavos,100).'.'.str_pad((string)($centavos%100),2,'0',STR_PAD_LEFT); }
    public function create(CreditPurchase $order): array {
        if(!$this->ready($order->provider)) throw ValidationException::withMessages(['provider'=>'This payment method is not configured yet.']);
        if($order->provider==='gcash') return app(GcashGateway::class)->create($order);
        $return=route('credits.purchase',$order->id);
        if($order->provider==='stripe') {
            $data=$this->http()->asForm()->withToken(config('payments.stripe.secret'))->withHeaders(['Idempotency-Key'=>$order->id])->post('https://api.stripe.com/v1/checkout/sessions',[
                'mode'=>'payment','success_url'=>$return,'cancel_url'=>$return.'?cancelled=1','client_reference_id'=>$order->id,
                'metadata'=>['purchase_id'=>$order->id], 'payment_method_types'=>['card'],
                'line_items'=>[['quantity'=>1,'price_data'=>['currency'=>'php','unit_amount'=>$order->amount,'product_data'=>['name'=>$order->label.' — '.$order->credits.' credits']]]],
            ])->throw()->json();
            $id=$data['id']??null; $url=$data['url']??null;
        } elseif($order->provider==='paypal') {
            $data=$this->paypal()->withHeaders(['PayPal-Request-Id'=>$order->id])->post(self::PAYPAL.'/v2/checkout/orders',[
                'intent'=>'CAPTURE','purchase_units'=>[['reference_id'=>$order->id,'custom_id'=>$order->id,'description'=>$order->credits.' Psychic Chat credits','amount'=>['currency_code'=>'PHP','value'=>$this->amount($order->amount)]]],
                'payment_source'=>['paypal'=>['experience_context'=>['return_url'=>$return,'cancel_url'=>$return.'?cancelled=1','user_action'=>'PAY_NOW','shipping_preference'=>'NO_SHIPPING']]],
            ])->throw()->json();
            $id=$data['id']??null; $url=collect($data['links']??[])->first(fn($l)=>in_array($l['rel']??'',['payer-action','approve']))['href']??null;
        } else {
            $data=$this->http()->withBasicAuth(config('payments.maya.public'),'')->post(self::MAYA.'/checkout/v1/checkouts',[
                'totalAmount'=>['value'=>$this->amount($order->amount),'currency'=>'PHP'],'requestReferenceNumber'=>$order->id,
                'redirectUrl'=>['success'=>$return,'failure'=>$return.'?failed=1','cancel'=>$return.'?cancelled=1'],
            ])->throw()->json();
            $id=$data['checkoutId']??null; $url=$data['redirectUrl']??null;
        }
        $host=strtolower(parse_url((string)$url,PHP_URL_HOST)??'');
        $allowed=match($order->provider) {'stripe'=>$host==='checkout.stripe.com','paypal'=>$host==='www.sandbox.paypal.com' || $host==='sandbox.paypal.com','maya'=>str_ends_with($host,'.paymaya.com') || str_ends_with($host,'.maya.ph')};
        if(!$id || !is_string($url) || parse_url($url,PHP_URL_SCHEME)!=='https' || !$allowed) throw new \RuntimeException('Invalid hosted checkout response');
        return ['provider_id'=>$id,'checkout_url'=>$url,'status'=>'pending'];
    }
    private function same(CreditPurchase $order,$id,$reference,$amount,$currency): void {
        if($id!==$order->provider_id || $reference!==$order->id || (int)$amount!==$order->amount || strtoupper((string)$currency)!=='PHP') throw new \RuntimeException('Payment verification mismatch');
    }
    // Verify through authenticated provider APIs. Neither return URLs nor webhook bodies grant credits.
    public function paid(CreditPurchase $order): bool {
        if($order->environment!=='sandbox' || !$this->ready($order->provider) || !$order->provider_id) return false;
        if($order->provider==='gcash') return app(GcashGateway::class)->paid($order);
        $id=rawurlencode($order->provider_id);
        if($order->provider==='stripe') {
            $d=$this->http()->withToken(config('payments.stripe.secret'))->get('https://api.stripe.com/v1/checkout/sessions/'.$id)->throw()->json();
            $this->same($order,$d['id']??null,$d['metadata']['purchase_id']??null,$d['amount_total']??-1,$d['currency']??null);
            if (($d['livemode']??true)===false && ($d['status']??'')==='expired') $order->update(['status'=>'expired']);
            return ($d['livemode']??true)===false && ($d['payment_status']??'')==='paid' && ($d['status']??'')==='complete';
        }
        if($order->provider==='paypal') {
            $http=$this->paypal(); $d=$http->get(self::PAYPAL.'/v2/checkout/orders/'.$id)->throw()->json();
            $unit=$d['purchase_units'][0]??[];
            $this->same($order,$d['id']??null,$unit['custom_id']??null,CreditShop::centavos($unit['amount']['value']??'0'),$unit['amount']['currency_code']??null);
            if(($d['status']??'')==='VOIDED') $order->update(['status'=>'cancelled']);
            if(($d['status']??'')==='APPROVED') $d=$http->withHeaders(['PayPal-Request-Id'=>$order->id.'-capture'])->withBody('{}','application/json')->post(self::PAYPAL.'/v2/checkout/orders/'.$id.'/capture')->throw()->json();
            $capture=$d['purchase_units'][0]['payments']['captures'][0]??[];
            return ($d['status']??'')==='COMPLETED' && ($capture['status']??'')==='COMPLETED' && ($capture['amount']['currency_code']??'')==='PHP' && CreditShop::centavos($capture['amount']['value']??'0')===$order->amount;
        }
        $d=$this->http()->withBasicAuth(config('payments.maya.secret'),'')->get(self::MAYA.'/payments/v1/payments/'.$id)->throw()->json();
        $this->same($order,$d['id']??null,$d['requestReferenceNumber']??null,CreditShop::centavos($d['amount']??'0'),$d['currency']??null);
        $terminal=match($d['paymentStatus']??'') {'PAYMENT_FAILED'=>'failed','PAYMENT_EXPIRED'=>'expired','PAYMENT_CANCELLED'=>'cancelled',default=>null};
        if($terminal) $order->update(['status'=>$terminal]);
        return ($d['paymentStatus']??'')==='PAYMENT_SUCCESS';
    }
    public function webhookOrder(Request $r,string $provider): ?string {
        abort_unless($this->ready($provider),503);
        if($provider==='gcash') return app(GcashGateway::class)->webhookOrder($r);
        if($provider==='stripe') {
            $parts=explode(',',(string)$r->header('Stripe-Signature')); $time=null; $signatures=[];
            foreach($parts as $part) { [$key,$value]=array_pad(explode('=',$part,2),2,''); if($key==='t')$time=$value; if($key==='v1')$signatures[]=$value; }
            abort_unless($time && ctype_digit($time) && abs(time()-(int)$time)<=300,400);
            $expected=hash_hmac('sha256',$time.'.'.$r->getContent(),config('payments.stripe.webhook_secret'));
            abort_unless(collect($signatures)->contains(fn($sig)=>hash_equals($expected,$sig)),400);
            if(!in_array($r->input('type'),['checkout.session.completed','checkout.session.async_payment_succeeded','checkout.session.expired','checkout.session.async_payment_failed']) || $r->input('livemode')!==false) return null;
            return $r->input('data.object.id');
        }
        if($provider==='paypal') {
            $valid=$this->paypal()->post(self::PAYPAL.'/v1/notifications/verify-webhook-signature',[
                'auth_algo'=>$r->header('PAYPAL-AUTH-ALGO'),'cert_url'=>$r->header('PAYPAL-CERT-URL'),'transmission_id'=>$r->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig'=>$r->header('PAYPAL-TRANSMISSION-SIG'),'transmission_time'=>$r->header('PAYPAL-TRANSMISSION-TIME'),'webhook_id'=>config('payments.paypal.webhook_id'),'webhook_event'=>$r->json()->all(),
            ])->throw()->json('verification_status');
            abort_unless($valid==='SUCCESS',400);
            return match($r->input('event_type')) {'CHECKOUT.ORDER.APPROVED'=>$r->input('resource.id'),'PAYMENT.CAPTURE.COMPLETED'=>$r->input('resource.supplementary_data.related_ids.order_id'),default=>null};
        }
        abort_unless(in_array($r->ip(),config('payments.maya.webhook_ips'),true),403);
        return in_array($r->input('paymentStatus'),['PAYMENT_SUCCESS','PAYMENT_FAILED','PAYMENT_EXPIRED','PAYMENT_CANCELLED'],true) ? $r->input('id') : null;
    }
}
