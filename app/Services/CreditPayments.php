<?php
namespace App\Services;
use App\Models\CreditPurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class CreditPayments {
    public function __construct(private PaymentGateway $gateway) {}
    public function sync(CreditPurchase $order): bool {
        if($order->paid_at) return true;
        $paid=$this->gateway->paid($order);
        $order->update(['checked_at'=>now()]);
        if(!$paid) return false;
        return DB::transaction(function() use($order) {
            $user=User::whereKey($order->user_id)->lockForUpdate()->firstOrFail();
            $current=CreditPurchase::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if($current->paid_at) return true;
            $units=$current->credits*3600; $user->credit_units+=$units; $user->save();
            DB::table('credit_transactions')->insert(['user_id'=>$user->id,'purchase_id'=>$current->id,'kind'=>'sandbox_topup','amount_units'=>$units,'balance_units'=>$user->credit_units,'earning_units'=>0,'reason'=>'Sandbox '.$current->provider.' purchase: '.$current->label,'created_at'=>now(),'updated_at'=>now()]);
            $current->update(['status'=>'paid','paid_at'=>now()]);
            AppNotifications::send($user->id,'purchase:'.$current->id,'Credits added',$current->credits.' credits were added after payment verification.',route('credits.purchase',$current->id,false));
            return true;
        },3);
    }
}
