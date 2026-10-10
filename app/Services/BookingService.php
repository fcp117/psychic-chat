<?php
namespace App\Services;
use App\Models\{Booking,ChatSession,User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
class BookingService {
 const DURATIONS=[5,10,20,30,60];
 public function fail(string $message): never {throw ValidationException::withMessages(['booking'=>$message]);}
 public function audit(int $actor,string $action,Booking $b,array $details=[]): void {
  DB::table('admin_audits')->insert(['actor_id'=>$actor,'action'=>$action,'target'=>'booking:'.$b->id,'before'=>null,'after'=>json_encode($details),'created_at'=>now(),'updated_at'=>now()]);
 }
 public function notify(Booking $b,string $event,string $text): void {
  foreach([$b->client_id,$b->coach_id] as $id)AppNotifications::send($id,'booking:'.$b->id.':'.$event,'Appointment update',$text,route('bookings.index',[],false));
 }
 public function release(Booking $b): void {DB::table('booking_holds')->where('booking_id',$b->id)->whereNull('released_at')->update(['released_at'=>now()]);}
 public function penaltyQuote(Booking $b): array {
  $parts=[];
  foreach(DB::table('booking_holds as h')->join('minute_lots as l','l.id','=','h.lot_id')->where('h.booking_id',$b->id)->whereNull('h.released_at')->select('h.*','l.purchase_id','l.label','l.expires_at')->get() as $h){
   $repeat=$h->purchase_id && DB::table('booking_holds as h')->join('minute_lots as l','l.id','=','h.lot_id')->join('bookings as b','b.id','=','h.booking_id')->where('l.purchase_id',$h->purchase_id)->where('b.penalty_units','>',0)->whereNotNull('b.reviewed_at')->exists();
   $parts[]=['lot_id'=>$h->lot_id,'label'=>$h->label,'percent'=>$repeat?100:50,'units'=>$repeat?(int)$h->remaining_units:(int)ceil($h->remaining_units/2),'expires_at'=>$h->expires_at];
  }
  return $parts;
 }
 public function reserve(User $client,Booking $b): void {
  $wallet=app(MinuteWallet::class);$wallet->ensure($client);$left=$b->minutes*3600;
  $lots=DB::table('minute_lots')->where('user_id',$client->id)->where('remaining_units','>',0)->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',$b->ends_at))->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')->orderBy('expires_at')->orderBy('id')->lockForUpdate()->get();
  foreach($lots as $lot){$held=(int)DB::table('booking_holds')->where('lot_id',$lot->id)->whereNull('released_at')->sum('remaining_units');$take=min($left,max(0,$lot->remaining_units-$held));if($take)DB::table('booking_holds')->insert(['booking_id'=>$b->id,'lot_id'=>$lot->id,'original_units'=>$take,'remaining_units'=>$take]);$left-=$take;if(!$left)break;}
  if($left)$this->fail('Not enough unreserved minutes that remain valid through this appointment. Add minutes or choose an earlier date.');
 }
 public function book(User $client,User $coach,Carbon $start,int $minutes,string $timezone,?Booking $previous=null): Booking {
  return DB::transaction(function()use($client,$coach,$start,$minutes,$timezone,$previous){
   $client=User::whereKey($client->id)->lockForUpdate()->firstOrFail();$coach=User::whereKey($coach->id)->lockForUpdate()->firstOrFail();
   if($client->role!=='user'||$client->is_suspended||!$client->hasVerifiedEmail())$this->fail('A verified client account is required.');
   if(!app(CoachSite::class)->allows($coach->id)||!app(CoachSite::class)->eligible()->whereKey($coach->id)->exists())$this->fail('This coach is not available for new bookings.');
   if(!in_array($minutes,self::DURATIONS,true)||$start->lt(now()->addHour())||$start->gt(now()->addDays(90)))$this->fail('Choose a supported duration and a start between one hour and 90 days from now.');
   // Ten protected minutes after each slot allow the approved late-arrival grace without double-booking.
   $end=$start->copy()->addMinutes($minutes+10);
   if($previous){$previous=Booking::whereKey($previous->id)->lockForUpdate()->firstOrFail();if($previous->client_id!==$client->id||$previous->coach_id!==$coach->id||$previous->status!=='booked'||now()->gt($previous->starts_at->copy()->subHour()))$this->fail('Free rescheduling closes one hour before the appointment.');}
   if(!DB::table('coach_availability')->where('coach_id',$coach->id)->where('starts_at','<=',$start)->where('ends_at','>=',$end)->exists())$this->fail('That time is outside the coach’s available hours (including the 10-minute buffer).');
   $overlap=Booking::whereIn('status',['booked','joining','active','review'])->where(fn($q)=>$q->where('coach_id',$coach->id)->orWhere('client_id',$client->id))->where('starts_at','<',$end)->where('ends_at','>',$start)->when($previous,fn($q)=>$q->where('id','!=',$previous->id))->exists();
   if($overlap)$this->fail('This slot overlaps another appointment. Please choose another time.');
   if(ChatSession::where(fn($q)=>$q->where('client_id',$client->id)->orWhere('counselor_id',$coach->id))->where('status','active')->exists())$this->fail('Finish the active reading before booking.');
   if($previous){$this->release($previous);$previous->update(['status'=>'rescheduled']);}
   app(MinuteWallet::class)->expireLocked($client);
   $b=Booking::create(['client_id'=>$client->id,'coach_id'=>$coach->id,'starts_at'=>$start,'ends_at'=>$end,'minutes'=>$minutes,'timezone'=>$timezone]);$this->reserve($client,$b);
   $this->audit($client->id,'booking.created',$b,['minutes'=>$minutes,'starts_at'=>$start->toIso8601String(),'previous'=>$previous?->id]);$this->notify($b,'created','Your '.$minutes.'-minute appointment is reserved. Check Bookings for the time in your time zone.');return $b;
  },3);
 }
 public function cancel(User $actor,Booking $ref): void {
  DB::transaction(function()use($actor,$ref){User::whereKey($ref->client_id)->lockForUpdate()->firstOrFail();User::whereKey($ref->coach_id)->lockForUpdate()->firstOrFail();$b=Booking::whereKey($ref->id)->lockForUpdate()->firstOrFail();abort_unless(in_array($actor->id,[$b->client_id,$b->coach_id],true)||$actor->role==='admin',403);
   if(!in_array($b->status,['booked','joining','review'],true))$this->fail('This appointment can no longer be cancelled here.');
   $coachOrAdmin=$actor->id===$b->coach_id||$actor->role==='admin';
   if(!$coachOrAdmin && (now()->gt($b->starts_at->copy()->subHour())||$b->status==='review')){
    $b->update(['status'=>'review','review_reason'=>'Client requested late cancellation. Minutes remain reserved pending review.']);
   }else{$this->release($b);$b->update(['status'=>'cancelled','review_reason'=>$coachOrAdmin?'Coach/admin cancellation; no client penalty':'Cancelled at least one hour before start']);}
   if($s=$b->session){if($s->status==='active')$this->fail('End the active reading first.');if($s->status==='pending')$s->update(['status'=>'rejected','end_reason'=>'booking_cancelled','ended_at'=>now()]);}
   $this->audit($actor->id,'booking.cancelled',$b,['status'=>$b->status]);$this->notify($b,'cancel:'.$b->status,$b->review_reason);
  },3);
 }
 public function join(User $actor,Booking $ref): ?ChatSession {
  return DB::transaction(function()use($actor,$ref){
   $client=User::whereKey($ref->client_id)->lockForUpdate()->firstOrFail();$coach=User::whereKey($ref->coach_id)->lockForUpdate()->firstOrFail();$b=Booking::whereKey($ref->id)->lockForUpdate()->firstOrFail();abort_unless(in_array($actor->id,[$b->client_id,$b->coach_id],true),403);
   if($b->status==='active')return $b->session;
   if(!in_array($b->status,['booked','joining'],true)||now()->lt($b->starts_at)||now()->gt($b->starts_at->copy()->addMinutes(10)))$this->fail('Join from the scheduled start until ten minutes afterwards.');
   if($client->is_suspended||$coach->is_suspended||$client->closed_at||$coach->closed_at||!app(CoachSite::class)->allows($coach->id)||!app(CoachSite::class)->eligible()->whereKey($coach->id)->exists())$this->fail('The appointment cannot start. Contact support to release your reservation.');
   if(ChatSession::where(fn($q)=>$q->where('client_id',$client->id)->orWhere('counselor_id',$coach->id))->whereIn('status',['pending','active'])->where(fn($q)=>$q->whereNull('booking_id')->orWhere('booking_id','!=',$b->id))->exists())$this->fail('Finish other open readings first.');
   $b->update([$actor->id===$client->id?'client_joined_at':'coach_joined_at'=>now()]);
   if($actor->id===$coach->id)return $b->session;
   $s=$b->session;
   if(!$s){$s=ChatSession::create(['client_id'=>$client->id,'counselor_id'=>$coach->id,'booking_id'=>$b->id,'status'=>'pending','billing_version'=>2,'agreed_rate'=>60,'agreed_at'=>now(),'disconnect_seconds'=>app(ReadingBilling::class)->settings()->disconnect_seconds,'client_seen_at'=>now()]);$b->update(['status'=>'joining']);app(ReadingBilling::class)->notify($s);}
   return $s;
  },3);
 }
 public function review(User $admin,Booking $ref,bool $penalize,string $reason): void {
  abort_unless($admin->role==='admin',403);
  DB::transaction(function()use($admin,$ref,$penalize,$reason){
   $client=User::whereKey($ref->client_id)->lockForUpdate()->firstOrFail();User::whereKey($ref->coach_id)->lockForUpdate()->firstOrFail();$b=Booking::whereKey($ref->id)->lockForUpdate()->firstOrFail();if($b->status!=='review')$this->fail('This appointment is not awaiting review.');
   if($penalize && ($b->client_joined_at || !$b->coach_joined_at || now()->lte($b->starts_at->copy()->addMinutes(10))))$this->fail('No-show penalties require a coach check-in, no client check-in, and the expired grace period. Otherwise waive and release.');
   $total=0;
   if($penalize){
    foreach($this->penaltyQuote($b) as $part){
     if($part['expires_at'] && now()->gte($part['expires_at']))$this->fail('Reserved minutes have expired; waive this penalty.');
     DB::table('minute_lots')->where('id',$part['lot_id'])->decrement('remaining_units',$part['units']);$total+=$part['units'];
    }
    $client->credit_units-=$total;$client->save();
    DB::table('credit_transactions')->insert(['user_id'=>$client->id,'actor_id'=>$admin->id,'kind'=>'booking_no_show','unit_type'=>'minutes','amount_units'=>-$total,'balance_units'=>$client->credit_units,'earning_units'=>0,'reason'=>'Booking #'.$b->id.': '.$reason,'created_at'=>now(),'updated_at'=>now()]);
   }
   $this->release($b);$b->update(['status'=>$penalize?'no_show':'cancelled','penalty_units'=>$total,'reviewed_by'=>$admin->id,'reviewed_at'=>now(),'review_reason'=>$reason]);$this->audit($admin->id,'booking.reviewed',$b,['penalty_units'=>$total,'reason'=>$reason]);$this->notify($b,'reviewed','Your appointment review is complete: '.($total/3600).' minutes deducted. Remaining reservation released.');
  },3);
 }
 public function sweep(): void {
  Booking::whereIn('status',['booked','joining','active'])->orderBy('id')->each(function($ref){DB::transaction(function()use($ref){User::whereKey($ref->client_id)->lockForUpdate()->firstOrFail();$b=Booking::whereKey($ref->id)->lockForUpdate()->first();$s=$b->session;
   if($s && $s->status==='completed'){$this->release($b);$b->update(['status'=>'completed']);return;}
   if(in_array($b->status,['booked','joining'],true)&&now()->gt($b->starts_at->copy()->addMinutes(10))){if($s && $s->status==='pending')$s->update(['status'=>'rejected','ended_at'=>now(),'end_reason'=>'booking_review']);$b->update(['status'=>'review','review_reason'=>'Appointment did not start within the grace period. Admin review required; no automatic penalty.']);$this->notify($b,'review','Appointment needs review. No penalty has been deducted.');}
  },3);});
 }
}
