<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=tempnam(sys_get_temp_dir(),'coach-site-');
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'cache.default'=>'array','session.driver'=>'array']);
Illuminate\Support\Facades\DB::purge('sqlite');
function checkSite($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function siteRequest($user,$data=[]){$r=Illuminate\Http\Request::create('/','POST',$data);$r->setUserResolver(fn()=>$user);$r->headers->set('X-Inertia','true');return $r;}
function forbiddenSite($fn,$code,$label){try{$fn();throw new RuntimeException('Unexpected success: '.$label);}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){checkSite($e->getStatusCode()===$code,$label);}}
$failed=false;
try {
 Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
 $create=fn($name,$role)=>App\Models\User::forceCreate(['name'=>$name,'email'=>$name.'@test.invalid','password'=>'unused','role'=>$role,'is_approved'=>true,'is_suspended'=>false,'email_verified_at'=>now()]);
 $admin=$create('Admin','admin');$client=$create('Client','user');$rainbow=$create('Rainbow','counselor');$other=$create('Other','counselor');
 $site=app(App\Services\CoachSite::class);$settings=app(App\Http\Controllers\CoachSiteController::class);$chat=app(App\Http\Controllers\ChatController::class);
 $directory=fn()=>$chat->psychics(siteRequest($client))->toResponse(siteRequest($client))->getData(true)['props']['psychics']['data'];
 checkSite(count($directory())===2 && $site->settings()['applications_open'],'defaults preserve directory and applications');
 try {$settings->update(siteRequest($admin,['rainbow_only'=>true,'applications_open'=>true,'rainbow_user_id'=>null]),$site);throw new RuntimeException('Missing coach accepted');}catch(Illuminate\Validation\ValidationException $e){checkSite(true,'Rainbow account required');}
 try {$settings->update(siteRequest($admin,['rainbow_only'=>true,'applications_open'=>true,'rainbow_user_id'=>$client->id]),$site);throw new RuntimeException('Client selected');}catch(Illuminate\Validation\ValidationException $e){checkSite(true,'non-coach selection denied');}
 $session=App\Models\ChatSession::create(['client_id'=>$client->id,'counselor_id'=>$other->id,'status'=>'active','started_at'=>now()]);
 $settings->update(siteRequest($admin,['rainbow_only'=>true,'applications_open'=>false,'rainbow_user_id'=>$rainbow->id]),$site);
 checkSite(count($directory())===1 && $directory()[0]['id']===$rainbow->id,'directory contains selected Rainbow account only');
 checkSite($session->fresh()->status==='active','switch preserves active reading');
 checkSite($site->allows($rainbow->id) && !$site->allows($other->id),'selected coach allowed, other coach excluded');
 forbiddenSite(fn()=>$chat->start(siteRequest($client),$other),403,'direct reading request blocked');
 forbiddenSite(fn()=>$chat->accept(siteRequest($other),$session),403,'hidden coach cannot accept new readings');
 $apps=app(App\Http\Controllers\CounselorApplicationController::class);
 forbiddenSite(fn()=>$apps->profile($other),404,'hidden coach direct profile blocked');
 forbiddenSite(fn()=>$apps->show(siteRequest($client)),403,'closed application page blocked');
 forbiddenSite(fn()=>$apps->store(siteRequest($client)),403,'closed application submission blocked');
 $settings->update(siteRequest($admin,['rainbow_only'=>false,'applications_open'=>false,'rainbow_user_id'=>$rainbow->id]),$site);
 checkSite(count($directory())===2 && !$site->settings()['applications_open'],'switches operate independently and restore coaches');
 $settings->update(siteRequest($admin,['rainbow_only'=>false,'applications_open'=>true,'rainbow_user_id'=>$rainbow->id]),$site);
 $apps->show(siteRequest($client));checkSite(true,'applications reopen');
 checkSite(Illuminate\Support\Facades\DB::table('admin_audits')->where('action','coach_site.updated')->count()===3,'settings changes audited');
 foreach(['admin.coach-site','admin.coach-site.update'] as $route)checkSite(in_array('role:admin',app('router')->getRoutes()->getByName($route)->gatherMiddleware()),$route.' admin protected');
 echo "COACH SITE CHECKS PASSED\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString());}
finally{Illuminate\Support\Facades\DB::disconnect('sqlite');unlink($db);}
exit($failed?1:0);
