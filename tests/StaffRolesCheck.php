<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->instance('request',Illuminate\Http\Request::create('/'));
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{User,ChatSession};
use App\Services\StaffAccess;
use Illuminate\Support\Facades\{DB,Artisan,Auth,Queue,Mail};
use Illuminate\Support\Str;
$db=tempnam(sys_get_temp_dir(),'staff-roles-');
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'session.driver'=>'array','cache.default'=>'array','broadcasting.default'=>'null','mail.default'=>'array','features.forecast'=>true]);
DB::purge('sqlite');Queue::fake();
function checkStaff($value,$label){if(!$value)throw new RuntimeException($label);echo "PASS $label\n";}
function denyStaff($fn,$label){try{$fn();}catch(Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $e){echo "PASS $label\n";return;}throw new RuntimeException($label);}
function staffRequest($user,$data=[]){$r=Illuminate\Http\Request::create('/','POST',$data);$r->setUserResolver(fn()=>$user->fresh());return $r;}
$failed=false;
try{
 Artisan::call('migrate',['--force'=>true]);
 $users=[];foreach(StaffAccess::ROLES as $role)$users[$role]=User::forceCreate(['name'=>'Fixture '.$role,'email'=>$role.'@example.invalid','password'=>'fixture-password','role'=>$role,'email_verified_at'=>now(),'is_approved'=>$role==='counselor']);
 $manager2=User::forceCreate(['name'=>'Other manager','email'=>'other-manager@example.invalid','password'=>'fixture-password','role'=>'manager','email_verified_at'=>now()]);
 $users['counselor']->forceFill(['manager_id'=>$users['manager']->id])->save();
 $kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
 $access=['/admin/tarot-cards'=>['admin','encoder','manager'],'/admin/birth-chart'=>['admin','encoder','manager'],'/admin/moonoscope'=>['admin','encoder','manager'],'/staff/schedule'=>['admin','manager'],'/staff/health'=>['admin','support'],'/staff/emails'=>['admin','support'],'/admin/reports'=>['admin','manager','support'],'/admin'=>['admin']];
 foreach($access as $path=>$roles)foreach($users as $role=>$user){
  Auth::guard('web')->setUser($user->fresh());$r=Illuminate\Http\Request::create($path);
  $r->headers->set('X-Inertia','true');$r->headers->set('X-Inertia-Version',$app->make(App\Http\Middleware\HandleInertiaRequests::class)->version($r));
  $response=$kernel->handle($r);
  checkStaff($response->getStatusCode()===(in_array($role,$roles)?200:403),"$role route access $path (".$response->getStatusCode().")");
 }
 $session=ChatSession::create(['client_id'=>$users['user']->id,'counselor_id'=>$users['counselor']->id,'status'=>'completed']);
 $report=DB::table('user_reports')->insertGetId(['reporter_id'=>$users['user']->id,'reported_id'=>$users['counselor']->id,'chat_session_id'=>$session->id,'reason'=>'harassment','details'=>'A fixture report to verify the approval chain.','status'=>'open','created_at'=>now(),'updated_at'=>now()]);
 $workflow=app(App\Http\Controllers\ReportWorkflowController::class);$moderation=app(App\Http\Controllers\ReportController::class);
 $final=['action'=>'suspend','note'=>'Final decision after documented review.'];
 denyStaff(fn()=>$moderation->review(staffRequest($users['admin'],$final),$report),'Admin cannot skip workflow');
 denyStaff(fn()=>$workflow->recommend(staffRequest($users['encoder'],$final),$report),'Encoder cannot recommend restriction');
 $workflow->recommend(staffRequest($users['support'],$final),$report);
 checkStaff(!$users['counselor']->fresh()->is_suspended,'Support recommendation does not suspend');
 denyStaff(fn()=>$workflow->manager(staffRequest($manager2,['approve'=>true,'note'=>'Not my assigned coach.']),$report),'unassigned manager cannot approve coach restriction');
 $workflow->manager(staffRequest($users['manager'],['approve'=>true,'note'=>'Assigned manager reviewed this evidence.']),$report);
 checkStaff(!$users['counselor']->fresh()->is_suspended,'Manager approval does not suspend');
 denyStaff(fn()=>$moderation->review(staffRequest($users['support'],$final),$report),'Support cannot apply final decision');
 $moderation->review(staffRequest($users['admin'],$final),$report);
 checkStaff($users['counselor']->fresh()->is_suspended,'Admin applies approved restriction');
 denyStaff(fn()=>$moderation->review(staffRequest($users['admin'],$final),$report),'Final decision cannot repeat');
 $privacy=DB::table('privacy_requests')->insertGetId(['user_id'=>$users['user']->id,'kind'=>'transcripts','reason'=>'Please review this deletion request.','status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
 app(App\Http\Controllers\StaffController::class)->privacy(staffRequest($users['support'],['note'=>'Verified request and documented its scope.']),$privacy);
 checkStaff(DB::table('privacy_requests')->find($privacy)->status==='pending','Support privacy review does not delete or approve');
 denyStaff(fn()=>app(App\Services\TranscriptPrivacy::class)->review($users['support'],$privacy,true,'Attempt to approve deletion.'),'Support cannot execute privacy deletion');
 $birth=DB::table('birth_chart_templates')->first();
 app(App\Http\Controllers\BirthChartController::class)->update(staffRequest($users['encoder'],['body'=>'A considered reflection for this placement.','version'=>$birth->version,'reviewed'=>true]),$birth->id);
 checkStaff(DB::table('birth_chart_templates')->find($birth->id)->body==='A considered reflection for this placement.','Encoder publishes birth-chart content directly');
 $emails=app(App\Http\Controllers\StaffEmailController::class);$key=(string)Str::uuid();
 $data=['recipients'=>[$users['user']->id,$users['manager']->id],'template'=>'support_update','details'=>'We have reviewed your request and will follow up.','confirmed'=>true,'request_key'=>$key];
 denyStaff(fn()=>$emails->send(staffRequest($users['encoder'],$data)),'Encoder cannot send staff emails');
 $emails->send(staffRequest($users['support'],$data));$emails->send(staffRequest($users['support'],$data));
 checkStaff(DB::table('staff_emails')->count()===2,'email retries are idempotent and each recipient has a separate email');
 checkStaff(Queue::pushed(App\Jobs\SendStaffEmail::class)->count()===2,'two isolated email jobs queued');
 foreach(DB::table('staff_emails')->pluck('id') as $id){$job=new App\Jobs\SendStaffEmail($id);$job->handle();$job->handle();}
 checkStaff(DB::table('staff_emails')->where('status','accepted')->count()===2,'mail job accepted once via local array transport');
 checkStaff(Mail::mailer('array')->getSymfonyTransport()->messages()->count()===2,'no duplicate mail on job retry');
 foreach(Mail::mailer('array')->getSymfonyTransport()->messages() as $message)checkStaff(count($message->getOriginalMessage()->getTo())===1,'recipient addresses isolated');
 echo "ALL STAFF ROLE CHECKS PASSED\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString());}
finally{DB::disconnect('sqlite');if(is_file($db))unlink($db);}
exit($failed?1:0);
