<?php
namespace App\Http\Controllers;
use App\Models\CreditPurchase;
use App\Models\User;
use App\Services\CreditShop;
use App\Services\CreditPayments;
use App\Services\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
class CreditPurchaseController extends Controller {
    public function __construct(private CreditShop $shop,private PaymentGateway $gateway,private CreditPayments $payments) {}
    public function index(Request $r) {
        app(\App\Services\ReadingBilling::class)->sweep();
        $settings=$this->shop->settings();
        return Inertia::render('Credits',['balance'=>$r->user()->fresh()->available_credits,'transactions'=>DB::table('credit_transactions')->where('user_id',$r->user()->id)->orderByDesc('id')->paginate(20),
            'shop'=>$settings,'packages'=>DB::table('credit_packages')->where('active',true)->where('amount','>=',$settings->minimum_amount)->orderBy('amount')->get(),'paymentMethods'=>$this->gateway->methods(),
            'purchases'=>CreditPurchase::where('user_id',$r->user()->id)->latest()->limit(10)->get(['id','label','credits','amount','provider','status','created_at']),
        ]);
    }
    public function checkout(Request $r) {
        abort_unless($r->user()->role==='user',403);
        $v=$r->validate(['package_id'=>'nullable|integer','credits'=>'nullable|integer|min:1|max:100000','provider'=>'required|in:paypal,stripe,maya,gcash','request_key'=>'required|uuid','expected_amount'=>'required|integer|min:100|max:10000000','expected_credits'=>'required|integer|min:1|max:100000']);
        if(!$this->gateway->ready($v['provider'])) throw ValidationException::withMessages(['provider'=>'This payment method is not configured yet.']);
        [$order,$new]=DB::transaction(function() use($r,$v) {
            User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            $existing=CreditPurchase::where('user_id',$r->user()->id)->where('request_key',$v['request_key'])->first();
            if($existing) return [$existing,false];
            $quote=$this->shop->quote($v['package_id']??null,$v['credits']??null);
            if($quote['amount']!==$v['expected_amount'] || $quote['credits']!==$v['expected_credits']) throw ValidationException::withMessages(['purchase'=>'Pricing changed. Refresh the page and review the new total.']);
            if($quote['amount']<max(100,(int)config('payments.'.$v['provider'].'.minimum',100))) throw ValidationException::withMessages(['provider'=>'This amount is below the payment method minimum. Choose more credits.']);
            return [CreditPurchase::create($quote+['id'=>(string)Str::uuid(),'user_id'=>$r->user()->id,'request_key'=>$v['request_key'],'provider'=>$v['provider'],'currency'=>'PHP','environment'=>'sandbox']),true];
        },3);
        if($new) {
            try { $order->update($this->gateway->create($order)); }
            catch(\Throwable $e) { $order->update(['status'=>'setup_failed']); Log::warning('Checkout setup failed',['purchase'=>$order->id,'error_type'=>get_class($e)]); return redirect()->route('credits.purchase',$order->id)->with('payment_error','The provider could not open checkout. No credits have been added.'); }
        }
        return $order->status==='pending' && $order->checkout_url ? Inertia::location($order->checkout_url) : redirect()->route('credits.purchase',$order->id);
    }
    public function show(Request $r,CreditPurchase $purchase) {
        abort_unless($purchase->user_id===$r->user()->id,403);
        return Inertia::render('Credits/Purchase',['purchase'=>$purchase->only(['id','label','amount','credits','provider','status','paid_at','checkout_url','environment']),'paymentError'=>$r->session()->get('payment_error')]);
    }
    public function verify(Request $r,CreditPurchase $purchase) {
        abort_unless($purchase->user_id===$r->user()->id,403);
        try { $paid=$this->payments->sync($purchase); }
        catch(\Throwable $e) { Log::warning('Payment verification deferred',['purchase'=>$purchase->id,'error_type'=>get_class($e)]); return response()->json(['message'=>'Payment could not be verified yet. Please check again shortly.'],503); }
        return response()->json(['paid'=>$paid,'status'=>$purchase->fresh()->status]);
    }
    public function webhook(Request $r,string $provider) {
        $id=$this->gateway->webhookOrder($r,$provider);
        if($id) {
            $order=CreditPurchase::where('provider',$provider)->where('provider_id',$id)->first();
            // Acknowledge promptly; the durable purchase is reconciled by the scheduler.
            if($order && !$order->paid_at) $order->update(['checked_at'=>null]);
        }
        return response()->json(['received'=>true]);
    }
}
