<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Artisan::command('readings:settle', function () {
    app(\App\Services\ReadingBilling::class)->sweep();
    $this->info('Reading balances and disconnects settled.');
})->purpose('Settle active readings and end expired requests');

\Illuminate\Support\Facades\Schedule::command('readings:settle')->everyTenSeconds()->withoutOverlapping();


Artisan::command('payments:reconcile', function () {
    \App\Models\CreditPurchase::where('status','pending')->whereNotNull('provider_id')->where(fn($q)=>$q->where('created_at','>=',now()->subDays(2))->orWhereNull('checked_at'))->orderByRaw('COALESCE(checked_at, created_at)')->limit(20)->get()->each(function($purchase) {
        try { app(\App\Services\CreditPayments::class)->sync($purchase); }
        catch(\Throwable $e) { $purchase->update(['checked_at'=>now()]); \Illuminate\Support\Facades\Log::warning('Payment reconciliation deferred',['purchase'=>$purchase->id,'error_type'=>get_class($e)]); }
    });
})->purpose('Verify pending sandbox credit purchases without duplicate crediting');
\Illuminate\Support\Facades\Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping();
