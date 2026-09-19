<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class CreditShop {
    public function settings() { return DB::table('credit_shop_settings')->find(1); }
    public static function centavos($value): int {
        $value=(string)$value;
        if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D',$value)) throw ValidationException::withMessages(['price'=>'Enter a peso amount with at most two decimal places.']);
        $parts=explode('.',$value); return (int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');
    }
    public function quote(?int $packageId, ?int $credits): array {
        $settings=$this->settings();
        if ($packageId) {
            $p=DB::table('credit_packages')->where('id',$packageId)->where('active',true)->first();
            if (!$p) throw ValidationException::withMessages(['purchase'=>'This package is no longer available. Refresh the page.']);
            $quote=['package_id'=>$p->id,'label'=>$p->name,'credits'=>(int)$p->credits,'amount'=>(int)$p->amount];
        } else {
            if (!$settings->custom_enabled || !$credits || $credits<1 || $credits>100000) throw ValidationException::withMessages(['credits'=>'Choose a valid number of credits.']);
            $quote=['package_id'=>null,'label'=>'Custom credits','credits'=>$credits,'amount'=>$credits*$settings->price_per_credit];
        }
        if ($quote['amount']<max(100,$settings->minimum_amount) || $quote['amount']>10000000) throw ValidationException::withMessages(['purchase'=>'Purchase must meet the shop minimum and cannot exceed ₱100,000.']);
        return $quote;
    }
}
