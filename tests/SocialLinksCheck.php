<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=tempnam(sys_get_temp_dir(),'social-links-');
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'cache.default'=>'array','session.driver'=>'array','broadcasting.default'=>'null']);
Illuminate\Support\Facades\DB::purge('sqlite');
function socialCheck($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
try {
 Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
 $admin=App\Models\User::forceCreate(['name'=>'Admin','email'=>'admin@example.invalid','password'=>'unused','role'=>'admin']);
 $user=App\Models\User::forceCreate(['name'=>'User','email'=>'user@example.invalid','password'=>'unused','role'=>'user']);
 $controller=app(App\Http\Controllers\SocialLinksController::class);
 $request=function($actor,$data){$r=Illuminate\Http\Request::create('/admin/social-links','PUT',$data);$r->setUserResolver(fn()=>$actor);return $r;};
 $valid=['facebook'=>'https://www.facebook.com/Intuitionisland/','instagram'=>null];
 $controller->update($request($admin,$valid));
 socialCheck(Illuminate\Support\Facades\DB::table('social_links')->where('platform','instagram')->value('url')===null,'admin can hide a platform');
 socialCheck(Illuminate\Support\Facades\DB::table('admin_audits')->where('action','social_links.updated')->count()===1,'changes are audited');
 foreach(['javascript:alert(1)','http://facebook.com/page','https://facebook.com.evil.test/','https://evil.test/','https://user:pass@facebook.com/page'] as $bad){
  try{$controller->update($request($admin,['facebook'=>$bad,'instagram'=>null]));throw new RuntimeException('Unsafe link accepted');}
  catch(Illuminate\Validation\ValidationException){socialCheck(true,'unsafe or non-platform link rejected');}
 }
 try{$controller->update($request($user,$valid));throw new RuntimeException('Non-admin accepted');}
 catch(Symfony\Component\HttpKernel\Exception\HttpException $e){socialCheck($e->getStatusCode()===403,'non-admin cannot update links');}
 foreach(['admin.social-links','admin.social-links.update'] as $name){socialCheck(in_array('role:admin',app('router')->getRoutes()->getByName($name)->gatherMiddleware()),$name.' is admin-only');}
 $shared=app(App\Http\Middleware\HandleInertiaRequests::class)->share($request(null,[]));
 socialCheck(($shared['socialLinks'])()['facebook']===$valid['facebook'],'guests receive published links');
 echo "SOCIAL LINKS CHECKS PASSED\n";
}finally{Illuminate\Support\Facades\DB::disconnect('sqlite');if(is_file($db))unlink($db);}
