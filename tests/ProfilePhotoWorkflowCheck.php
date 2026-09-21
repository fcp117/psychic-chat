<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->instance('request',Illuminate\Http\Request::create('/'));
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();$app['env']='testing';
$db=tempnam(sys_get_temp_dir(),'photo-db-');
$disk=sys_get_temp_dir().'/photo-test-'.bin2hex(random_bytes(8));
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'session.driver'=>'array','cache.default'=>'array','filesystems.disks.local.root'=>$disk]);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\Storage::forgetDisk('local');
function checkPhoto($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function photoRequest($user,$file=null){$r=Illuminate\Http\Request::create('/profile/photo','POST',['user_id'=>999]);$r->setUserResolver(fn()=>$user->fresh());$r->setLaravelSession(app('session')->driver());if($file)$r->files->set('photo',$file);return $r;}
$temp=[];
try {
 Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
 $user=App\Models\User::forceCreate(['name'=>'Photo Test','email'=>'photo@example.test','password'=>'Example123!','role'=>'user']);
 $other=App\Models\User::forceCreate(['name'=>'Other','email'=>'other@example.test','password'=>'Example123!','role'=>'user']);
 $controller=app(App\Http\Controllers\ProfilePhotoController::class);
 $image=function()use(&$temp){$f=tempnam(sys_get_temp_dir(),'avatar-');$temp[]=$f;file_put_contents($f,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII='));return new Illuminate\Http\UploadedFile($f,'photo.png','image/png',null,true);};
 $controller->store(photoRequest($user,$image()));$old=$user->fresh()->profile_photo_path;
 checkPhoto(Illuminate\Support\Facades\Storage::disk('local')->exists($old),'image stored');
 checkPhoto($other->fresh()->profile_photo_path===null,'cannot target another user');
 checkPhoto(!array_key_exists('profile_photo_path',$user->fresh()->toArray()),'storage path hidden');
 checkPhoto(str_contains($user->fresh()->profile_photo_url,'/members/'.$user->id.'/photo?v='),'versioned photo URL');
 checkPhoto($controller->show($user->fresh())->headers->get('X-Content-Type-Options')==='nosniff','safe image response');
 $controller->store(photoRequest($user,$image()));
 checkPhoto(!Illuminate\Support\Facades\Storage::disk('local')->exists($old),'replacement deletes old image');
 $bad=tempnam(sys_get_temp_dir(),'avatar-bad-');$temp[]=$bad;file_put_contents($bad,'<svg xmlns="http://www.w3.org/2000/svg"></svg>');
 try{$controller->store(photoRequest($user,new Illuminate\Http\UploadedFile($bad,'photo.svg','image/svg+xml',null,true)));throw new RuntimeException('SVG accepted');}catch(Illuminate\Validation\ValidationException $e){echo "PASS SVG rejected\n";}
 $controller->destroy(photoRequest($user));
 checkPhoto($user->fresh()->profile_photo_url===null && count(Illuminate\Support\Facades\Storage::disk('local')->allFiles())===0,'removal deletes image and clears URL');
 $routes=app('router')->getRoutes();
 foreach(['profile.photo.store','profile.photo.destroy','profile.photo.show'] as $name)checkPhoto(in_array('auth',$routes->getByName($name)->gatherMiddleware()),$name.' requires authentication');
 echo "ALL PROFILE PHOTO CHECKS PASSED\n";
} finally {
 Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('profile-photos');
 if(is_dir($disk))rmdir($disk);
 Illuminate\Support\Facades\DB::disconnect('sqlite');@unlink($db);
 foreach($temp as $f)@unlink($f);
}
