<?php
// Isolated SQLite; payment traffic is mocked, never sent to PayMongo.
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->instance('request', Illuminate\Http\Request::create('/'));
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,Artisan,Http};
use Illuminate\Support\{Carbon,Str};
use App\Models\{User,ChatSession,CreditPurchase};
use App\Services\{MinuteWallet,ReadingBilling,CreditShop,CreditPayments};
$db=tempnam(sys_get_temp_dir(),'minute-check-');
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'session.driver'=>'array','cache.default'=>'array','broadcasting.default'=>'null']);DB::purge('sqlite');
function ok($value,$label){if(!$value)throw new RuntimeException($label);echo "PASS $label\n";}
function req($u,$data){$r=Illuminate\Http\Request::create('/','POST',$data);$r->setUserResolver(fn()=>$u->fresh());return $r;}
function denied($fn,$label){try{$fn();}catch(Illuminate\Validation\ValidationException $e){echo "PASS $label\n";return;}throw new RuntimeException($label);}
$failed=false;
try{
 Carbon::setTestNow('2026-10-09 12:00:00');
 $paths=array_values(array_filter(array_map(fn($p)=>'database/migrations/'.basename($p),glob(dirname(__DIR__).'/database/migrations/*.php')),fn($p)=>!str_contains($p,'160000_add_minute_wallets') && !str_contains($p,'180000_add_bookings')));
 Artisan::call('migrate',['--force'=>true,'--path'=>$paths]);
 $u=User::forceCreate(['name'=>'Existing person','email'=>'existing@example.invalid','birthdate'=>'1990-01-02','password'=>'test','role'=>'user','credit_units'=>37800]);
 DB::table('credit_transactions')->insert(['user_id'=>$u->id,'kind'=>'old','amount_units'=>37800,'balance_units'=>37800,'earning_units'=>0,'reason'=>'Historic','created_at'=>now(),'updated_at'=>now()]);
 Artisan::call('migrate',['--force'=>true]);$u->refresh();
 ok($u->credit_units===37800 && DB::table('minute_lots')->where('user_id',$u->id)->value('remaining_units')===37800,'conversion preserves fractional balance');
 ok(DB::table('minute_lots')->where('user_id',$u->id)->value('expires_at')===null,'converted balance has no expiry');
 ok(DB::table('credit_transactions')->where('kind','old')->value('unit_type')==='credits','historic ledger units preserved');
 Artisan::call('migrate',['--force'=>true]);ok(DB::table('minute_lots')->count()===1,'migration does not convert twice');
 $wallet=app(MinuteWallet::class);$billing=app(ReadingBilling::class);$shop=app(CreditShop::class);
 $welcome=DB::table('credit_packages')->where('welcome',true)->first();
 ok($shop->quote($welcome->id,null)['amount']===34835 && $shop->quote(null,3)['amount']===56301,'fixed PHP package and extra-minute quotes');
 denied(fn()=>$shop->quote(null,501),'extra minute quantity limited');
 $coach=User::forceCreate(['name'=>'Coach','email'=>'coach@example.invalid','password'=>'test','role'=>'counselor','rate_per_hour'=>900,'is_approved'=>true]);
 ok($billing->rate($coach)===60,'every coach consumes minutes equally');
 DB::transaction(function()use($u,$billing){$u->refresh();$billing->record($u,20*3600,'admin_adjustment','Test funding');});
 $s=ChatSession::create(['client_id'=>$u->id,'counselor_id'=>$coach->id,'status'=>'active','billing_version'=>2,'agreed_rate'=>60,'agreed_at'=>now()->subSeconds(725),'started_at'=>now()->subSeconds(725),'client_seen_at'=>now(),'counselor_seen_at'=>now(),'disconnect_seconds'=>30]);
 $billing->settle($s->id);ok($s->fresh()->billed_units===43500,'ongoing settlement does not round each poll');
 $billing->settle($s->id,null,true);ok($s->fresh()->billed_units===46800,'12 minutes 5 seconds rounds to 13 minutes once');
 $before=$u->fresh()->credit_units;$billing->settle($s->id,null,true);ok($u->fresh()->credit_units===$before,'ended session is never charged twice');
 ok((int)DB::table('credit_transactions')->where('chat_session_id',$s->id)->sum('minute_earning_units')===46800 && (int)DB::table('credit_transactions')->where('chat_session_id',$s->id)->sum('earning_units')===0,'new minutes and legacy earnings remain separate');
 $old=ChatSession::create(['client_id'=>$u->id,'counselor_id'=>$coach->id,'status'=>'active','billing_version'=>1,'agreed_rate'=>120,'agreed_at'=>now()->subSeconds(5),'started_at'=>now()->subSeconds(5),'client_seen_at'=>now(),'counselor_seen_at'=>now(),'disconnect_seconds'=>30]);
 $billing->settle($old->id,null,true);ok($old->fresh()->billed_units===600,'legacy reading retains seconds and original rate');
 DB::transaction(function()use($u,$wallet){$u->refresh();$wallet->lot($u,7200,'Expires first',null,now()->addDay());$u->credit_units+=7200;$u->save();});
 DB::transaction(function()use($u,$billing){$u->refresh();$billing->record($u,-3600,'admin_adjustment','Consume soonest expiry');});
 ok((int)DB::table('minute_lots')->where('label','Expires first')->value('remaining_units')===3600,'soonest expiry is consumed before non-expiring minutes');
 $before=$u->fresh()->credit_units;Carbon::setTestNow(now()->addDays(2));$wallet->refresh($u);$wallet->refresh($u);
 ok($u->fresh()->credit_units===$before-3600 && DB::table('credit_transactions')->where('kind','minutes_expired')->count()===1,'expiry removes remaining purchased minutes exactly once');
 config(['payments.paymongo.enabled'=>true,'payments.paymongo.secret'=>'sk_test_fixture','payments.paymongo.payment_method_types'=>['gcash','card']]);
 Http::preventStrayRequests();Http::fake(fn()=>Http::response(['data'=>['id'=>'cs_minute_fixture','attributes'=>['livemode'=>false,'checkout_url'=>'https://checkout.paymongo.com/cs_minute_fixture']]]));
 $controller=app(App\Http\Controllers\CreditPurchaseController::class);
 $data=['package_id'=>$welcome->id,'provider'=>'paymongo','request_key'=>(string)Str::uuid(),'expected_amount'=>34835,'expected_credits'=>5,'payment_consent'=>true];
 denied(fn()=>$controller->checkout(req($u,array_replace($data,['payment_consent'=>false]))),'payment requires separate explicit approval');
 denied(fn()=>$controller->checkout(req($u,array_replace($data,['expected_amount'=>100]))),'stale checkout price rejected');
 $controller->checkout(req($u,$data));$controller->checkout(req($u,$data));
 ok(CreditPurchase::count()===1,'checkout retries reuse the same purchase');
 denied(fn()=>$controller->checkout(req($u,array_replace($data,['request_key'=>(string)Str::uuid()]))),'Welcome cannot be purchased twice');
 $duplicate=User::forceCreate(['name'=>' Existing person ','email'=>'second@example.invalid','birthdate'=>'1990-01-02','password'=>'test','role'=>'user']);
 ok(!$wallet->welcomeAvailable($duplicate->fresh()),'matching name and birthdate cannot repeat Welcome on another account');
 $order=CreditPurchase::first();
 Http::swap(new Illuminate\Http\Client\Factory);Http::preventStrayRequests();Http::fake(fn()=>Http::response(['data'=>['id'=>'cs_minute_fixture','attributes'=>['livemode'=>false,'reference_number'=>$order->id,'metadata'=>['purchase_id'=>$order->id],'status'=>'active','line_items'=>[['amount'=>34835,'quantity'=>1,'currency'=>'PHP']],'payments'=>[['attributes'=>['status'=>'paid','amount'=>34835,'currency'=>'PHP','livemode'=>false,'source'=>['type'=>'gcash'],'refunds'=>[],'disputed'=>false]]]]]]));
 app(CreditPayments::class)->sync($order);app(CreditPayments::class)->sync($order);
 $lot=DB::table('minute_lots')->where('purchase_id',$order->id)->first();
 ok($lot && Carbon::parse($lot->expires_at)->eq(now()->addDays(365)) && (int)$lot->remaining_units===18000,'verified payment creates one 365-day minute lot');
 ok(DB::table('minute_lots')->where('purchase_id',$order->id)->count()===1,'duplicate payment does not duplicate minute lots');
 echo "ALL MINUTE WALLET CHECKS PASSED\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString());}
finally{Carbon::setTestNow();DB::disconnect('sqlite');if(is_file($db))unlink($db);}
exit($failed?1:0);
