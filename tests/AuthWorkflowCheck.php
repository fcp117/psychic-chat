<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->instance('request',Illuminate\Http\Request::create('/'));
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app['env']='testing';
$db=tempnam(sys_get_temp_dir(),'psychic-auth-');
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'session.driver'=>'array','cache.default'=>'array','broadcasting.default'=>'null','mail.default'=>'array']);
Illuminate\Support\Facades\DB::purge('sqlite');
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
function check($v,$label) { if(!$v) throw new RuntimeException($label); echo 'PASS '.$label.PHP_EOL; }
function reject($fn,$label) { try{$fn();}catch(Illuminate\Validation\ValidationException $e){echo 'PASS '.$label.PHP_EOL;return;}throw new RuntimeException($label); }
try {
 check(Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true])===0,'isolated migrations');
 Notification::fake();
 $session=app('session')->driver();$session->start();
 $r=Illuminate\Http\Request::create('/register','POST',['name'=>'New User','birthdate'=>'1990-01-01','username'=>'New_User','email'=>'NEW@example.test','password'=>'Example123!','password_confirmation'=>'Example123!','role'=>'admin','credit_units'=>999]);
 $r->setLaravelSession($session);
 app(App\Http\Controllers\Auth\RegisteredUserController::class)->store($r);
 $user=User::where('username','new_user')->firstOrFail();
 check($user->email==='new@example.test' && !$user->hasVerifiedEmail() && $user->role==='user' && $user->credit_units===0,'registration normalizes identity and ignores privileged fields');
 $code=Notification::sent($user,EmailVerificationCode::class)->last()?->code;
 check(is_string($code) && preg_match('/^[0-9]{6}$/',$code),'registration sends six-digit notification');
 $row=DB::table('email_verification_codes')->where('user_id',$user->id)->first();
 check($row->code_hash!==$code && Illuminate\Support\Facades\Hash::check($code,$row->code_hash),'only hashed verification code is stored');
 $codes=app(App\Services\EmailVerificationCodes::class);
 reject(fn()=>$codes->send($user),'resend cooldown enforced');
 reject(fn()=>app(App\Http\Controllers\Auth\RegisteredUserController::class)->store($r),'duplicate username and email rejected');
 $kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
 foreach(['/','/chat','/credits','/admin','/demo'] as $path) {
  Auth::guard('web')->setUser($user);
  $response=$kernel->handle(Illuminate\Http\Request::create($path));
  check($response->getStatusCode()===302 && str_ends_with($response->headers->get('Location'),'/verify-email'),'unverified access blocked: '.$path);
 }
 foreach([' NEW_USER ',' NEW@EXAMPLE.TEST '] as $login) {
  Auth::logout();$req=App\Http\Requests\Auth\LoginRequest::create('/login','POST',['login'=>$login,'password'=>'Example123!']);
  $req->authenticate();check(Auth::id()===$user->id,'username/email login normalization');
 }
 $bad=$code==='000000'?'111111':'000000';
 for($i=0;$i<5;$i++) reject(fn()=>$codes->verify($user,$bad),'incorrect code rejected');
 reject(fn()=>$codes->verify($user,$code),'correct code blocked after five failures');
 check(DB::table('email_verification_codes')->where('user_id',$user->id)->value('attempts')===5,'attempt counter persists');
 Illuminate\Support\Carbon::setTestNow(now()->addSeconds(61));
 $codes->send($user);$newCode=Notification::sent($user,EmailVerificationCode::class)->last()->code;
 DB::table('email_verification_codes')->where('user_id',$user->id)->update(['expires_at'=>now()->subSecond()]);
 reject(fn()=>$codes->verify($user,$newCode),'expired code rejected');
 DB::table('email_verification_codes')->where('user_id',$user->id)->update(['expires_at'=>now()->addMinutes(10),'email'=>'old@example.test']);
 reject(fn()=>$codes->verify($user,$newCode),'code bound to exact email');
 DB::table('email_verification_codes')->where('user_id',$user->id)->update(['email'=>$user->email]);
 $codes->verify($user,$newCode);
 check($user->fresh()->hasVerifiedEmail() && !DB::table('email_verification_codes')->where('user_id',$user->id)->exists(),'valid code verifies and is consumed');
 $other=User::forceCreate(['name'=>'Other','username'=>'other','email'=>'other@example.test','password'=>'Example123!']);
 reject(fn()=>$codes->verify($other,$newCode),'code cannot verify another account');
 Auth::guard('web')->setUser($user->fresh());
 $response=$kernel->handle(Illuminate\Http\Request::create('/'));
 check($response->getStatusCode()===200,'verified account reaches home');
 check(app('router')->getRoutes()->getByName('verification.verify')->methods()===['POST'],'legacy verification link removed');
 $app['env']='local'; config(['mail.default'=>'smtp','mail.mailers.smtp.username'=>null,'mail.mailers.smtp.password'=>null]);
 reject(fn()=>$codes->send($other),'missing SMTP credentials fail explicitly');
 check(!DB::table('email_verification_codes')->where('user_id',$other->id)->exists(),'failed delivery creates no usable code');
 echo "ALL AUTH CHECKS PASSED\n";
} finally { Illuminate\Support\Carbon::setTestNow(); DB::disconnect('sqlite'); if(is_file($db)) unlink($db); }
