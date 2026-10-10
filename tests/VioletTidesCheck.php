<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\TarotCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
$db=tempnam(sys_get_temp_dir(),'violet-check-');config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'cache.default'=>'array','session.driver'=>'array']);DB::purge('sqlite');
function violetOk($v,$m){if(!$v)throw new RuntimeException($m);echo "PASS $m\n";}
function violetReject($f,$m){try{$f();throw new RuntimeException('Unexpected success: '.$m);}catch(Illuminate\Validation\ValidationException $e){violetOk(true,$m);}}
$failed=false;
try{
 Artisan::call('migrate',['--force'=>true]);
 $cards=TarotCard::where('is_archived',false)->get();violetOk($cards->count()===14,'fourteen Violet Tides cards');
 foreach($cards as $card){violetOk(!$card->is_active&&!$card->is_ready&&$card->meaning===''&&$card->category==='', $card->name.' imported as name/artwork draft');violetOk(is_file(public_path($card->image_path)),'artwork present: '.$card->slug);}
 $service=app(App\Services\DailyTarot::class);$guest=$service->guestKey('draft-check');$token=fn()=>(string)Illuminate\Support\Str::uuid();
 violetOk(!$service->state($guest,null)['available'],'no incomplete public deck');
 violetReject(fn()=>$service->draw($guest,null,$token()),'draft deck cannot consume a draw');violetOk($service->state($guest,null)['remaining']===1,'guest attempt unchanged');
 $admin=App\Models\User::forceCreate(['name'=>'Test Admin','email'=>'violet@test.invalid','password'=>'unused','role'=>'admin','email_verified_at'=>now()]);
 $controller=app(App\Http\Controllers\AdminTarotController::class);
 $request=function($data)use($admin){$r=Illuminate\Http\Request::create('/admin/tarot-cards','POST',$data);$r->setUserResolver(fn()=>$admin);return $r;};
 $card=$cards->first();$data=['name'=>$card->name,'is_active'=>false];
 $controller->update($request($data),$card);violetOk($card->fresh()->meaning==='','name-only draft saves');
 $data['is_active']=true;violetReject(fn()=>$controller->update($request($data),$card),'incomplete activation rejected');
 foreach(TarotCard::CONTENT_FIELDS as $f)$data[$f]='   ';
 violetReject(fn()=>$controller->update($request($data),$card),'whitespace activation rejected');
 foreach(TarotCard::CONTENT_FIELDS as $f)$data[$f]='Approved test '.$f;
 $controller->update($request($data),$card);violetOk($card->fresh()->is_ready&&TarotCard::drawable()->count()===1,'complete card can activate');
 $service->draw($guest,null,$token());$saved=$service->state($guest,null)['draws'][0]['reading'];
 $card->update(['is_archived'=>true,'is_active'=>false]);violetOk($service->state($guest,null)['draws'][0]['reading']===$saved,'archiving preserves saved reading');
 $card->update(['is_active'=>true]);violetOk(TarotCard::drawable()->count()===0,'archived card never drawable even if flagged active');
 $another=$cards->last();$another->update(['is_active'=>true]);violetOk(TarotCard::drawable()->count()===0,'incomplete legacy active row excluded defensively');
 (new Database\Seeders\TarotCardSeeder)->run();violetOk(TarotCard::count()===14&&$card->fresh()->meaning==='Approved test meaning','repeat seeding preserves edits and avoids duplicate/starter cards');
 echo "ALL VIOLET TIDES CHECKS PASSED (isolated database)\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString());}finally{DB::disconnect('sqlite');unlink($db);}exit($failed?1:0);
