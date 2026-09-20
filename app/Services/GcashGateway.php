<?php
namespace App\Services;
use App\Models\CreditPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
class GcashGateway {
    private function http() { return Http::acceptJson()->withBasicAuth(config('payments.gcash.secret'),'')->connectTimeout(3)->timeout(12); }
    public function ready(): bool { return config('payments.gcash.enabled') && str_starts_with((string)config('payments.gcash.secret'),'sk_test_') && (bool)config('payments.gcash.webhook_secret'); }
    public function create(CreditPurchase $order): array {
        $url=route('credits.purchase',$order->id);
        $d=$this->http()->withHeaders(['Idempotency-Key'=>$order->id])->post('https://api.paymongo.com/v2/checkout_sessions',['data'=>['attributes'=>[
            'line_items'=>[['name'=>$order->label.' — '.$order->credits.' credits','amount'=>$order->amount,'currency'=>'PHP','quantity'=>1]],
            'payment_method_types'=>['gcash'],'reference_number'=>$order->id,'metadata'=>['purchase_id'=>$order->id],
            'success_url'=>$url,'cancel_url'=>$url.'?cancelled=1','send_email_receipt'=>false,'pass_on_fees'=>false,
        ]]])->throw()->json('data');
        $checkout=$d['attributes']['checkout_url']??null;
        if(empty($d['id']) || ($d['attributes']['livemode']??true)!==false || !is_string($checkout) || parse_url($checkout,PHP_URL_SCHEME)!=='https' || parse_url($checkout,PHP_URL_HOST)!=='checkout.paymongo.com') throw new \RuntimeException('Invalid GCash sandbox checkout response');
        return ['provider_id'=>$d['id'],'checkout_url'=>$checkout,'status'=>'pending'];
    }
    public function paid(CreditPurchase $order): bool {
        $d=$this->http()->get('https://api.paymongo.com/v1/checkout_sessions/'.rawurlencode($order->provider_id))->throw()->json('data');
        $a=$d['attributes']??[];
        if(($d['id']??null)!==$order->provider_id || ($a['reference_number']??null)!==$order->id || ($a['metadata']['purchase_id']??null)!==$order->id) throw new \RuntimeException('GCash payment reference mismatch');
        if(($a['livemode']??true)!==false) return false;
        $items=$a['line_items']??[];
        if(count($items)!==1 || ($items[0]['currency']??'')!=='PHP' || ($items[0]['amount']??-1)!==$order->amount || ($items[0]['quantity']??0)!==1) throw new \RuntimeException('GCash checkout amount mismatch');
        foreach($a['payments']??[] as $payment) {
            $paid=$payment['attributes']??[];
            if(($paid['status']??'')!=='paid') continue;
            if(($paid['amount']??-1)!==$order->amount || ($paid['currency']??'')!=='PHP' || ($paid['source']['type']??'')!=='gcash' || ($paid['livemode']??true)!==false || !empty($paid['refunds']) || !empty($paid['disputed'])) throw new \RuntimeException('GCash payment verification mismatch');
            return true;
        }
        if(($a['status']??'')==='expired') $order->update(['status'=>'expired']);
        return false;
    }
    public function webhookOrder(Request $r): ?string {
        $parts=[];
        foreach(explode(',',(string)$r->header('Paymongo-Signature')) as $part) { [$key,$value]=array_pad(explode('=',trim($part),2),2,''); $parts[$key]=$value; }
        $time=$parts['t']??'';
        abort_unless(ctype_digit($time) && abs(time()-(int)$time)<=300,400);
        $expected=hash_hmac('sha256',$time.'.'.$r->getContent(),config('payments.gcash.webhook_secret'));
        abort_unless(hash_equals($expected,$parts['te']??''),400);
        // Accept documented event envelopes, never their unverified payment amounts.
        $event=$r->input('data.attributes') ?? $r->input('data');
        if(!is_array($event) || ($event['type']??'')!=='checkout_session.payment.paid' || ($event['livemode']??true)!==false) return null;
        $id=$event['data']['id']??null;
        return is_string($id) ? $id : null;
    }
}
