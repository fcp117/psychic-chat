<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=tempnam(sys_get_temp_dir(),'reviews-');
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'cache.default'=>'array','session.driver'=>'array','reviews.window_hours'=>48]);
Illuminate\Support\Facades\DB::purge('sqlite');
function verifyReview($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function reviewRequest($user,$data){$r=Illuminate\Http\Request::create('/reading-feedback','POST',$data);$r->setUserResolver(fn()=>$user);return $r;}
$failed=false;
try {
 Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
 $client=App\Models\User::forceCreate(['name'=>'Client','email'=>'review@test.invalid','password'=>'unused']);
 $coach=App\Models\User::forceCreate(['name'=>'Coach','email'=>'coach@test.invalid','password'=>'unused','role'=>'counselor','is_approved'=>true,'email_verified_at'=>now()]);
 $admin=App\Models\User::forceCreate(['name'=>'Admin','email'=>'admin@test.invalid','password'=>'unused','role'=>'admin']);
 $make=fn($status,$end)=>App\Models\ChatSession::create(['client_id'=>$client->id,'counselor_id'=>$coach->id,'status'=>$status,'started_at'=>$end->copy()->subMinutes(10),'ended_at'=>$end]);
 $controller=app(App\Http\Controllers\CoachReviewController::class);$service=app(App\Services\CoachFeedback::class);
 $data=['rating'=>5,'comment'=>'A thoughtful reading.','publish_consent'=>false];
 $session=$make('completed',now()->subHour());
 $controller->store(reviewRequest($client,$data),$session,$service);
 verifyReview($service->summary($coach->id)===['average'=>5.0,'count'=>1],'rating contributes immediately');
 $reject=function($callback,$label){try{$callback();throw new RuntimeException('Unexpected success: '.$label);}catch(Illuminate\Validation\ValidationException $e){verifyReview(true,$label);}};
 $reject(fn()=>$controller->store(reviewRequest($client,$data),$session,$service),'duplicate review denied');
 try{$controller->store(reviewRequest($coach,$data),$session,$service);throw new RuntimeException('Coach reviewed self');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){verifyReview($e->getStatusCode()===403,'non-client denied');}
 $reject(fn()=>$controller->store(reviewRequest($client,$data),$make('completed',now()->subHours(49)),$service),'expired feedback denied');
 $reject(fn()=>$controller->store(reviewRequest($client,$data),$make('rejected',now()->subHour()),$service),'cancelled request denied');
 $reject(fn()=>$controller->store(reviewRequest($client,array_replace($data,['rating'=>6])),$make('completed',now()->subHour()),$service),'invalid stars denied');
 $review=App\Models\CoachReview::first();
 $reject(fn()=>$controller->moderate(reviewRequest($admin,['action'=>'highlight','reason'=>'Approved for profile']),$review),'private comment cannot be published');
 $s2=$make('completed',now()->subMinutes(20));
 $controller->store(reviewRequest($client,array_replace($data,['rating'=>3,'publish_consent'=>true])),$s2,$service);
 verifyReview($service->summary($coach->id)['average']===4.0,'average uses all submitted ratings');
 $review2=App\Models\CoachReview::latest('id')->first();
 $controller->moderate(reviewRequest($admin,['action'=>'highlight','reason'=>'Approved with permission']),$review2);
 verifyReview($review2->fresh()->highlighted,'consented review can be highlighted');
 $controller->moderate(reviewRequest($admin,['action'=>'delete','reason'=>'Removed for policy violation']),$review2);
 verifyReview($service->summary($coach->id)===['average'=>5.0,'count'=>1],'deleting review recalculates rating');
 $reject(fn()=>$controller->store(reviewRequest($client,$data),$s2,$service),'removed review cannot be resubmitted');
 verifyReview(App\Models\CoachReview::withTrashed()->count()===2,'deleted review recoverable');
 foreach(['admin.reviews','admin.reviews.coach','admin.reviews.moderate'] as $name)verifyReview(in_array('role:admin',app('router')->getRoutes()->getByName($name)->gatherMiddleware()),$name.' restricted');
 $r=reviewRequest($admin,[]);$r->headers->set('X-Inertia','true');
 $list=$controller->coaches($r)->toResponse($r)->getData(true)['props']['coaches'];
 verifyReview($list['total']===1 && (int)$list['data'][0]['review_count']===1,'admin first sees coach aggregates');
 $r=reviewRequest($client,[]);$r->headers->set('X-Inertia','true');
 $directory=app(App\Http\Controllers\ChatController::class)->psychics($r)->toResponse($r)->getData(true)['props']['psychics'];
 verifyReview((float)$directory['data'][0]['rating']['average']===5.0 && $directory['data'][0]['rating']['count']===1,'directory exposes submitted ratings and excludes deleted reviews');
 echo "COACH REVIEW CHECKS PASSED\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString());}
finally{Illuminate\Support\Facades\DB::disconnect('sqlite');unlink($db);}
exit($failed?1:0);
