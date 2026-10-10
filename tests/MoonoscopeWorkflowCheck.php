<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Services\Moonoscope;
use App\Http\Controllers\MoonoscopeController;
$db=tempnam(sys_get_temp_dir(),'moon-check-');
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'cache.default'=>'array','session.driver'=>'array','features.forecast'=>true]);DB::purge('sqlite');
function moonOk($v,$m){if(!$v)throw new RuntimeException($m);echo "PASS $m\n";}
function moonRequest($u=null,$data=[]){$r=Illuminate\Http\Request::create('/forecast','POST',$data);$r->setUserResolver(fn()=>$u);$r->headers->set('X-Inertia','true');return $r;}
function moonReject($fn,$m){try{$fn();throw new RuntimeException('Unexpected success: '.$m);}catch(Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $e){moonOk(true,$m);}}
$failed=false;
try {
 Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
 $admin=App\Models\User::forceCreate(['name'=>'Moon Admin','email'=>'moon-admin@test.invalid','password'=>'unused','role'=>'admin','email_verified_at'=>now()]);
 $user=App\Models\User::forceCreate(['name'=>'Moon Client','email'=>'moon-user@test.invalid','password'=>'unused','role'=>'user','birthdate'=>'1990-07-15','email_verified_at'=>now()]);
 $s=app(Moonoscope::class);$c=app(MoonoscopeController::class);$date=now('Asia/Manila')->toDateString();
 $c->generate(moonRequest($admin,['date'=>$date,'consent'=>true]),$s);$d=DB::table('moonoscope_days')->first();
 moonOk($d->mode==='manual' && count(json_decode($d->lines,true))===12 && !$d->published_at,'manual default creates unpublished 12-sign draft');
 $payload=fn($u=null)=>$c->index(moonRequest($u))->toResponse(moonRequest($u))->getData(true)['props'];
 moonOk($payload()['daily']===null,'guests cannot see draft text');moonOk($payload($user)['birthdate']==='1990-07-15' && $payload()['birthdate']===null,'only own birthday prefilled');
 moonReject(fn()=>$c->generate(moonRequest($admin,['date'=>$date,'consent'=>true]),$s),'regeneration cannot overwrite existing date');
 moonReject(fn()=>$c->settings(moonRequest($user,['mode'=>'ai']),$s),'non-admin cannot change mode');
 $c->settings(moonRequest($admin,['mode'=>'templates']),$s);moonOk(DB::table('moonoscope_days')->value('lines')===$d->lines,'mode switching preserves draft');
 $calc=$s->calculate('2026-10-07');$template=$s->draft('templates',$calc);moonOk(count(array_filter($template,fn($l)=>$l['text']!==''))===3,'only three supplied template examples seeded');
 moonReject(fn()=>$s->validateLines($template,true),'missing template messages block publication');
 $lines=array_map(fn($sign)=>['sign'=>$sign,'text'=>'You can pause for a gentle moment of reflection '.$sign.'.'],Moonoscope::SIGNS);
 $save=['version'=>1,'lines'=>$lines,'action'=>'publish','reviewed'=>false];
 moonReject(fn()=>$c->save(moonRequest($admin,$save),$d->id,$s),'editorial confirmation required');
 $save['reviewed']=true;$c->save(moonRequest($admin,$save),$d->id,$s);moonOk($payload()['daily']['lines']===$lines,'approved daily set visible to guests');
 $save['action']='save';moonReject(fn()=>$c->save(moonRequest($admin,$save),$d->id,$s),'stale editor cannot overwrite newer revision');
 $save['version']=2;$save['lines'][0]['text']='Edited private draft.';$c->save(moonRequest($admin,$save),$d->id,$s);moonOk($payload()['daily']['lines']===$lines,'draft edits leave public snapshot unchanged');
 $bad=$lines;$bad[0]['text']=str_repeat('word ',36);moonReject(fn()=>$s->validateLines($bad,true),'35 word limit enforced');
 $bad=$lines;$bad[0]['sign']='Pisces';moonReject(fn()=>$s->validateLines($bad,true),'wrong sign order rejected');
 $bad=$lines;$bad[1]['text']=$bad[0]['text'];moonReject(fn()=>$s->validateLines($bad,true),'duplicate wording rejected');
 config(['moonoscope.ai_key'=>null,'moonoscope.ai_model'=>null]);moonReject(fn()=>$s->draft('ai',$calc),'unconfigured AI fails without overwriting');
 config(['moonoscope.ai_key'=>'fake-test-key','moonoscope.ai_model'=>'fake-test-model']);Http::preventStrayRequests();Http::fake(['api.anthropic.com/*'=>Http::response(['content'=>[['type'=>'text','text'=>json_encode($lines)]]])]);
 moonOk($s->draft('ai',$calc)===$lines,'mocked AI response parsed and validated');
 moonOk(Http::recorded(fn($r)=>!str_contains($r->body(),'1990-07-15') && str_contains($r->body(),'calculated'))->count()===1,'AI receives sky facts, not birth details');
 Http::swap(new Illuminate\Http\Client\Factory());Http::preventStrayRequests();Http::fake(['api.anthropic.com/*'=>Http::response(['error'=>'unavailable'],503)]);moonReject(fn()=>$s->draft('ai',$calc),'provider failure safely reported');
 $save['version']=3;$save['action']='unpublish';$c->save(moonRequest($admin,$save),$d->id,$s);moonOk($payload()['daily']===null,'unpublishing hides public text and retains draft');
 config(['features.forecast'=>false]);moonReject(fn()=>$c->index(moonRequest()),'visibility switch blocks public direct URL');moonReject(fn()=>$c->adminIndex(moonRequest($admin),$s),'visibility switch blocks admin direct URL');
 foreach(['admin.moonoscope','admin.moonoscope.settings','admin.moonoscope.templates','admin.moonoscope.generate','admin.moonoscope.save'] as $route)moonOk(in_array('role:admin',app('router')->getRoutes()->getByName($route)->gatherMiddleware()),$route.' protected');
 echo "ALL MOONOSCOPE WORKFLOW CHECKS PASSED (isolated database; no live AI calls)\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString());}
finally{DB::disconnect('sqlite');unlink($db);}exit($failed?1:0);
