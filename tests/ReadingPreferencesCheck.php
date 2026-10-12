<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->instance('request',Illuminate\Http\Request::create('/'));
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Support\Facades\{DB,Artisan};
use Illuminate\Support\{Carbon,Str};
$db=tempnam(sys_get_temp_dir(),'reading-preferences-');
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'session.driver'=>'array','cache.default'=>'array','broadcasting.default'=>'null','mail.default'=>'array']);
DB::purge('sqlite');
function check($test,$name){if(!$test)throw new RuntimeException($name);echo "PASS $name\n";}
function denied($fn,$name){try{$fn();}catch(Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $e){echo "PASS $name\n";return;}throw new RuntimeException($name);}
function requestFor($user,$data){$r=Illuminate\Http\Request::create('/','POST',$data);$r->setUserResolver(fn()=>$user->fresh());return $r;}
function makeUser($role){return User::forceCreate(['name'=>$role,'email'=>Str::uuid().'@example.invalid','password'=>'fixture-password','email_verified_at'=>now(),'role'=>$role,'is_approved'=>$role==='counselor','credit_units'=>360000]);}
$failed=false;
try{
 Carbon::setTestNow('2026-10-12 00:00:00');
 Artisan::call('migrate',['--force'=>true]);
 $client=makeUser('user');$coach=makeUser('counselor');$other=makeUser('counselor');
 $music=app(App\Http\Controllers\MusicPreferenceController::class);
 $music->update(requestFor($client,['muted'=>true,'user_id'=>$coach->id]));
 check($client->fresh()->music_muted===true && $coach->fresh()->music_muted===false,'music preference belongs only to authenticated account');
 check($music->show(requestFor($client,[]))->getData()->muted===true,'music mute persists for a new request/device');
 denied(fn()=>$music->update(requestFor($client,['muted'=>'invalid'])),'invalid music preference rejected');
 $controller=app(App\Http\Controllers\BookingController::class);$service=app(BookingService::class);
 $window=['starts_at'=>'2026-10-12T02:00','ends_at'=>'2026-10-12T08:00','timezone'=>'UTC','is_available'=>true];
 denied(fn()=>$controller->availability(requestFor($client,$window)),'clients cannot set coach hours');
 $controller->availability(requestFor($coach,$window));
 $controller->availability(requestFor($coach,['starts_at'=>'2026-10-12T03:00','ends_at'=>'2026-10-12T04:00','timezone'=>'UTC','is_available'=>false]));
 denied(fn()=>$service->book($client,$coach,Carbon::parse('2026-10-12 03:00'),20,'UTC'),'manual time off blocks booking');
 denied(fn()=>$service->book($client,$coach,Carbon::parse('2026-10-12 02:45'),20,'UTC'),'buffer cannot overlap time off');
 $booking=$service->book($client,$coach,Carbon::parse('2026-10-12 04:00'),20,'UTC');
 check($booking->exists,'open time after block can be reserved');
 denied(fn()=>$controller->availability(requestFor($coach,['starts_at'=>'2026-10-12T04:00','ends_at'=>'2026-10-12T05:00','timezone'=>'UTC','is_available'=>false])),'cannot block existing appointment');
 $id=DB::table('coach_availability')->where('coach_id',$coach->id)->where('is_available',false)->value('id');
 denied(fn()=>$controller->removeAvailability(requestFor($other,[]),$id),'other coach cannot remove block');
 $controller->removeAvailability(requestFor($coach,[]),$id);
 check(!DB::table('coach_availability')->where('id',$id)->exists(),'coach can reopen blocked time');
 echo "ALL READING PREFERENCE CHECKS PASSED\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString());}
finally{Carbon::setTestNow();DB::disconnect('sqlite');if(is_file($db))unlink($db);}
exit($failed?1:0);
