<?php
// Run from the repository: isolated SQLite and mocked provider APIs only.
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->instance('request',Illuminate\Http\Request::create('/'));
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=tempnam(sys_get_temp_dir(),'psychic-payments-');
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'session.driver'=>'array','cache.default'=>'array','broadcasting.default'=>'null']);
Illuminate\Support\Facades\DB::purge('sqlite');
function check($value,$label) { if(!$value)throw new RuntimeException($label); echo "PASS $label\n"; }
function rejected($fn,$label) { try {$fn();}catch(Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException|RuntimeException $e){echo "PASS $label\n";return;}throw new Exception($label); }
function asUser($user,$data=[]) {$r=Illuminate\Http\Request::create('/','POST',$data);$r->setUserResolver(fn()=>$user);return $r;}
function fakeHttp($fn) {Illuminate\Support\Facades\Http::swap(new Illuminate\Http\Client\Factory);Illuminate\Support\Facades\Http::preventStrayRequests();Illuminate\Support\Facades\Http::fake($fn);}
try {
    check(Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true])===0,'payment migrations apply on isolated database');
    $user=App\Models\User::forceCreate(['name'=>'Buyer','email'=>'buyer@test.invalid','password'=>'test','role'=>'user']);
    $admin=App\Models\User::forceCreate(['name'=>'Admin','email'=>'admin@test.invalid','password'=>'test','role'=>'admin']);
    $other=App\Models\User::forceCreate(['name'=>'Other','email'=>'other@test.invalid','password'=>'test','role'=>'user']);
    $shop=app(App\Services\CreditShop::class);$gateway=app(App\Services\PaymentGateway::class);$payments=app(App\Services\CreditPayments::class);$controller=app(App\Http\Controllers\CreditPurchaseController::class);$manage=app(App\Http\Controllers\AdminController::class);
    check($shop->quote(null,1)['amount']===100,'default custom pricing is exactly one peso per credit');
    check($shop->quote(1,null)['credits']===1,'default package offers one credit for one peso');
    check(App\Services\CreditShop::centavos('1.25')===125,'money uses exact integer centavos');
    rejected(fn()=>App\Services\CreditShop::centavos('1.001'),'fractional centavos rejected');
    $manage->package(asUser($admin,['name'=>'Bundle','credits'=>110,'price'=>'100.00','active'=>true]));
    $pid=Illuminate\Support\Facades\DB::table('credit_packages')->max('id');
    check($shop->quote($pid,null)['amount']===10000 && $shop->quote($pid,null)['credits']===110,'admin package sets independent credit quantity and price');
    Illuminate\Support\Facades\DB::table('credit_packages')->where('id',$pid)->update(['active'=>false]);
    rejected(fn()=>$shop->quote($pid,null),'hidden package cannot be purchased');
    $manage->shop(asUser($admin,['custom_enabled'=>true,'price'=>'1.25','minimum'=>'5.00']));
    rejected(fn()=>$shop->quote(null,1),'custom purchase enforces shop minimum');
    check($shop->quote(null,4)['amount']===500,'updated custom pricing calculates exact total');
    $manage->shop(asUser($admin,['custom_enabled'=>false,'price'=>'1','minimum'=>'1']));
    rejected(fn()=>$shop->quote(null,1),'disabled custom purchases rejected');
    $manage->shop(asUser($admin,['custom_enabled'=>true,'price'=>'1','minimum'=>'1']));
    $payload=['credits'=>1,'provider'=>'stripe','request_key'=>(string)Illuminate\Support\Str::uuid(),'expected_amount'=>100,'expected_credits'=>1];
    config(['payments.stripe.enabled'=>false]);
    rejected(fn()=>$controller->checkout(asUser($user,$payload)),'unconfigured checkout blocked');
    config(['payments.stripe.enabled'=>true,'payments.stripe.secret'=>'sk_test_fixture','payments.stripe.webhook_secret'=>'whsec_fixture','payments.paypal.enabled'=>true,'payments.paypal.client_id'=>'fixture','payments.paypal.secret'=>'fixture','payments.paypal.webhook_id'=>'fixture','payments.maya.enabled'=>true,'payments.maya.public'=>'fixture','payments.maya.secret'=>'fixture']);
    fakeHttp(fn($r)=>Illuminate\Support\Facades\Http::response(['id'=>'cs_test_fixture','url'=>'https://checkout.stripe.com/test'],200));
    rejected(fn()=>$controller->checkout(asUser($user,array_replace($payload,['expected_amount'=>200]))),'stale or tampered quoted price rejected');
    $controller->checkout(asUser($user,$payload));$controller->checkout(asUser($user,$payload));
    check(App\Models\CreditPurchase::count()===1,'duplicate checkout request creates one purchase');
    $order=App\Models\CreditPurchase::first();
    check($order->status==='pending' && $user->fresh()->credit_units===0,'checkout creation does not grant credits');
    rejected(fn()=>$controller->verify(asUser($other),$order),'other user cannot verify purchase');
    rejected(fn()=>$controller->show(asUser($other),$order),'other user cannot view purchase');
    $response=['id'=>$order->provider_id,'metadata'=>['purchase_id'=>$order->id],'amount_total'=>100,'currency'=>'php','livemode'=>false,'payment_status'=>'unpaid','status'=>'open'];
    fakeHttp(fn($r)=>Illuminate\Support\Facades\Http::response($response,200));
    check(!$payments->sync($order) && $user->fresh()->credit_units===0,'unpaid checkout grants no credits');
    $response['payment_status']='paid';$response['status']='complete';$response['amount_total']=99;
    fakeHttp(fn($r)=>Illuminate\Support\Facades\Http::response($response,200));
    rejected(fn()=>$payments->sync($order),'provider amount mismatch grants no credits');
    $response['amount_total']=100;$response['currency']='usd';
    fakeHttp(fn($r)=>Illuminate\Support\Facades\Http::response($response,200));
    rejected(fn()=>$payments->sync($order),'provider currency mismatch rejected');
    $response['currency']='php';$response['metadata']['purchase_id']='wrong';
    fakeHttp(fn($r)=>Illuminate\Support\Facades\Http::response($response,200));
    rejected(fn()=>$payments->sync($order),'provider order reference mismatch rejected');
    $response['metadata']['purchase_id']=$order->id;$response['livemode']=true;
    fakeHttp(fn($r)=>Illuminate\Support\Facades\Http::response($response,200));
    check(!$payments->sync($order),'live Stripe result is rejected in sandbox');
    $response['livemode']=false;
    fakeHttp(fn($r)=>Illuminate\Support\Facades\Http::response($response,200));
    // Price edits after checkout do not alter the already-agreed purchase.
    $manage->shop(asUser($admin,['custom_enabled'=>true,'price'=>'5','minimum'=>'1']));
    check($payments->sync($order),'verified sandbox payment succeeds');$payments->sync($order);
    check($user->fresh()->credit_units===3600 && Illuminate\Support\Facades\DB::table('credit_transactions')->where('purchase_id',$order->id)->count()===1,'repeated verification grants snapshotted credits exactly once');
    $body=json_encode(['type'=>'checkout.session.completed','livemode'=>false,'data'=>['object'=>['id'=>$order->provider_id]]]);
    $req=Illuminate\Http\Request::create('/','POST',[],[],[],['CONTENT_TYPE'=>'application/json'],$body);
    rejected(fn()=>$gateway->webhookOrder($req,'stripe'),'unsigned Stripe webhook rejected');
    $ts=time();$req->headers->set('Stripe-Signature','t='.$ts.',v1='.hash_hmac('sha256',$ts.'.'.$body,'whsec_fixture'));
    check($gateway->webhookOrder($req,'stripe')===$order->provider_id,'signed Stripe webhook accepted');
    $req->headers->set('Stripe-Signature','t='.($ts-1000).',v1='.hash_hmac('sha256',($ts-1000).'.'.$body,'whsec_fixture'));
    rejected(fn()=>$gateway->webhookOrder($req,'stripe'),'stale webhook rejected');
    foreach(['paypal','maya'] as $provider) {
        $o=App\Models\CreditPurchase::create(['id'=>(string)Illuminate\Support\Str::uuid(),'user_id'=>$user->id,'request_key'=>(string)Illuminate\Support\Str::uuid(),'label'=>'Test','credits'=>2,'amount'=>200,'currency'=>'PHP','provider'=>$provider,'provider_id'=>$provider.'-fixture','environment'=>'sandbox','status'=>'pending']);
        if($provider==='paypal') {
            fakeHttp(function($r)use($o){if(str_contains($r->url(),'oauth2'))return Illuminate\Support\Facades\Http::response(['access_token'=>'fake-token']);if(str_ends_with($r->url(),'/capture'))return Illuminate\Support\Facades\Http::response(['status'=>'COMPLETED','purchase_units'=>[['payments'=>['captures'=>[['status'=>'COMPLETED','amount'=>['currency_code'=>'PHP','value'=>'2.00']]]]]]]);return Illuminate\Support\Facades\Http::response(['id'=>$o->provider_id,'status'=>'APPROVED','purchase_units'=>[['custom_id'=>$o->id,'amount'=>['currency_code'=>'PHP','value'=>'2.00']]]]);});
        } else {
            fakeHttp(fn($r)=>Illuminate\Support\Facades\Http::response(['id'=>$o->provider_id,'requestReferenceNumber'=>$o->id,'amount'=>'2.00','currency'=>'PHP','paymentStatus'=>'PAYMENT_SUCCESS']));
        }
        check($payments->sync($o),$provider.' API-confirmed payment grants credits');$payments->sync($o);
        check(Illuminate\Support\Facades\DB::table('credit_transactions')->where('purchase_id',$o->id)->count()===1,$provider.' duplicate verification is idempotent');
    }
    check($user->fresh()->credit_units===5*3600,'all test purchases reconcile with the ledger');
    $req=Illuminate\Http\Request::create('/','POST',[],[],[],['REMOTE_ADDR'=>'127.0.0.1','CONTENT_TYPE'=>'application/json'],'{"paymentStatus":"PAYMENT_SUCCESS","id":"maya-fixture"}');
    rejected(fn()=>$gateway->webhookOrder($req,'maya'),'Maya webhook from untrusted IP rejected');
    foreach(['admin.shop','admin.package'] as $name)check(in_array('role:admin',app('router')->getRoutes()->getByName($name)->gatherMiddleware()),$name.' requires admin access');

    config(['payments.gcash.enabled'=>false]);
    check(!$gateway->ready('gcash') && collect($gateway->methods())->contains('id','gcash'),'GCash is listed but disabled without credentials');
    config(['payments.gcash.enabled'=>true,'payments.gcash.secret'=>'sk_live_fixture','payments.gcash.webhook_secret'=>'gcash-signing']);
    check(!$gateway->ready('gcash'),'GCash rejects live credentials');
    config(['payments.gcash.secret'=>'sk_test_fixture']);
    $g=App\Models\CreditPurchase::create(['id'=>(string)Illuminate\Support\Str::uuid(),'user_id'=>$user->id,'request_key'=>(string)Illuminate\Support\Str::uuid(),'label'=>'GCash test','credits'=>1,'amount'=>100,'currency'=>'PHP','provider'=>'gcash','environment'=>'sandbox','status'=>'pending']);
    fakeHttp(function($r)use($g){check($r->url()==='https://api.paymongo.com/v2/checkout_sessions' && $r['data']['attributes']['payment_method_types']===['gcash'] && $r['data']['attributes']['line_items'][0]['amount']===100 && $r['data']['attributes']['pass_on_fees']===false,'GCash checkout uses centavos and no added customer fee');return Illuminate\Support\Facades\Http::response(['data'=>['id'=>'cs_gcash','attributes'=>['livemode'=>false,'checkout_url'=>'https://checkout.paymongo.com/cs_gcash']]]);});
    $g->update($gateway->create($g));
    $fixture=['data'=>['id'=>'cs_gcash','attributes'=>['livemode'=>false,'reference_number'=>$g->id,'metadata'=>['purchase_id'=>$g->id],'status'=>'active','line_items'=>[['amount'=>100,'quantity'=>1,'currency'=>'PHP']],'payments'=>[]]]];
    fakeHttp(fn($r)=>Illuminate\Support\Facades\Http::response($fixture));
    check(!$payments->sync($g),'unpaid GCash checkout adds no credits');
    $fixture['data']['attributes']['payments']=[['attributes'=>['status'=>'paid','amount'=>100,'currency'=>'PHP','livemode'=>false,'source'=>['type'=>'gcash'],'refunds'=>[],'disputed'=>false]]];
    $good=$fixture;
    foreach(['amount'=>99,'currency'=>'USD','livemode'=>true,'source'=>['type'=>'card']] as $field=>$value) {
        $fixture=$good;$fixture['data']['attributes']['payments'][0]['attributes'][$field]=$value;
        fakeHttp(fn($r)=>Illuminate\Support\Facades\Http::response($fixture));
        rejected(fn()=>$payments->sync($g),'GCash rejects mismatched payment '.$field);
    }
    $fixture=$good;$fixture['data']['attributes']['reference_number']='unrelated';
    fakeHttp(fn($r)=>Illuminate\Support\Facades\Http::response($fixture));
    rejected(fn()=>$payments->sync($g),'GCash rejects another purchase reference');
    $fixture=$good;fakeHttp(fn($r)=>Illuminate\Support\Facades\Http::response($fixture));
    $before=$user->fresh()->credit_units;
    check($payments->sync($g),'verified GCash test payment adds credits');$payments->sync($g);
    check($user->fresh()->credit_units===$before+3600 && Illuminate\Support\Facades\DB::table('credit_transactions')->where('purchase_id',$g->id)->count()===1,'GCash duplicate verification credits once');
    foreach([['data'=>['attributes'=>['type'=>'checkout_session.payment.paid','livemode'=>false,'data'=>['id'=>'cs_gcash']]]],['data'=>['type'=>'checkout_session.payment.paid','livemode'=>false,'data'=>['id'=>'cs_gcash']]]] as $envelope) {
        $body=json_encode($envelope);$req=Illuminate\Http\Request::create('/','POST',[],[],[],['CONTENT_TYPE'=>'application/json'],$body);
        rejected(fn()=>$gateway->webhookOrder($req,'gcash'),'unsigned GCash callback rejected');
        $time=time();$req->headers->set('Paymongo-Signature','t='.$time.',te='.hash_hmac('sha256',$time.'.'.$body,'gcash-signing').',li=');
        check($gateway->webhookOrder($req,'gcash')==='cs_gcash','signed GCash test callback resolves checkout');
        $req->headers->set('Paymongo-Signature','t='.$time.',te=bad,li=');
        rejected(fn()=>$gateway->webhookOrder($req,'gcash'),'tampered GCash callback rejected');
    }

    echo "ALL PAYMENT CHECKS PASSED (no network calls, no live data)\n";
}finally{Illuminate\Support\Facades\DB::disconnect('sqlite');if(is_file($db))unlink($db);}
