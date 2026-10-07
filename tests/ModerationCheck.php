<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=tempnam(sys_get_temp_dir(),'moderation-');
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'cache.default'=>'array','session.driver'=>'array','broadcasting.default'=>'null']);
Illuminate\Support\Facades\DB::purge('sqlite');
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function req($user,$data=[]){$r=Illuminate\Http\Request::create('/chat/1/report','POST',$data,[],[],['REMOTE_ADDR'=>'9.9.9.9']);$r->setUserResolver(fn()=>$user);return $r;}
try {
 Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
 $user=App\Models\User::forceCreate(['name'=>'User','email'=>'u@test.invalid','password'=>'test','role'=>'user','last_seen_ip'=>'8.8.8.8']);
 $coach=App\Models\User::forceCreate(['name'=>'Coach','email'=>'c@test.invalid','password'=>'test','role'=>'counselor']);
 $admin=App\Models\User::forceCreate(['name'=>'Admin','email'=>'a@test.invalid','password'=>'test','role'=>'admin']);
 $s=App\Models\ChatSession::forceCreate(['client_id'=>$user->id,'counselor_id'=>$coach->id,'status'=>'completed']);
 $controller=app(App\Http\Controllers\ReportController::class);
 $controller->store(req($coach,['reason'=>'harassment','details'=>'A test report with enough detail.']),$s);
 $id=Illuminate\Support\Facades\DB::table('user_reports')->value('id');
 check((bool)$id,'coach can report participant');
 try{$controller->store(req($admin,['reason'=>'other','details'=>'Unauthorized test report']),$s);throw new RuntimeException('Unauthorized report accepted');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===403,'nonparticipant denied');}
 $controller->review(req($admin,['action'=>'suspend','note'=>'Confirmed test abuse']),$id);
 check($user->fresh()->is_suspended,'account suspended');
 $controller->review(req($admin,['action'=>'restore','note'=>'Restored after review']),$id);
 check(!$user->fresh()->is_suspended,'account restored');
 $controller->review(req($admin,['action'=>'block_ip','note'=>'Confirmed repeat abuse','confirm_ip'=>true]),$id);
 check(Illuminate\Support\Facades\DB::table('blocked_ips')->where('ip','8.8.8.8')->exists(),'exact observed IP blocked');
 $r=Illuminate\Http\Request::create('/login','GET',[],[],[],['REMOTE_ADDR'=>'8.8.8.8']);
 try{app(App\Http\Middleware\ModerationAccess::class)->handle($r,fn()=>true);throw new RuntimeException('Blocked IP accepted');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===403,'blocked guest denied');}
 $controller->unblock(req($admin),Illuminate\Support\Facades\DB::table('blocked_ips')->value('id'));
 check(app(App\Http\Middleware\ModerationAccess::class)->handle($r,fn()=>true),'IP access restored');
 check(!array_key_exists('last_seen_ip',$user->toArray()),'IP is hidden from ordinary user payloads');
 $throttle=app(Illuminate\Routing\Middleware\ThrottleRequests::class);
 for($i=0;$i<10;$i++) $throttle->handle(req($coach),fn()=>response('ok'),120,1,'chat-heartbeat:');
 for($i=0;$i<5;$i++) $throttle->handle(req($coach),fn()=>response('ok'),5,60,'chat-report:');
 check(true,'heartbeat polling does not consume report quota');
 try{$throttle->handle(req($coach),fn()=>response('ok'),5,60,'chat-report:');throw new RuntimeException('Report quota bypassed');}catch(Illuminate\Http\Exceptions\ThrottleRequestsException $e){check($e->getStatusCode()===429,'sixth report is rate limited');}
 echo "MODERATION CHECKS PASSED\n";
} finally {Illuminate\Support\Facades\DB::disconnect('sqlite');unlink($db);}
