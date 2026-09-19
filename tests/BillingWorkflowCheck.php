<?php
// Standalone integration check: no PHPUnit dependency and no changes to the live database.
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->instance('request', Illuminate\Http\Request::create('/'));
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=tempnam(sys_get_temp_dir(),'psychic-billing-test-');
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'session.driver'=>'array','cache.default'=>'array','broadcasting.default'=>'null']);
Illuminate\Support\Facades\DB::purge('sqlite');
function check($condition,$label) { if (!$condition) throw new RuntimeException($label); echo "PASS $label\n"; }
function requestAs($user,$data=[]) { $r=Illuminate\Http\Request::create('/','POST',$data); $r->setUserResolver(fn()=>$user->fresh()); return $r; }
function rejects($fn,$label) { try { $fn(); } catch (Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $e) { echo "PASS $label\n"; return; } throw new RuntimeException($label); }
try {
    $exit=Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
    check($exit===0,'migrations apply on isolated SQLite');
    $user=App\Models\User::forceCreate(['name'=>'Test user','email'=>'user@test.invalid','password'=>'test123456','role'=>'user','credit_units'=>100*3600]);
    $counselor=App\Models\User::forceCreate(['name'=>'Test counselor','email'=>'counselor@test.invalid','password'=>'test123456','role'=>'counselor','is_approved'=>true]);
    $admin=App\Models\User::forceCreate(['name'=>'Test admin','email'=>'admin@test.invalid','password'=>'test123456','role'=>'admin']);
    $other=App\Models\User::forceCreate(['name'=>'Other','email'=>'other@test.invalid','password'=>'test123456','role'=>'user']);
    $billing=app(App\Services\ReadingBilling::class); $chat=app(App\Http\Controllers\ChatController::class); $manage=app(App\Http\Controllers\AdminController::class);
    Illuminate\Support\Carbon::setTestNow('2026-09-19 12:00:00');
    rejects(fn()=> $chat->start(requestAs($user,['accepted_rate'=>60,'consent'=>false]),$counselor),'server requires consent');
    $chat->start(requestAs($user,['accepted_rate'=>60,'consent'=>true,'hide_notice'=>true]),$counselor);
    $s=App\Models\ChatSession::latest('id')->first();
    check($s->status==='pending' && $s->started_at===null && $user->fresh()->credit_units===360000,'pending request does not charge');
    rejects(fn()=> $chat->accept(requestAs($user),$s),'only counselor accepts');
    rejects(fn()=> $chat->store(requestAs($user,['content'=>'too soon']),$s),'pending messages rejected');
    $chat->accept(requestAs($counselor),$s);
    $s->refresh(); check($s->status==='active' && $s->agreed_rate===60,'acceptance starts agreed rate');
    Illuminate\Support\Facades\DB::table('billing_settings')->where('id',1)->update(['default_rate'=>120]);
    // Simulate both participants confirming each 10-second interval.
    for($i=1;$i<=60;$i++) {
        Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse('2026-09-19 12:00:00')->addSeconds($i*10));
        $billing->settle($s->id,$user->id,false,true);
        $billing->settle($s->id,$counselor->id,false,true);
    }
    check($s->fresh()->billed_units===36000 && $user->fresh()->credit_units===324000,'10 minutes at snapshotted 60/hour costs exactly 10 credits');
    $n=Illuminate\Support\Facades\DB::table('credit_transactions')->count();
    $billing->settle($s->id);
    check(Illuminate\Support\Facades\DB::table('credit_transactions')->count()===$n,'repeated settlement is idempotent');
    rejects(fn()=> $chat->store(requestAs($other,['content'=>'not allowed']),$s),'unrelated user cannot send messages');
    $billing->settle($s->id,$user->id,true);
    check($s->fresh()->status==='completed','explicit end closes reading');
    rejects(fn()=> $chat->start(requestAs($user,['accepted_rate'=>120,'consent'=>false]),$counselor),'new rate requires fresh consent despite hidden notice');
    rejects(fn()=> $chat->start(requestAs($user,['accepted_rate'=>60,'consent'=>true]),$counselor),'stale rate rejected');
    rejects(fn()=> $chat->start(requestAs($other,['accepted_rate'=>120,'consent'=>true]),$counselor),'insufficient balance rejected');
    $chat->start(requestAs($user,['accepted_rate'=>120,'consent'=>true]),$counselor);
    $s2=App\Models\ChatSession::latest('id')->first();$chat->accept(requestAs($counselor),$s2);
    $units=$user->fresh()->credit_units;
    Illuminate\Support\Carbon::setTestNow(now()->addSeconds(31));$billing->settle($s2->id);
    check($s2->fresh()->end_reason==='disconnected' && $user->fresh()->credit_units===$units,'disconnect grace is not billed');
    $request=requestAs($admin,['amount'=>5,'kind'=>'refund','reason'=>'Test refund','session_id'=>$s->id]);
    $manage->credits($request,$user);
    check($user->fresh()->credit_units===$units+18000,'refund restores credits');
    check((int)Illuminate\Support\Facades\DB::table('credit_transactions')->where('chat_session_id',$s->id)->sum('earning_units')===18000,'refund reduces counselor earnings');
    rejects(fn()=> $manage->credits(requestAs($admin,['amount'=>6,'kind'=>'refund','reason'=>'Too much','session_id'=>$s->id]),$user),'over-refund rejected');
    rejects(fn()=> $manage->user(requestAs($admin,['role'=>'user','is_approved'=>false,'is_suspended'=>false,'rate_per_hour'=>null]),$admin),'admin cannot remove own access');
    $guard=new App\Http\Middleware\RequireRole;
    rejects(fn()=> $guard->handle(requestAs($user),fn()=>true,'admin'),'non-admin backend access denied');
    check($guard->handle(requestAs($admin),fn()=>true,'admin')===true,'admin backend access allowed');
    rejects(fn()=> $guard->handle(requestAs($user),fn()=>true,'counselor'),'non-counselor earnings denied');
    $other->is_suspended=true;$other->save();
    rejects(fn()=> (new App\Http\Middleware\EnsureActiveAccount)->handle(requestAs($other),fn()=>true),'suspended account denied');
    // A small paid balance must stop exactly at zero, never become negative.
    $user->credit_units=3600;$user->save();
    $chat->start(requestAs($user,['accepted_rate'=>120,'consent'=>true]),$counselor);
    $s3=App\Models\ChatSession::latest('id')->first();$chat->accept(requestAs($counselor),$s3);
    for($i=0;$i<4;$i++) { Illuminate\Support\Carbon::setTestNow(now()->addSeconds(10));$billing->settle($s3->id,$user->id,false,true);$billing->settle($s3->id,$counselor->id,false,true); }
    check($user->fresh()->credit_units===0 && $s3->fresh()->end_reason==='insufficient_credits','exhaustion stops at zero');
    check(App\Models\User::forceCreate(['name'=>'New','email'=>'new@test.invalid','password'=>'test123456'])->fresh()->role==='user','registration defaults to User');

    // A conversation persists across separately billed readings.
    $manage->credits(requestAs($admin,['amount'=>100,'kind'=>'add','reason'=>'Continuation test funding']),$user);
    $redirect=$chat->start(requestAs($user,['accepted_rate'=>120,'consent'=>true]),$counselor);
    $continued=App\Models\ChatSession::latest('id')->first();
    check($continued->conversationId()===$s->id && str_ends_with($redirect->getTargetUrl(),'/chat/'.$s->id),'continuation uses the original conversation URL');
    check(count($chat->requests(requestAs($counselor))->getData(true)['requests'])===1,'counselor receives a pending continuation notification');
    $chat->accept(requestAs($counselor),$continued);
    $chat->store(requestAs($user,['content'=>'Message in continued reading']),$continued);
    $payload=$chat->heartbeat(requestAs($counselor,['active'=>true]),$s)->getData(true);
    check($payload['session']['id']===$continued->id,'original conversation follows the latest reading');
    check(!array_key_exists('billed_units',$payload['session']) && !array_key_exists('billed_seconds',$payload['session']),'counselor chat API excludes earnings totals');
    check(collect($payload['messages'])->contains('content','Message in continued reading'),'conversation includes continued reading messages');
    rejects(fn()=> $chat->heartbeat(requestAs($other),$s),'unrelated account cannot read conversation history');
    rejects(fn()=> $chat->agreement(requestAs($other),$s),'unrelated account cannot read continuation agreement');
    $notices=App\Models\Message::where('kind','system')->count();
    // Idle user polling must not reset the activity clock.
    for($idle=5;$idle<=30;$idle+=5) {
        Illuminate\Support\Carbon::setTestNow(now()->addSeconds(5));
        $chat->heartbeat(requestAs($user,['active'=>$idle<30,'idle_seconds'=>$idle]),$s);
        $chat->heartbeat(requestAs($counselor,['active'=>true,'idle_seconds'=>0]),$s);
    }
    check($continued->fresh()->end_reason==='disconnected','30 seconds without interaction ends the reading despite polling');
    $billing->settle($continued->id);$billing->settle($continued->id);
    check(App\Models\Message::where('kind','system')->count()===$notices+1,'automatic end notice is persisted exactly once');
    $oldCount=$continued->conversationMessages()->count();
    $chat->start(requestAs($user,['accepted_rate'=>120,'consent'=>true]),$counselor);
    $again=App\Models\ChatSession::latest('id')->first();
    check($again->conversationMessages()->count()===$oldCount && $again->billed_units===0 && $again->status==='pending','new request preserves history and waits for paid acceptance');
    $event=new App\Events\ReadingUpdated($again);
    check($event->conversationId===$s->id && count($event->broadcastOn())===3,'continuation event targets original room and both private accounts');
    $chat->end(requestAs($user),$again);


    foreach ([null, now()->subDay()->format('Y-m-d H:i:s')] as $publication) {
        $fid=Illuminate\Support\Facades\DB::table('forecasts')->insertGetId(['title'=>'Delete test','body'=>'Isolated fixture','published_at'=>$publication,'created_at'=>now(),'updated_at'=>now()]);
        $manage->deleteForecast(requestAs($admin),$fid);
        check(!Illuminate\Support\Facades\DB::table('forecasts')->where('id',$fid)->exists(),'forecast deletion removes draft or published item');
        $audit=Illuminate\Support\Facades\DB::table('admin_audits')->where('action','forecast.deleted')->where('target','forecast:'.$fid)->first();
        check($audit && $audit->actor_id===$admin->id && json_decode($audit->before,true)['title']==='Delete test','forecast deletion preserves admin audit snapshot');
        rejects(fn()=> $manage->deleteForecast(requestAs($admin),$fid),'missing forecast deletion is rejected');
    }
    $deleteRoute=app('router')->getRoutes()->getByName('admin.forecast.delete');
    check(in_array('role:admin',$deleteRoute->gatherMiddleware()) && in_array('DELETE',$deleteRoute->methods()),'forecast deletion route enforces admin role and DELETE method');

$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
foreach (['users','pricing','transactions','sessions','forecasts','audit'] as $section) {
    Illuminate\Support\Facades\Auth::guard('web')->setUser(App\Models\User::where('role','admin')->firstOrFail());
    $r=Illuminate\Http\Request::create('/admin?section='.$section);
    $r->headers->set('X-Inertia','true');$r->headers->set('X-Inertia-Version',$app->make(App\Http\Middleware\HandleInertiaRequests::class)->version($r));
    $result=$kernel->handle($r);$data=json_decode($result->getContent(),true);
    if ($result->getStatusCode()!==200 || ($data['component'] ?? '')!=='Admin/Settings') throw new RuntimeException('Admin section failed: '.$section.' HTTP '.$result->getStatusCode());
    echo 'PASS admin '.$section.PHP_EOL;
}
foreach ([['user','/admin',403],['user','/earnings',403],['counselor','/admin',403],['counselor','/earnings',200],['user','/psychics',200],['user','/credits',200],['user','/forecast',200],['user','/chat',200],['counselor','/chat/'.$s->id,200]] as [$role,$path,$expected]) {
    Illuminate\Support\Facades\Auth::guard('web')->setUser(App\Models\User::where('role',$role)->firstOrFail());
    $r=Illuminate\Http\Request::create($path);$r->headers->set('Accept','application/json');
    $r->headers->set('X-Inertia','true');$r->headers->set('X-Inertia-Version',$app->make(App\Http\Middleware\HandleInertiaRequests::class)->version($r));
    $result=$kernel->handle($r);
    if ($result->getStatusCode()!==$expected) throw new RuntimeException($role.' '.$path.' HTTP '.$result->getStatusCode());
    if ($path==='/chat') { $payload=json_decode($result->getContent(),true); check($payload['props']['sessions']['total']===1,'chat list contains one conversation per pair'); }
    if ($role==='counselor' && $path==='/chat/'.$s->id) { $payload=json_decode($result->getContent(),true); check(!isset($payload['props']['session']['billed_units']),'counselor room page hides earnings data'); }
    echo 'PASS '.$role.' '.$path.' HTTP '.$expected.PHP_EOL;
}

    echo "ALL BILLING CHECKS PASSED\n";
} finally {
    Illuminate\Support\Carbon::setTestNow();
    Illuminate\Support\Facades\DB::disconnect('sqlite');
    if (is_file($db)) unlink($db);
}

