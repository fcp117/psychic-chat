<?php
namespace App\Http\Controllers;
use App\Models\{Booking,User};
use App\Services\{BookingService,MinuteWallet,CoachSite};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
class BookingController extends Controller {
 public function index(Request $r){
  app(BookingService::class)->sweep();$u=app(MinuteWallet::class)->refresh($r->user());$site=app(CoachSite::class)->settings();
  $coaches=app(CoachSite::class)->eligible()->when($site['rainbow_only'],fn($q)=>$q->whereKey($site['rainbow_user_id']??0))->get(['id','name']);
  $bookings=Booking::with(['client:id,name','coach:id,name','session:id,booking_id,client_id,counselor_id,status'])->when($u->role!=='admin',fn($q)=>$q->where(fn($x)=>$x->where('client_id',$u->id)->orWhere('coach_id',$u->id)))->orderByRaw("CASE WHEN status='review' THEN 0 WHEN status IN ('booked','joining','active') THEN 1 ELSE 2 END")->orderBy('starts_at')->paginate(10)->withQueryString();
  $bookings->through(function($b)use($u){if($b->session)$b->session->setAttribute('conversation_id',$b->session->conversationId());if($u->role==='admin'&&$b->status==='review')$b->setAttribute('penalty_quote',app(BookingService::class)->penaltyQuote($b));return $b;});
  $availability=DB::table('coach_availability')->where('ends_at','>',now())->whereIn('coach_id',$u->role==='counselor'?[$u->id]:$coaches->pluck('id'))->orderBy('starts_at')->limit(200)->get();
  foreach($availability as $a){$a->starts_at=Carbon::parse($a->starts_at)->toIso8601String();$a->ends_at=Carbon::parse($a->ends_at)->toIso8601String();}
  $busy=Booking::whereIn('coach_id',$coaches->pluck('id'))->whereIn('status',['booked','joining','active','review'])->where('ends_at','>',now())->where('starts_at','<',now()->addDays(90))->get(['id','coach_id','starts_at','ends_at']);
  return Inertia::render('Bookings/Index',['bookings'=>$bookings,'coaches'=>$coaches,'availability'=>$availability,'busy'=>$busy,'durations'=>BookingService::DURATIONS,'balance'=>app(MinuteWallet::class)->available($u)/3600,'reserved'=>app(MinuteWallet::class)->reserved($u)/3600]);
 }
 public function availability(Request $r){
  abort_unless($r->user()->role==='counselor' && $r->user()->is_approved,403);
  $v=$r->validate(['starts_at'=>'required|date','ends_at'=>'required|date|after:starts_at','timezone'=>'required|timezone']);$start=Carbon::parse($v['starts_at'],$v['timezone'])->utc();$end=Carbon::parse($v['ends_at'],$v['timezone'])->utc();
  if($start->lt(now())||$end->gt(now()->addDays(90))||$start->diffInHours($end)>24)app(BookingService::class)->fail('Choose future office hours within 90 days, at most 24 hours per window.');
  DB::transaction(function()use($r,$v,$start,$end){User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();if(DB::table('coach_availability')->where('coach_id',$r->user()->id)->where('starts_at','<',$end)->where('ends_at','>',$start)->exists())app(BookingService::class)->fail('This window overlaps existing office hours.');DB::table('coach_availability')->insert(['coach_id'=>$r->user()->id,'starts_at'=>$start,'ends_at'=>$end,'timezone'=>$v['timezone'],'created_at'=>now(),'updated_at'=>now()]);});return back()->with('success','Office hours saved.');
 }
 public function removeAvailability(Request $r,int $window){
  DB::transaction(function()use($r,$window){User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();$a=DB::table('coach_availability')->where('id',$window)->first();abort_unless($a && $a->coach_id===$r->user()->id,403);if(Booking::where('coach_id',$r->user()->id)->whereIn('status',['booked','joining','active','review'])->where('starts_at','<',$a->ends_at)->where('ends_at','>',$a->starts_at)->exists())app(BookingService::class)->fail('Cancel or resolve appointments in this window first.');DB::table('coach_availability')->where('id',$window)->delete();});return back();
 }
 public function store(Request $r){
  abort_unless($r->user()->role==='user',403);$v=$r->validate(['coach_id'=>'required|integer|exists:users,id','starts_at'=>'required|date','minutes'=>['required','integer',Rule::in(BookingService::DURATIONS)],'timezone'=>'required|timezone','consent'=>'required|accepted','reschedule_id'=>'nullable|integer|exists:bookings,id']);
  app(BookingService::class)->book($r->user(),User::findOrFail($v['coach_id']),Carbon::parse($v['starts_at'])->utc(),$v['minutes'],$v['timezone'],isset($v['reschedule_id'])?Booking::findOrFail($v['reschedule_id']):null);return back()->with('success','Appointment reserved. No minutes have been spent.');
 }
 public function cancel(Request $r,Booking $booking){app(BookingService::class)->cancel($r->user(),$booking);return back()->with('success','Cancellation request processed. Check the appointment status.');}
 public function join(Request $r,Booking $booking){$r->validate(['consent'=>'required|accepted']);$s=app(BookingService::class)->join($r->user(),$booking);return $s?redirect()->route('chat.room',$s->conversationId()):back()->with('success','You are checked in. Open the reading request when your client joins.');}
 public function review(Request $r,Booking $booking){$v=$r->validate(['penalize'=>'required|boolean','reason'=>'required|string|min:10|max:1000']);app(BookingService::class)->review($r->user(),$booking,$v['penalize'],$v['reason']);return back()->with('success','Review recorded.');}
}
