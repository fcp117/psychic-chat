<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\{DB,Mail};
use App\Models\User;
use App\Services\StaffAccess;
class SendStaffEmail implements ShouldQueue {
 use Queueable;
 public $tries=1;
 public function __construct(public int $emailId){}
 public function handle(): void {
  // Claim once. A provider timeout is not retried automatically to avoid duplicate mail.
  $claimed=DB::table('staff_emails')->where('id',$this->emailId)->where('status','queued')->update(['status'=>'sending','updated_at'=>now()]);
  if(!$claimed)return;
  $entry=DB::table('staff_emails')->find($this->emailId);
  $sender=User::find($entry->sender_id);$recipient=User::find($entry->recipient_id);
  if(!StaffAccess::allows($sender,'email')||!$recipient||$recipient->closed_at||!$recipient->email_verified_at||$recipient->email!==$entry->recipient_email){
   DB::table('staff_emails')->where('id',$this->emailId)->update(['status'=>'cancelled','updated_at'=>now()]);return;
  }
  try {
   Mail::raw($entry->body,fn($message)=>$message->to($entry->recipient_email)->subject($entry->subject));
   DB::table('staff_emails')->where('id',$this->emailId)->update(['status'=>'accepted','sent_at'=>now(),'updated_at'=>now()]);
  }catch(\Throwable $error){
   DB::table('staff_emails')->where('id',$this->emailId)->update(['status'=>'failed','updated_at'=>now()]);
   // Do not write recipient addresses or message content into exception logs.
  }
 }
 public function failed(?\Throwable $exception):void{
  DB::table('staff_emails')->where('id',$this->emailId)->whereIn('status',['queued','sending'])->update(['status'=>'failed','updated_at'=>now()]);
 }
}
