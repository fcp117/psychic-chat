<?php
// Isolated SQLite, fake notifications and no real provider calls.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = tempnam(sys_get_temp_dir(), 'security-check-');
$files = sys_get_temp_dir().'/security-files-'.bin2hex(random_bytes(6));
config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>$db, 'cache.default'=>'array', 'session.driver'=>'array', 'broadcasting.default'=>'null', 'mail.default'=>'array', 'filesystems.disks.local.root'=>$files]);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\Notification::fake();
Illuminate\Support\Facades\Http::preventStrayRequests();
$app['env'] = 'testing';
function shcheck($ok, $label) { if (!$ok) throw new RuntimeException($label); echo "PASS $label\n"; }
function shrequest($user, $data = [], $method = 'POST') {
    $r = Illuminate\Http\Request::create('/', $method, $data);
    $r->setUserResolver(fn () => $user);
    $r->setLaravelSession(app('session')->driver());
    return $r;
}
try {
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force'=>true]);
    foreach (['production','staging'] as $environment) {
        $app['env'] = $environment;
        foreach ([Database\Seeders\DatabaseSeeder::class, Database\Seeders\UserSeeder::class, Database\Seeders\ChatSeeder::class, Database\Seeders\DemoAccountsSeeder::class] as $class) {
            $denied = false;
            try { app($class)->run(); } catch (RuntimeException) { $denied = true; }
            shcheck($denied && App\Models\User::count() === 0, "$class blocked before writes in $environment");
        }
    }
    $app['env'] = 'testing';
    $make = fn ($name, $role) => App\Models\User::forceCreate(['name'=>$name, 'email'=>$name.'@example.invalid', 'password'=>'unused', 'role'=>$role, 'is_approved'=>true, 'email_verified_at'=>now()]);
    $client=$make('Client','user'); $coach=$make('Coach','counselor'); $other=$make('Other','user');
    $s=App\Models\ChatSession::create(['client_id'=>$client->id,'counselor_id'=>$coach->id,'status'=>'completed']);
    $chat=app(App\Http\Controllers\ChatController::class);
    foreach (range(1,55) as $i) App\Models\Message::create(['chat_session_id'=>$s->id,'sender_id'=>$client->id,'content'=>'Message '.$i]);
    $page=$chat->history(shrequest($client,['before'=>999999],'GET'),$s)->getData(true);
    shcheck(count($page['messages'])===50 && $page['has_more'], 'history is limited to 50 messages');
    $older=$chat->history(shrequest($coach,['before'=>$page['messages'][0]['id']],'GET'),$s)->getData(true);
    shcheck(count($older['messages'])===5 && !$older['has_more'], 'older page is accessible to coach without overlap');
    try { $chat->history(shrequest($other,['before'=>999999],'GET'),$s); throw new RuntimeException('Outsider accessed history'); }
    catch (Symfony\Component\HttpKernel\Exception\HttpException $e) { shcheck($e->getStatusCode()===403,'history rejects outsiders'); }
    foreach ([$client,$coach] as $sender) {
        config(['chat.daily_upload_bytes'=>100,'chat.stored_upload_bytes'=>100]);
        $usage=App\Models\Message::create(['chat_session_id'=>$s->id,'sender_id'=>$sender->id,'content'=>'','attachment_path'=>'existing.pdf','attachment_bytes'=>100]);
        $r=shrequest($sender,['request_key'=>(string)Illuminate\Support\Str::uuid()]);
        $r->files->set('attachment',Illuminate\Http\UploadedFile::fake()->createWithContent('sample.pdf',"%PDF-1.4\n%%EOF"));
        try { $chat->store($r,$s); throw new RuntimeException('Quota bypassed'); }
        catch (Illuminate\Validation\ValidationException $e) { shcheck(isset($e->errors()['attachment']),'upload quota applies to '.$sender->role); }
        $usage->update(['attachment_bytes'=>null,'created_at'=>now()->subDays(2)]);
        config(['chat.daily_upload_bytes'=>10000000]);
        try { $chat->store($r,$s); throw new RuntimeException('Legacy quota bypassed'); }
        catch (Illuminate\Validation\ValidationException $e) { shcheck(isset($e->errors()['attachment']),'legacy uploads count toward retained quota'); }
        $chat->store(shrequest($sender,['request_key'=>(string)Illuminate\Support\Str::uuid(),'content'=>'Text still works']),$s);
    }
    shcheck(Illuminate\Support\Facades\Storage::disk('local')->allFiles()===[], 'rejected uploads store no files');
    app('session')->driver()->start();
    $recovery=app(App\Http\Controllers\Auth\PasswordResetLinkController::class);
    $statuses=[];
    foreach ([$client->email,'missing@example.invalid',$client->email] as $email) {
        $recovery->store(shrequest(null,['email'=>$email]));
        $statuses[]=session('status');
    }
    shcheck(count(array_unique($statuses))===1 && str_contains($statuses[0],'If an account'), 'recovery is identical for existing, missing and throttled addresses');
    $routes=app('router')->getRoutes();
    shcheck(in_array('throttle:30,1,chat-message:',$routes->getByName('chat.message')->gatherMiddleware()),'message route uses independent sender throttle');
    shcheck(in_array('throttle:5,1,password-recovery:',$routes->getByName('password.email')->gatherMiddleware()),'recovery route is throttled');
    $throttle=app(Illuminate\Routing\Middleware\ThrottleRequests::class);
    for($i=0;$i<30;$i++) $throttle->handle(shrequest($client),fn()=>response('ok'),30,1,'chat-message:');
    try { $throttle->handle(shrequest($client),fn()=>response('ok'),30,1,'chat-message:'); throw new RuntimeException('Throttle bypassed'); }
    catch (Illuminate\Http\Exceptions\ThrottleRequestsException) { shcheck(true,'31st message attempt is blocked'); }
    // Exercise actual HTTP routing and CSRF (disabled automatically only in testing).
    $app['env']='local';
    config(['payments.paymongo.enabled'=>true,'payments.paymongo.secret'=>'sk_test_fixture','payments.paymongo.webhook_secret'=>'test-signing','payments.paymongo.payment_method_types'=>['card']]);
    $body=json_encode(['data'=>['attributes'=>['type'=>'checkout_session.payment.paid','livemode'=>false,'data'=>['id'=>'cs_fixture']]]]);
    $time=time(); $signature='t='.$time.',te='.hash_hmac('sha256',$time.'.'.$body,'test-signing');
    $kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
    foreach ([$signature=>200,'invalid'=>400] as $header=>$status) {
        $request=Illuminate\Http\Request::create('/payment-webhooks/paymongo','POST',[],[],[],['HTTP_ACCEPT'=>'application/json','CONTENT_TYPE'=>'application/json','HTTP_PAYMONGO_SIGNATURE'=>$header],$body);
        $response=$kernel->handle($request);
        shcheck($response->getStatusCode()===$status,'webhook HTTP route returns '.$status.' with '.($status===200?'valid':'invalid').' signature');
        $kernel->terminate($request,$response);
    }
    $request=Illuminate\Http\Request::create('/forgot-password','POST',['email'=>'missing@example.invalid'],[],[],['HTTP_ACCEPT'=>'application/json']);
    $response=$kernel->handle($request);
    shcheck($response->getStatusCode()===419,'ordinary browser POST still requires CSRF');
    echo "SECURITY HARDENING CHECKS PASSED\n";
} finally {
    Illuminate\Support\Facades\DB::disconnect('sqlite');
    if(is_file($db)) unlink($db);
    // Only this test's randomly named, isolated file root is eligible for cleanup.
    if(is_dir($files)) rmdir($files);
}
