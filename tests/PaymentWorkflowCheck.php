<?php
// Run from the repository: temporary SQLite and mocked PayMongo APIs only.
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->instance('request', Illuminate\Http\Request::create('/'));
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=tempnam(sys_get_temp_dir(), 'intuition-payments-');
config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>$db, 'session.driver'=>'array', 'cache.default'=>'array', 'broadcasting.default'=>'null']);
Illuminate\Support\Facades\DB::purge('sqlite');
function check($value, $label) { if (!$value) throw new RuntimeException($label); echo "PASS $label\n"; }
function rejected($fn, $label) { try {$fn();} catch (Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException|RuntimeException $e) {echo "PASS $label\n"; return;} throw new Exception($label); }
function fakePaymongo($response) { Illuminate\Support\Facades\Http::swap(new Illuminate\Http\Client\Factory); Illuminate\Support\Facades\Http::preventStrayRequests(); Illuminate\Support\Facades\Http::fake(fn () => Illuminate\Support\Facades\Http::response($response)); }
try {
    check(Illuminate\Support\Facades\Artisan::call('migrate', ['--force'=>true]) === 0, 'payment migrations apply on an isolated database');
    $user=App\Models\User::forceCreate(['name'=>'Buyer','email'=>'buyer@test.invalid','password'=>'test','role'=>'user']);
    config(['payments.paymongo.enabled'=>true, 'payments.paymongo.secret'=>'sk_test_fixture', 'payments.paymongo.webhook_secret'=>'paymongo-signing', 'payments.paymongo.payment_method_types'=>['gcash','card']]);
    $gateway=app(App\Services\PaymentGateway::class); $payments=app(App\Services\CreditPayments::class);
    check($gateway->ready('paymongo'), 'PayMongo test checkout is ready with test credentials');
    check(collect($gateway->methods())->pluck('id')->all() === ['paymongo'], 'only PayMongo is shown as a payment provider');
    $order=App\Models\CreditPurchase::create(['id'=>(string) Illuminate\Support\Str::uuid(), 'user_id'=>$user->id, 'request_key'=>(string) Illuminate\Support\Str::uuid(), 'label'=>'Test credits', 'credits'=>2, 'amount'=>200, 'currency'=>'PHP', 'provider'=>'paymongo', 'environment'=>'sandbox', 'status'=>'pending']);
    fakePaymongo(['data'=>['id'=>'cs_fixture','attributes'=>['livemode'=>false,'checkout_url'=>'https://checkout.paymongo.com/cs_fixture']]]);
    $order->update($gateway->create($order));
    check($order->fresh()->provider_id === 'cs_fixture', 'PayMongo checkout is created without granting credits');
    $fixture=['data'=>['id'=>'cs_fixture','attributes'=>['livemode'=>false,'reference_number'=>$order->id,'metadata'=>['purchase_id'=>$order->id],'status'=>'active','line_items'=>[['amount'=>200,'quantity'=>1,'currency'=>'PHP']],'payments'=>[]]]];
    fakePaymongo($fixture); check(!$payments->sync($order), 'unpaid PayMongo checkout grants no credits');
    $fixture['data']['attributes']['payments']=[['attributes'=>['status'=>'paid','amount'=>200,'currency'=>'PHP','livemode'=>false,'source'=>['type'=>'gcash'],'refunds'=>[],'disputed'=>false]]];
    $good=$fixture;
    foreach ([['amount',199], ['currency','USD'], ['livemode',true], ['source',['type'=>'paymaya']]] as [$field,$value]) { $fixture=$good; $fixture['data']['attributes']['payments'][0]['attributes'][$field]=$value; fakePaymongo($fixture); rejected(fn()=>$payments->sync($order), 'mismatched PayMongo '.$field.' is rejected'); }
    $order->update(['status'=>'cancelled']);
    fakePaymongo($good); check($payments->sync($order), 'a PayMongo payment completed after local cancellation is still credited safely'); $payments->sync($order);
    check($user->fresh()->credit_units === 7200 && Illuminate\Support\Facades\DB::table('credit_transactions')->where('purchase_id',$order->id)->count() === 1, 'duplicate reconciliation grants credits only once');
    $body=json_encode(['data'=>['attributes'=>['type'=>'checkout_session.payment.paid','livemode'=>false,'data'=>['id'=>'cs_fixture']]]]);
    $request=Illuminate\Http\Request::create('/','POST',[],[],[],['CONTENT_TYPE'=>'application/json'],$body);
    rejected(fn()=>$gateway->webhookOrder($request, 'paymongo'), 'unsigned PayMongo callback is rejected');
    $time=time(); $request->headers->set('Paymongo-Signature','t='.$time.',te='.hash_hmac('sha256',$time.'.'.$body,'paymongo-signing'));
    check($gateway->webhookOrder($request, 'paymongo') === 'cs_fixture', 'signed PayMongo callback resolves checkout');
    $original=$app['env']; $app['env']='production'; check(!$gateway->ready('paymongo'), 'sandbox checkout is blocked in production'); $app['env']=$original;
    echo "ALL PAYMONGO PAYMENT CHECKS PASSED (no network calls, no live data)\n";
} finally { Illuminate\Support\Facades\DB::disconnect('sqlite'); if (is_file($db)) unlink($db); }
