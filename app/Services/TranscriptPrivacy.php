<?php
namespace App\Services;
use App\Models\{ChatSession,Message,User,Booking};
use Illuminate\Support\Facades\{DB,Storage,Mail};
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
class TranscriptPrivacy {
 public function sessions(User $u){return ChatSession::where(fn($q)=>$q->where('client_id',$u->id)->orWhere('counselor_id',$u->id));}
 public function messages(User $u,?int $session=null){
  if($session){$s=ChatSession::findOrFail($session);abort_unless(in_array($u->id,[$s->client_id,$s->counselor_id],true),403);return $s->conversationMessages();}
  return Message::whereIn('chat_session_id',$this->sessions($u)->select('id'));
 }
 // Queue private-file removal durably before deleting message rows; never delete notes, support or ledgers.
 public function purge($query): int {
  $count=0;(clone $query)->orderBy('id')->chunkById(200,function($messages)use(&$count){foreach($messages as $m){if($m->attachment_path)DB::table('private_file_deletions')->insertOrIgnore(['path'=>$m->attachment_path,'created_at'=>now(),'updated_at'=>now()]);}$ids=$messages->pluck('id');$count+=Message::whereIn('id',$ids)->delete();});return $count;
 }
 public function removeFiles(): void {
  DB::table('private_file_deletions')->orderBy('id')->limit(500)->get()->each(function($file){try{if(!Storage::disk('local')->exists($file->path)||Storage::disk('local')->delete($file->path))DB::table('private_file_deletions')->where('id',$file->id)->delete();}catch(\Throwable $e){report($e);}});
 }
 public function retention(): void {
  // Client ownership is the retention clock; coaches do not trigger deletion of every client at once.
  User::whereIn('id',ChatSession::select('client_id'))->whereNull('closed_at')->chunkById(100,function($users){foreach($users as $ref){
   try{DB::transaction(function()use($ref){
    $u=User::whereKey($ref->id)->lockForUpdate()->firstOrFail();$sessions=ChatSession::where('client_id',$u->id);
    if((clone $sessions)->whereIn('status',['pending','active'])->exists())return;
    $messages=Message::whereIn('chat_session_id',(clone $sessions)->select('id'));$first=(clone $messages)->min('created_at');if(!$first)return;
    $lastSession=(clone $sessions)->max('started_at');$purchase=DB::table('credit_purchases')->where('user_id',$u->id)->whereNotNull('paid_at')->max('paid_at');
    $activity=Carbon::parse(max(array_filter([$first,$lastSession,$purchase])));
    $notice=DB::table('transcript_notices')->where('client_id',$u->id)->first();
    if($activity->gt(now()->subMonthsNoOverflow(23))){if($notice)DB::table('transcript_notices')->where('id',$notice->id)->delete();return;}
    if(!$notice||!Carbon::parse($notice->activity_at)->eq($activity)){
     if(app()->environment('production') && in_array(config('mail.default'),['log','array'],true))throw new \RuntimeException('Real email delivery must be configured before retention deletion notices are activated.');
     $deleteAfter=$activity->copy()->addMonthsNoOverflow(24)->max(now()->addDays(30));
     // A failed mail delivery does not start the deletion countdown. Retry on the next scheduled run.
     Mail::raw('Your Intuition Island chat transcripts and attachments are scheduled for deletion on '.$deleteAfter->toDateString().'. Download your conversations from '.route('chat.index').'. Financial records, support messages and private coach notes are excluded. A new paid session or confirmed purchase resets the inactivity clock. Contact support with questions.',fn($m)=>$m->to($u->email)->subject('Your chat transcripts: 30-day deletion notice'));
     DB::table('transcript_notices')->updateOrInsert(['client_id'=>$u->id],['activity_at'=>$activity,'notified_at'=>now(),'delete_after'=>$deleteAfter,'through_message_id'=>(int)(clone $messages)->max('id'),'created_at'=>now(),'updated_at'=>now()]);
     AppNotifications::send($u->id,'retention:'.$activity->timestamp.':'.now()->timestamp,'Download your chat history','Chat transcripts and attachments are scheduled for deletion on '.$deleteAfter->toDateString().'.',route('privacy.requests',[],false));return;
    }
    if($notice->notified_at && now()->gte($notice->delete_after) && now()->gte(Carbon::parse($notice->notified_at)->addDays(30))){
     $count=$this->purge((clone $messages)->where('id','<=',$notice->through_message_id));
     DB::table('admin_audits')->insert(['actor_id'=>null,'action'=>'transcripts.retention_deleted','target'=>'user:'.$u->id,'before'=>null,'after'=>json_encode(['messages'=>$count,'notice_id'=>$notice->id]),'created_at'=>now(),'updated_at'=>now()]);
     DB::table('transcript_notices')->where('id',$notice->id)->delete();
    }
   },3);}catch(\Throwable $e){report($e);}
  }});$this->removeFiles();
 }
 public function review(User $admin,int $id,bool $approve,string $reason): void {
  abort_unless($admin->role==='admin',403);$ref=DB::table('privacy_requests')->find($id);abort_unless($ref,404);
  DB::transaction(function()use($admin,$id,$approve,$reason,$ref){
   $u=User::whereKey($ref->user_id)->lockForUpdate()->firstOrFail();$r=DB::table('privacy_requests')->where('id',$id)->lockForUpdate()->first();abort_unless($r->status==='pending',409,'This request has already been reviewed.');
   $count=0;
   if($approve){
    if($this->sessions($u)->whereIn('status',['active','pending'])->exists())app(BookingService::class)->fail('End open readings before processing deletion.');
    if($r->kind==='account'){
     abort_if($u->role==='admin',422,'Administrator accounts cannot close through this workflow.');
     if(DB::table('credit_purchases')->where('user_id',$u->id)->whereNull('paid_at')->whereIn('status',['creating','pending','cancelled','setup_failed'])->exists())app(BookingService::class)->fail('Resolve outstanding payment checkouts with the provider before closing this account. A cancelled checkout can still receive a delayed payment.');
     foreach(Booking::where(fn($q)=>$q->where('client_id',$u->id)->orWhere('coach_id',$u->id))->whereIn('status',['booked','joining','review'])->get() as $b)app(BookingService::class)->cancel($admin,$b);
     app(MinuteWallet::class)->expireLocked($u);
     if($u->credit_units>0)app(ReadingBilling::class)->record($u,-$u->credit_units,'account_closure','Confirmed account closure; unused minutes forfeited',$admin->id);
     $count=$this->purge($this->messages($u));
     if($u->profile_photo_path)DB::table('private_file_deletions')->insertOrIgnore(['path'=>$u->profile_photo_path,'created_at'=>now(),'updated_at'=>now()]);
     DB::table('sessions')->where('user_id',$u->id)->delete();
     $u->forceFill(['name'=>'Closed account','email'=>'closed-'.$u->id.'-'.Str::random(20).'@example.invalid','username'=>'closed_'.$u->id,'password'=>Str::random(64),'remember_token'=>null,'email_verified_at'=>null,'birthdate'=>null,'profile_photo_path'=>null,'is_suspended'=>true,'is_approved'=>false,'closed_at'=>now()])->save();
     // Keep the pseudonymous account key for financial, support and coach-note integrity.
    }else{$count=$this->purge($this->messages($u,$r->chat_session_id)->where('id','<=',$r->through_message_id));}
   }
   DB::table('privacy_requests')->where('id',$id)->update(['status'=>$approve?'approved':'rejected','decision_reason'=>$reason,'reviewed_by'=>$admin->id,'reviewed_at'=>now(),'updated_at'=>now()]);
   DB::table('admin_audits')->insert(['actor_id'=>$admin->id,'action'=>'privacy.request_'.($approve?'approved':'rejected'),'target'=>'privacy_request:'.$id,'before'=>null,'after'=>json_encode(['kind'=>$r->kind,'messages'=>$count,'reason'=>$reason]),'created_at'=>now(),'updated_at'=>now()]);
   if(!$u->closed_at)AppNotifications::send($u->id,'privacy:'.$id.':reviewed','Privacy request reviewed','Your request was '.($approve?'approved':'declined').'. Open Privacy requests for the reason.',route('privacy.requests',[],false));
  },3);$this->removeFiles();
 }
}
