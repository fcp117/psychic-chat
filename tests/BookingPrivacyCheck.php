<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->instance('request',Illuminate\Http\Request::create('/'));$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{User,Booking,ChatSession,Message};
use App\Services\{BookingService,MinuteWallet,ReadingBilling,TranscriptPrivacy};
use Illuminate\Support\Facades\{DB,Artisan,Storage};
use Illuminate\Support\{Carbon,Str};
$db=tempnam(sys_get_temp_dir(),'booking-privacy-');$files=sys_get_temp_dir().'/booking-files-'.Str::uuid();
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'session.driver'=>'array','cache.default'=>'array','broadcasting.default'=>'null','mail.default'=>'array','filesystems.disks.local.root'=>$files]);DB::purge('sqlite');Storage::forgetDisk('local');
function ok($condition,$label){if(!$condition)throw new RuntimeException($label);echo "PASS $label\n";}
function reject($fn,$label){try{$fn();}catch(Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $e){echo "PASS $label\n";return;}throw new RuntimeException($label);}
function req($u,$data=[]){$r=Illuminate\Http\Request::create('/','POST',$data);$r->setUserResolver(fn()=>$u->fresh());return $r;}
function person($name,$role='user',$units=0){return User::forceCreate(['name'=>$name,'email'=>Str::uuid().'@example.invalid','password'=>'test-password','email_verified_at'=>now(),'role'=>$role,'is_approved'=>$role==='counselor','credit_units'=>$units]);}
$failed=false;
try{
 Carbon::setTestNow('2026-10-09 12:00:00');Artisan::call('migrate',['--force'=>true]);
 $service=app(BookingService::class);$wallet=app(MinuteWallet::class);$billing=app(ReadingBilling::class);$chat=app(App\Http\Controllers\ChatController::class);$privacy=app(TranscriptPrivacy::class);
 $u=person('Client','user',200*3600);$coach=person('Coach','counselor');$other=person('Other','user',100*3600);$admin=person('Admin','admin');
 DB::table('coach_availability')->insert(['coach_id'=>$coach->id,'starts_at'=>now()->addHour(),'ends_at'=>now()->addHours(12),'timezone'=>'UTC','created_at'=>now(),'updated_at'=>now()]);
 $b=$service->book($u,$coach,now()->addHours(2),20,'Asia/Manila');
 ok($u->fresh()->credit_units===720000 && $wallet->reserved($u)===72000,'booking holds minutes without spending them');
 reject(fn()=>$service->book($other,$coach,now()->addHours(2)->addMinutes(10),20,'UTC'),'overlapping coach booking rejected');
 reject(fn()=>$service->book($other,$coach,now()->addHours(2)->addMinutes(25),5,'UTC'),'ten-minute grace buffer prevents overlap');
 reject(fn()=>DB::transaction(fn()=>$billing->record($u->fresh(),-181*3600,'admin_adjustment','Attempt to spend reserved minutes')),'other spending cannot consume reserved minutes');
 ok($u->fresh()->credit_units===720000,'failed deduction rolls back');
 $new=$service->book($u,$coach,now()->addHours(3),20,'UTC',$b);
 ok($b->fresh()->status==='rescheduled' && $wallet->reserved($u)===72000,'free reschedule releases old hold and reserves new slot once');
 $cancel=$service->book($other,$coach,now()->addHours(4),5,'UTC');$service->cancel($other,$cancel);ok($wallet->reserved($other)===0,'early cancellation releases all minutes');
 reject(fn()=>$service->join($other,$new),'unrelated user cannot join');
 Carbon::setTestNow('2026-10-09 15:00:00');$s=$service->join($u,$new);ok($s->status==='pending' && $u->fresh()->credit_units===720000,'client check-in requires coach acceptance before charging');
 $chat->accept(req($coach),$s);ok($new->fresh()->status==='active','coach accepts reserved reading');
 for($i=1;$i<=120;$i++){Carbon::setTestNow(Carbon::parse('2026-10-09 15:00:00')->addSeconds($i*10));$billing->settle($s->id,$u->id,false,true);$billing->settle($s->id,$coach->id,false,true);}
 ok($s->fresh()->status==='completed' && $s->fresh()->billed_units===72000,'booked reading ends at its reserved duration');
 ok($s->fresh()->end_reason==='ended','completed reservation does not claim the entire wallet is empty');
 ok($wallet->reserved($u->fresh())===0 && $new->fresh()->status==='completed','completed booking releases holds');
 // Purchased portions retain their own no-show history; reserved funds stay untouched until review.
 $pay=person('Package client','user',60*3600);$wallet->lot($pay,60*3600,'Purchased', (string)Str::uuid(),now()->addYear());
 $miss=$service->book($pay,$coach,Carbon::parse('2026-10-09 17:00:00'),20,'UTC');Carbon::setTestNow('2026-10-09 17:00:00');$service->join($coach,$miss);Carbon::setTestNow('2026-10-09 17:11:00');$service->sweep();
 ok($miss->fresh()->status==='review' && $pay->fresh()->credit_units===216000,'missed appointment queues review without automatic penalty');
 reject(fn()=>$service->review($other,$miss,true,'Not an administrator'),'only admin reviews penalties');
 $service->review($admin,$miss,true,'Coach attended; client did not attend.');ok($pay->fresh()->credit_units===180000 && $miss->fresh()->penalty_units===36000,'approved first no-show takes 50 percent');
 reject(fn()=>$service->review($admin,$miss,true,'Repeated request should not charge twice.'),'penalty review cannot run twice');
 $repeat=$service->book($pay,$coach,Carbon::parse('2026-10-09 19:00:00'),10,'UTC');Carbon::setTestNow('2026-10-09 19:00:00');$service->join($coach,$repeat);Carbon::setTestNow('2026-10-09 19:11:00');$service->sweep();$service->review($admin,$repeat,true,'Second documented no-show on this package.');ok($repeat->fresh()->penalty_units===36000,'second approved no-show uses same-package reserved portion in full');
 $waive=$service->book($u,$coach,Carbon::parse('2026-10-09 21:00:00'),10,'UTC');Carbon::setTestNow('2026-10-09 21:11:00');$service->sweep();reject(fn()=>$service->review($admin,$waive,true,'Neither participant checked in.'),'no penalty when coach did not check in');$service->review($admin,$waive,false,'Coach unavailable; release all minutes.');ok($wallet->reserved($u->fresh())===0,'waiving releases balance');
 // Retention never touches financial rows, support messages or coach notes.
 $oldUser=person('Old history');$old=ChatSession::create(['client_id'=>$oldUser->id,'counselor_id'=>$coach->id,'status'=>'completed','started_at'=>now()->subMonthsNoOverflow(25),'ended_at'=>now()->subMonthsNoOverflow(25)->addMinutes(5)]);
 Storage::disk('local')->put('chat-attachments/private.txt','Private attachment');
 $m=Message::create(['chat_session_id'=>$old->id,'sender_id'=>$oldUser->id,'content'=>'Old transcript','attachment_path'=>'chat-attachments/private.txt','attachment_name'=>'private.txt','created_at'=>now()->subMonthsNoOverflow(25)]);
 DB::table('coach_notes')->insert(['client_id'=>$oldUser->id,'counselor_id'=>$coach->id,'body'=>'Private note','created_at'=>now(),'updated_at'=>now()]);
 $thread=DB::table('support_threads')->insertGetId(['user_id'=>$oldUser->id,'category'=>'technical','created_at'=>now(),'updated_at'=>now()]);DB::table('support_messages')->insert(['support_thread_id'=>$thread,'sender_id'=>$oldUser->id,'body'=>'Support content','request_key'=>(string)Str::uuid(),'created_at'=>now(),'updated_at'=>now()]);
 DB::table('credit_transactions')->insert(['user_id'=>$oldUser->id,'kind'=>'fixture','amount_units'=>0,'balance_units'=>0,'reason'=>'Keep financial record','created_at'=>now(),'updated_at'=>now()]);
 $privacy->retention();$n=DB::table('transcript_notices')->where('client_id',$oldUser->id)->first();ok($n && Message::find($m->id),'overdue history receives notice rather than immediate deletion');
 Carbon::setTestNow(now()->addDays(29));$privacy->retention();ok(Message::find($m->id)!==null,'at least 30 days notice required');Carbon::setTestNow(now()->addDays(2));$privacy->retention();ok(Message::find($m->id)===null && !Storage::disk('local')->exists('chat-attachments/private.txt'),'eligible messages and private attachment deleted after notice');
 ok(DB::table('coach_notes')->where('client_id',$oldUser->id)->exists()&&DB::table('support_messages')->where('support_thread_id',$thread)->exists()&&DB::table('credit_transactions')->where('user_id',$oldUser->id)->exists(),'excluded notes, support and financial records preserved');
 $pc=app(App\Http\Controllers\PrivacyController::class);
 reject(fn()=>$pc->store(req($other,['kind'=>'transcripts','chat_session_id'=>$s->id,'reason'=>'Attempt unrelated transcript deletion','consent'=>true])),'cannot request another conversation deletion');
 $pc->store(req($u,['kind'=>'transcripts','chat_session_id'=>$s->id,'reason'=>'Please remove this old conversation.','consent'=>true]));$pr=DB::table('privacy_requests')->where('user_id',$u->id)->first();$freshMessage=Message::create(['chat_session_id'=>$s->id,'sender_id'=>$u->id,'content'=>'New after request']);$privacy->review($admin,$pr->id,true,'Confirmed request, no open reading.');ok(Message::find($freshMessage->id)!==null,'manual request snapshot preserves later messages');
 // New paid activity invalidates an old deletion countdown.
 $reset=person('Retention reset');$rs=ChatSession::create(['client_id'=>$reset->id,'counselor_id'=>$coach->id,'status'=>'completed','started_at'=>now()->subMonthsNoOverflow(25)]);$rm=Message::create(['chat_session_id'=>$rs->id,'sender_id'=>$reset->id,'content'=>'Keep after renewed activity','created_at'=>now()->subMonthsNoOverflow(25)]);$privacy->retention();ok(DB::table('transcript_notices')->where('client_id',$reset->id)->exists(),'inactive client receives advance notice');
 $rs->update(['started_at'=>now()]);Carbon::setTestNow(now()->addDays(31));$privacy->retention();ok(Message::find($rm->id)!==null && !DB::table('transcript_notices')->where('client_id',$reset->id)->exists(),'new paid session resets retention clock');
 // Closure is admin-reviewed and cannot destroy financial records.
 $close=person('Closure fixture','user',5*3600);$wallet->ensure($close);$cs=ChatSession::create(['client_id'=>$close->id,'counselor_id'=>$coach->id,'status'=>'completed']);Message::create(['chat_session_id'=>$cs->id,'sender_id'=>$close->id,'content'=>'Remove on closure']);
 reject(fn()=>$pc->store(req($close,['kind'=>'account','reason'=>'Please close my test account.','consent'=>true,'password'=>'incorrect'])),'account closure requires current password');
 Illuminate\Support\Facades\Auth::guard('web')->setUser($close);
 $pc->store(req($close,['kind'=>'account','reason'=>'Please close my test account.','consent'=>true,'password'=>'test-password']));$closure=DB::table('privacy_requests')->where('user_id',$close->id)->first();$privacy->review($admin,$closure->id,true,'Owner confirmed closure and balance forfeiture.');
 ok($close->fresh()->closed_at && $close->fresh()->is_suspended && $close->fresh()->credit_units===0 && $close->fresh()->name==='Closed account','approved closure anonymizes login and forfeits balance');
 ok(!Message::where('chat_session_id',$cs->id)->exists() && DB::table('credit_transactions')->where('user_id',$close->id)->where('kind','account_closure')->exists(),'closure deletes chats but retains audited financial record');
 reject(fn()=>$privacy->review($admin,$closure->id,true,'Repeated approval must be rejected.'),'closure cannot be applied twice');
 // Render server payloads for all three account types without touching the real database.
 $kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
 foreach([$u,$coach,$admin] as $viewer){foreach(['/bookings','/privacy-requests'] as $path){Illuminate\Support\Facades\Auth::guard('web')->setUser($viewer->fresh());$r=Illuminate\Http\Request::create($path);$r->headers->set('X-Inertia','true');$r->headers->set('X-Inertia-Version',$app->make(App\Http\Middleware\HandleInertiaRequests::class)->version($r));$response=$kernel->handle($r);ok($response->getStatusCode()===200,$viewer->role.' can load '.$path);}}
 echo "ALL BOOKING AND PRIVACY CHECKS PASSED\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString());}
finally{Carbon::setTestNow();DB::disconnect('sqlite');if(is_file($db))unlink($db);Storage::disk('local')->deleteDirectory('chat-attachments');if(is_dir($files))rmdir($files);}
exit($failed?1:0);
