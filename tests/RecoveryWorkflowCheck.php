<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->instance('request',Illuminate\Http\Request::create('/'));
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();$app['env']='testing';
$db=tempnam(sys_get_temp_dir(),'psychic-recovery-');
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'session.driver'=>'array','cache.default'=>'array','broadcasting.default'=>'null','mail.default'=>'array']);
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use App\Models\ChatSession;
use App\Services\AppNotifications;
DB::purge('sqlite');
function check($v,$label){if(!$v)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
function denied($fn,$label){try{$fn();}catch(Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $e){echo 'PASS '.$label.PHP_EOL;return;}throw new RuntimeException($label);}
function req($u,$data=[]){$r=Illuminate\Http\Request::create('/','POST',$data);$r->setUserResolver(fn()=>$u->fresh());$r->setLaravelSession(app('session')->driver());return $r;}
try {
 check(Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true])===0,'recovery migrations');
 Notification::fake();app('session')->driver()->start();
 $user=User::forceCreate(['name'=>'Applicant','username'=>'applicant','email'=>'applicant@example.test','password'=>'Example123!','email_verified_at'=>now(),'role'=>'user','available_credits'=>10]);
 $admin=User::forceCreate(['name'=>'Admin','email'=>'admin@example.test','password'=>'Example123!','email_verified_at'=>now(),'role'=>'admin']);
 $other=User::forceCreate(['name'=>'Other','email'=>'other@example.test','password'=>'Example123!','email_verified_at'=>now(),'role'=>'user']);
 $controller=app(App\Http\Controllers\CounselorApplicationController::class);
 $payload=['biography'=>str_repeat('Thoughtful guidance and reflection. ',4),'specialties'=>'Tarot, reflection','languages'=>'English, Filipino','years_experience'=>3,'qualifications'=>'Training and practice in reflective tarot readings.','availability'=>'Weekdays, 7–9 PM Asia/Manila','consent'=>true,'role'=>'admin','is_approved'=>true];
 denied(fn()=>$controller->store(req($admin,$payload)),'admins cannot apply');
 $controller->store(req($user,$payload));$application=DB::table('counselor_applications')->first();
 check($user->fresh()->role==='user' && $application->status==='pending','submission cannot self-promote');
 check(DB::table('app_notifications')->where('user_id',$admin->id)->count()===1,'admin receives application notification');
 denied(fn()=>$controller->store(req($user,$payload)),'duplicate pending application blocked');
 denied(fn()=>$controller->review(req($user,['decision'=>'approved','review_note'=>'I approve myself.']),$application->id),'non-admin cannot approve');
 denied(fn()=>$controller->profile($user),'unapproved counselor profile hidden');
 $controller->review(req($admin,['decision'=>'rejected','review_note'=>'Please expand your qualifications.']),$application->id);
 check($user->fresh()->role==='user','decline preserves user access');
 $controller->store(req($user,$payload));check(DB::table('counselor_applications')->count()===1,'declined applicant can resubmit');
 $session=ChatSession::create(['client_id'=>$user->id,'counselor_id'=>$other->id,'status'=>'pending']);
 denied(fn()=>$controller->review(req($admin,['decision'=>'approved','review_note'=>'Approved after review.']),$application->id),'approval blocked during open reading');
 $session->update(['status'=>'completed']);
 $controller->review(req($admin,['decision'=>'approved','review_note'=>'Approved after review.','rate_per_hour'=>90]),$application->id);
 check($user->fresh()->role==='counselor' && $user->fresh()->is_approved && $user->fresh()->rate_per_hour===90,'approval grants counselor access and approved rate');
 denied(fn()=>$controller->review(req($admin,['decision'=>'approved','review_note'=>'Repeated decision.']),$application->id),'review cannot be applied twice');
 $profile=$controller->profile($user->fresh())->toResponse(req($other));
 check(!str_contains($profile->getContent(),'Training and practice'),'private qualifications not in public profile');
 check(DB::table('admin_audits')->where('action','counselor.application_approved')->exists(),'review is audited');
 $notifications=app(App\Http\Controllers\NotificationController::class);
 $list=$notifications->index(req($user))->getData(true);check(count($list['items'])===2,'applicant receives decisions only');
 $adminNotice=DB::table('app_notifications')->where('user_id',$admin->id)->value('id');
 $notifications->read(req($other,['id'=>$adminNotice]));check(DB::table('app_notifications')->where('id',$adminNotice)->value('read_at')===null,'cannot mark someone else’s notification read');
 AppNotifications::send($other->id,'same','A title','Body','/');AppNotifications::send($other->id,'same','A title','Body','/');check(DB::table('app_notifications')->where('event_key','same')->count()===1,'notification delivery is idempotent');
 $reader=User::forceCreate(['name'=>'Reader','email'=>'reader@example.test','password'=>'Example123!','role'=>'user','email_verified_at'=>now(),'available_credits'=>20]);
 $chat=app(App\Http\Controllers\ChatController::class);
 $chat->start(req($reader,['accepted_rate'=>90,'consent'=>true]),$user->fresh());$s=ChatSession::latest('id')->first();$chat->accept(req($user),$s);$s->refresh();
 $key=(string)Illuminate\Support\Str::uuid();$data=['content'=>'Please keep this message once.','request_key'=>$key];
 $first=$chat->store(req($reader,$data),$s)->getData(true);$second=$chat->store(req($reader,$data),$s)->getData(true);
 check($first['message']['id']===$second['message']['id'] && App\Models\Message::where('request_key',$key)->count()===1,'message retry saves once');
 denied(fn()=>$chat->store(req($reader,['content'=>'Changed','request_key'=>$key]),$s),'reused key cannot change content');
 denied(fn()=>$chat->store(req($other,$data),$s),'nonparticipant cannot retrieve a retry');
 $chat->end(req($reader),$s);$retry=$chat->store(req($reader,$data),$s)->getData(true);check($retry['message']['id']===$first['message']['id'],'lost message acknowledgement recoverable after reading ends');
 $unverified=User::forceCreate(['name'=>'Typo','email'=>'typo@example.test','password'=>'Example123!','role'=>'user']);
 $codes=app(App\Services\EmailVerificationCodes::class);$codes->send($unverified);$old=Notification::sent($unverified,App\Notifications\EmailVerificationCode::class)->last()->code;
 Auth::guard('web')->setUser($unverified);
 $emailController=app(App\Http\Controllers\Auth\VerificationEmailController::class);
 denied(fn()=>$emailController->update(req($unverified,['email'=>'correct@example.test','password'=>'wrong'])),'email correction requires password');
 $emailController->update(req($unverified,['email'=>'correct@example.test','password'=>'Example123!']));
 check($unverified->fresh()->email==='correct@example.test' && !$unverified->fresh()->hasVerifiedEmail(),'email correction requires new verification');
 $row=DB::table('email_verification_codes')->where('user_id',$unverified->id)->first();check($row->email==='correct@example.test','replacement code is bound to corrected address');
 denied(fn()=>$emailController->update(req($unverified,['email'=>$reader->email,'password'=>'Example123!'])),'email correction rejects duplicate address');
 $kernel=app(Illuminate\Contracts\Http\Kernel::class);
 foreach([[$other,'/become-a-counselor',200],[$other,'/admin?section=applications',403],[$admin,'/admin?section=applications',200],[$admin,'/admin?section=health',200],[$other,'/counselors/'.$user->id,200]] as [$u,$path,$expected]) {
  Auth::guard('web')->setUser($u->fresh());$r=Illuminate\Http\Request::create($path);$r->headers->set('X-Inertia','true');$r->headers->set('X-Inertia-Version',app(App\Http\Middleware\HandleInertiaRequests::class)->version($r));
  $res=$kernel->handle($r);check($res->getStatusCode()===$expected,'route access '.$path.' HTTP '.$expected);
 }
 $handler=app(Illuminate\Contracts\Debug\ExceptionHandler::class);
 foreach([403,404,419,429,500,503] as $status) {
  $r=Illuminate\Http\Request::create('/missing','GET');$r->setLaravelSession(app('session')->driver());$r->headers->set('X-Inertia','true');
  $res=$handler->render($r,new Symfony\Component\HttpKernel\Exception\HttpException($status,'private details'));
  $json=json_decode($res->getContent(),true);check($res->getStatusCode()===$status && ($json['component']??'')==='Error' && !str_contains($res->getContent(),'private details'),'branded safe error '.$status);
 }
 $r=Illuminate\Http\Request::create('/edit','POST');$r->setLaravelSession(app('session')->driver());$r->headers->set('X-Inertia','true');
 $res=$handler->render($r,new Symfony\Component\HttpKernel\Exception\HttpException(419));check($res->isRedirect() && app('session')->driver()->get('errors')?->has('request'),'expired mutation redirects with validation error to preserve form');
 echo "ALL RECOVERY CHECKS PASSED\n";
} finally {DB::disconnect('sqlite');if(is_file($db))unlink($db);}
