<?php
require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';$app->instance('request',Illuminate\Http\Request::create('/'));$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();$app['env']='testing';
$db=tempnam(sys_get_temp_dir(),'account-safety-');config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'cache.default'=>'array','session.driver'=>'array','mail.default'=>'array','broadcasting.default'=>'null']);Illuminate\Support\Facades\DB::purge('sqlite');
use App\Models\User;use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Notification;
function ck($ok,$s){if(!$ok)throw new RuntimeException($s);echo "PASS $s\n";}
function rq($u,$data){$r=Illuminate\Http\Request::create('/','POST',$data);$r->setUserResolver(fn()=>$u);$r->setLaravelSession(app('session')->driver());return $r;}
function blocked($fn,$s){try{$fn();}catch(Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $e){echo "PASS $s\n";return;}throw new RuntimeException($s);}
function person($name,$extra=[]){return User::forceCreate(array_merge(['name'=>$name,'email'=>$name.'@example.test','password'=>'Example123!','role'=>'user','created_at'=>now()->subDays(8)],$extra));}
try {
 Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);Notification::fake();app('session')->driver()->start();
 $admin=person('admin',['role'=>'admin']);$old=person('old');$recent=person('recent',['created_at'=>now()]);$verified=person('verified',['email_verified_at'=>now()]);$credit=person('credit',['credit_units'=>3600]);$counselor=person('counselor',['role'=>'counselor']);
 ck($old->birthdate===null,'existing/legacy birthdate stays null');
 $base=['name'=>'Adult','username'=>'adult','email'=>'adult@example.test','password'=>'Example123!','password_confirmation'=>'Example123!'];$reg=app(App\Http\Controllers\Auth\RegisteredUserController::class);
 blocked(fn()=>$reg->store(rq(null,$base)),'birthdate required');
 blocked(fn()=>$reg->store(rq(null,$base+['birthdate'=>now('Asia/Manila')->subYears(18)->addDay()->toDateString()])),'one day under 18 rejected');
 blocked(fn()=>$reg->store(rq(null,$base+['birthdate'=>'2020-02-30'])),'invalid calendar date rejected');
 $reg->store(rq(null,$base+['birthdate'=>now('Asia/Manila')->subYears(18)->toDateString()]));$adult=User::where('username','adult')->first();ck((bool)$adult,'exactly 18 accepted');ck(!array_key_exists('birthdate',$adult->toArray()),'birthdate hidden from ordinary serialization');
 $service=app(App\Services\UnverifiedAccountCleanup::class);$controller=app(App\Http\Controllers\AccountCleanupController::class);
 blocked(fn()=>$controller->preview(rq($old,['mode'=>'abandoned']),$service),'non-admin cleanup rejected');
 $preview=$controller->preview(rq($admin,['mode'=>'abandoned']),$service)->getData(true);ck($preview['count']===1,'bulk cleanup protects recent verified privileged and funded accounts');
 $old->email_verified_at=now();$old->save();
 $controller->destroy(rq($admin,['token'=>$preview['token'],'confirmation'=>'DELETE']),$service);ck(User::whereKey($old->id)->exists(),'verification after preview prevents deletion');
 $target=person('target');$related=person('related');DB::table('credit_transactions')->insert(['user_id'=>$related->id,'kind'=>'opening','amount_units'=>0,'balance_units'=>0,'earning_units'=>0,'reason'=>'test','created_at'=>now(),'updated_at'=>now()]);
 $preview=$controller->preview(rq($admin,['mode'=>'selected','ids'=>[$target->id,$related->id,$admin->id]]),$service)->getData(true);ck($preview['count']===1,'selected cleanup excludes transaction history and admin');
 blocked(fn()=>$controller->destroy(rq($admin,['token'=>$preview['token'],'confirmation'=>'no']),$service),'typed confirmation required');
 $controller->destroy(rq($admin,['token'=>$preview['token'],'confirmation'=>'DELETE']),$service);ck(!User::whereKey($target->id)->exists() && User::whereKey($related->id)->exists(),'only confirmed eligible account deleted');
 ck(DB::table('admin_audits')->where('action','account.unverified_deleted')->count()===1,'deletion recorded in audit history');
 blocked(fn()=>$controller->destroy(rq($admin,['token'=>$preview['token'],'confirmation'=>'DELETE']),$service),'confirmation cannot be reused');
 echo "ALL ACCOUNT SAFEGUARD CHECKS PASSED\n";
} finally {DB::disconnect('sqlite');@unlink($db);}
