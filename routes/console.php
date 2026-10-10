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
    \App\Models\CreditPurchase::whereIn('status',['pending','cancelled'])->whereNotNull('provider_id')->where(fn($q)=>$q->where('created_at','>=',now()->subDays(2))->orWhereNull('checked_at'))->orderByRaw('COALESCE(checked_at, created_at)')->limit(20)->get()->each(function($purchase) {
        try { app(\App\Services\CreditPayments::class)->sync($purchase); }
        catch(\Throwable $e) { $purchase->update(['checked_at'=>now()]); \Illuminate\Support\Facades\Log::warning('Payment reconciliation deferred',['purchase'=>$purchase->id,'error_type'=>get_class($e)]); }
    });
})->purpose('Verify pending sandbox credit purchases without duplicate crediting');
\Illuminate\Support\Facades\Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping();

Artisan::command('minutes:expire', function () {
    \App\Models\User::whereIn('id',\Illuminate\Support\Facades\DB::table('minute_lots')->select('user_id')->where('remaining_units','>',0)->where('expires_at','<=',now()))->chunkById(100,function($users){foreach($users as $user) app(\App\Services\MinuteWallet::class)->refresh($user);});
})->purpose('Expire purchased minute lots exactly once');
\Illuminate\Support\Facades\Schedule::command('minutes:expire')->everyMinute()->withoutOverlapping();

Artisan::command('bookings:review', function () {
    app(\App\Services\ReadingBilling::class)->sweep();
    app(\App\Services\BookingService::class)->sweep();
})->purpose('Resolve finished bookings and flag missed appointments without automatic penalties');
\Illuminate\Support\Facades\Schedule::command('bookings:review')->everyMinute()->withoutOverlapping();
Artisan::command('transcripts:retain', function () {
    app(\App\Services\TranscriptPrivacy::class)->retention();
})->purpose('Send advance retention notices, then purge eligible chat content only');
\Illuminate\Support\Facades\Schedule::command('transcripts:retain')->dailyAt('03:00')->withoutOverlapping();
