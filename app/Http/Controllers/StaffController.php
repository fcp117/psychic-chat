<?php
namespace App\Http\Controllers;
use App\Models\{User,Booking};
use App\Services\StaffAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
class StaffController extends Controller {
 public function home(Request $r){
  return redirect()->route(match($r->user()->role){'admin'=>'admin.settings','encoder'=>'admin.tarot','manager'=>'staff.schedule','support'=>'support.index',default=>abort(403)});
 }
 public function schedule(Request $r){
  abort_unless(StaffAccess::allows($r->user(),'schedule'),403);
  $v=$r->validate(['month'=>'nullable|date_format:Y-m','coach'=>'nullable|integer|exists:users,id']);
  $start=Carbon::createFromFormat('!Y-m',$v['month']??now('Asia/Manila')->format('Y-m'),'Asia/Manila')->startOfMonth()->utc();$end=$start->copy()->timezone('Asia/Manila')->addMonth()->utc();
  $coaches=User::where('role','counselor')->get(['id','name','is_suspended','is_approved','manager_id']);
  $ids=isset($v['coach'])?$coaches->where('id',$v['coach'])->pluck('id'):$coaches->pluck('id');
  $windows=DB::table('coach_availability')->whereIn('coach_id',$ids)->where('starts_at','<',$end)->where('ends_at','>',$start)->get(['id','coach_id','starts_at','ends_at','is_available']);
  foreach($windows as $w){$w->starts_at=Carbon::parse($w->starts_at)->toIso8601String();$w->ends_at=Carbon::parse($w->ends_at)->toIso8601String();}
  $bookings=Booking::whereIn('coach_id',$ids)->whereIn('status',['booked','joining','active','review'])->where('starts_at','<',$end)->where('ends_at','>',$start)->get(['id','coach_id','starts_at','ends_at','status']);
  return Inertia::render('Staff/Schedule',['coaches'=>$coaches,'windows'=>$windows,'bookings'=>$bookings,'filters'=>['month'=>$start->copy()->timezone('Asia/Manila')->format('Y-m'),'coach'=>$v['coach']??'']]);
 }
 public function health(Request $r){
  abort_unless(StaffAccess::allows($r->user(),'health'),403);
  return Inertia::render('Staff/Health',['incidents'=>DB::table('system_incidents')->orderByDesc('created_at')->paginate(20)]);
 }
 public function privacy(Request $r,int $privacyRequest){
  abort_unless(StaffAccess::allows($r->user(),'privacy'),403);
  $v=$r->validate(['note'=>'required|string|min:10|max:1000']);
  DB::transaction(function()use($r,$v,$privacyRequest){
   $entry=DB::table('privacy_requests')->where('id',$privacyRequest)->lockForUpdate()->first();abort_unless($entry,404);
   abort_unless($entry->status==='pending',409);
   DB::table('privacy_requests')->where('id',$privacyRequest)->update(['support_note'=>$v['note'],'support_id'=>$r->user()->id,'support_reviewed_at'=>now(),'updated_at'=>now()]);
   DB::table('admin_audits')->insert(['actor_id'=>$r->user()->id,'action'=>'privacy.support_review','target'=>'privacy_request:'.$privacyRequest,'before'=>null,'after'=>json_encode($v),'created_at'=>now(),'updated_at'=>now()]);
  });return back()->with('success','Review saved for Admin. No data was deleted.');
 }
}
