<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class MinuteWallet {
 public function reserved(User $u,?int $exceptBooking=null): int {
  return (int)DB::table('booking_holds as h')->join('minute_lots as l','l.id','=','h.lot_id')->where('l.user_id',$u->id)->where('l.remaining_units','>',0)->where(fn($q)=>$q->whereNull('l.expires_at')->orWhere('l.expires_at','>',now()))->whereNull('h.released_at')->when($exceptBooking,fn($q)=>$q->where('h.booking_id','!=',$exceptBooking))->sum('h.remaining_units');
 }
 public function available(User $u,?int $booking=null): int {return max(0,$u->credit_units-$this->reserved($u,$booking));}
 // All methods modifying balances require the caller to lock this user in a transaction.
 public function ensure(User $u): void {
  $tracked=(int)DB::table('minute_lots')->where('user_id',$u->id)->sum('remaining_units');
  if($u->credit_units>$tracked)$this->lot($u,$u->credit_units-$tracked,'Existing balance — no expiry');
 }
 public function lot(User $u,int $units,string $label,?string $purchase=null,$expiry=null): void {
  DB::table('minute_lots')->insert(['user_id'=>$u->id,'purchase_id'=>$purchase,'label'=>$label,'original_units'=>$units,'remaining_units'=>$units,'expires_at'=>$expiry,'created_at'=>now(),'updated_at'=>now()]);
 }
 public function expireLocked(User $u): void {
  $this->ensure($u);
  $lots=DB::table('minute_lots')->where('user_id',$u->id)->where('remaining_units','>',0)->whereNotNull('expires_at')->where('expires_at','<=',now())->lockForUpdate()->get();
  foreach($lots as $lot){$u->credit_units-=$lot->remaining_units;$u->save();DB::table('minute_lots')->where('id',$lot->id)->update(['remaining_units'=>0,'updated_at'=>now()]);DB::table('credit_transactions')->insert(['user_id'=>$u->id,'kind'=>'minutes_expired','amount_units'=>-$lot->remaining_units,'balance_units'=>$u->credit_units,'earning_units'=>0,'unit_type'=>'minutes','reason'=>'Expired package: '.$lot->label,'created_at'=>now(),'updated_at'=>now()]);}
 }
 public function refresh(User $u): User {
  return DB::transaction(function()use($u){$locked=User::whereKey($u->id)->lockForUpdate()->firstOrFail();$this->expireLocked($locked);return $locked;},3);
 }
 public function change(User $u,int $amount,string $label,?int $booking=null): void {
  $this->ensure($u);
  if($amount>0){$this->lot($u,$amount,$label);return;}
  $left=-$amount;
  $limit=$booking?(int)DB::table('booking_holds')->where('booking_id',$booking)->whereNull('released_at')->sum('remaining_units'):$this->available($u);
  if($left>$limit)throw \Illuminate\Validation\ValidationException::withMessages(['minutes'=>'Insufficient available minutes. Appointment reservations cannot be spent elsewhere.']);
  $lots=DB::table('minute_lots')->where('user_id',$u->id)->where('remaining_units','>',0)->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')->orderBy('expires_at')->orderBy('id')->lockForUpdate()->get();
  foreach($lots as $lot){
   $holds=DB::table('booking_holds')->where('lot_id',$lot->id)->whereNull('released_at');
   $own=$booking?(clone $holds)->where('booking_id',$booking)->first():null;
   $spendable=$booking ? ($own?->remaining_units ?? 0) : max(0,$lot->remaining_units-(int)$holds->sum('remaining_units'));
   $take=min($left,$lot->remaining_units,$spendable);
   if($take){DB::table('minute_lots')->where('id',$lot->id)->decrement('remaining_units',$take);if($own)DB::table('booking_holds')->where('id',$own->id)->decrement('remaining_units',$take);}
   $left-=$take;if(!$left)break;
  }
  if($left)throw new \RuntimeException('Minute wallet balance mismatch.');
 }
 public function welcomeKeys(User $u): array {
  $values=['email:'.mb_strtolower(trim($u->email))];
  if($u->birthdate)$values[]='person:'.preg_replace('/\s+/u',' ',mb_strtolower(trim($u->name))).'|'.$u->birthdate->format('Y-m-d');
  return array_map(fn($value)=>hash_hmac('sha256',$value,(string)config('app.key')),$values);
 }
 public function welcomeAvailable(User $u): bool {return $u->welcome_eligible && !DB::table('welcome_claims')->whereIn('identity_key',$this->welcomeKeys($u))->exists();}
}
