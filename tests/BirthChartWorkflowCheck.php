<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Services\BirthChartAccess;
use App\Http\Controllers\BirthChartController;
$db=tempnam(sys_get_temp_dir(),'birth-check-');
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'cache.default'=>'array','session.driver'=>'array','birth_chart.enabled'=>true,'birth_chart.access'=>'free']);DB::purge('sqlite');
function birthOk($v,$m){if(!$v)throw new RuntimeException($m);echo "PASS $m\n";}
function birthRequest($u=null,$data=[]){$r=Illuminate\Http\Request::create('/birth-chart','POST',$data);$r->setUserResolver(fn()=>$u);$r->headers->set('X-Inertia','true');return $r;}
function birthReject($fn,$m){try{$fn();throw new RuntimeException('Unexpected success: '.$m);}catch(Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $e){birthOk(true,$m);}}
$failed=false;
try {
 Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
 $admin=App\Models\User::forceCreate(['name'=>'Admin','email'=>'birth-admin@test.invalid','password'=>'unused','role'=>'admin','email_verified_at'=>now()]);
 $user=App\Models\User::forceCreate(['name'=>'Client','email'=>'birth-user@test.invalid','password'=>'unused','role'=>'user','birthdate'=>'1990-07-15','email_verified_at'=>now()]);
 $coach=App\Models\User::forceCreate(['name'=>'Coach','email'=>'birth-coach@test.invalid','password'=>'unused','role'=>'counselor','email_verified_at'=>now()]);
 $s=app(BirthChartAccess::class);$c=app(BirthChartController::class);
 birthOk(DB::table('birth_chart_templates')->count()===36,'36 editable placements created');
 birthOk(DB::table('birth_chart_templates')->whereNotNull('body')->count()===2,'only supplied sample wording seeded');
 foreach([$user,$coach,$admin] as $u){$s->ensure($u);birthOk(true,$u->role.' has free access');}
 $before=$user->fresh()->getRawOriginal();$r=birthRequest($user);$props=$c->index($r,$s)->toResponse($r)->getData(true)['props'];
 birthOk($props['birthdate']==='1990-07-15' && $props['reportName']==='Client','own birthdate and report name prefilled');
 birthOk(count($props['library'])===2,'approved text only delivered');
 birthOk($user->fresh()->getRawOriginal()===$before,'viewing does not debit balance or save birth details');
 birthReject(fn()=>$s->ensure(null),'guest denied');$user->is_suspended=true;birthReject(fn()=>$s->ensure($user),'suspended denied');$user->is_suspended=false;
 birthReject(fn()=>$c->admin(birthRequest($user)),'non-admin editor denied');
 $id=DB::table('birth_chart_templates')->value('id');
 birthReject(fn()=>$c->update(birthRequest($user,['body'=>'Unapproved','version'=>1,'reviewed'=>true]),$id),'non-admin update denied');
 birthReject(fn()=>$c->update(birthRequest($admin,['body'=>'Text','version'=>1,'reviewed'=>false]),$id),'approval required');
 $c->update(birthRequest($admin,['body'=>'Approved reflection.','version'=>1,'reviewed'=>true]),$id);
 birthOk(DB::table('birth_chart_templates')->where('id',$id)->value('version')===2,'approved update versioned');
 birthOk(DB::table('admin_audits')->where('action','birth_chart.template')->count()===1,'edit audited');
 birthReject(fn()=>$c->update(birthRequest($admin,['body'=>'Stale','version'=>1,'reviewed'=>true]),$id),'stale update rejected');
 config(['birth_chart.enabled'=>false]);birthReject(fn()=>$c->index(birthRequest($admin),$s),'disabled feature blocks direct report access');
 foreach(['birth-chart','admin.birth-chart','admin.birth-chart.update'] as $name){$mw=app('router')->getRoutes()->getByName($name)->gatherMiddleware();birthOk(in_array('auth',$mw)&&in_array('verified',$mw),$name.' authenticated and verified');if(str_starts_with($name,'admin.'))birthOk(in_array('role:admin',$mw),$name.' admin only');}
 echo "ALL BIRTH CHART WORKFLOW CHECKS PASSED (isolated database)\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString());}
finally{DB::disconnect('sqlite');unlink($db);}exit($failed?1:0);
