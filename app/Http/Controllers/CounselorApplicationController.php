<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Models\ChatSession;
use App\Services\AppNotifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
class CounselorApplicationController extends Controller {
 public function show(Request $r) {
  abort_unless(in_array($r->user()->role,['user','counselor']),403);
  return Inertia::render('Counselors/Apply',['application'=>DB::table('counselor_applications')->where('user_id',$r->user()->id)->first()]);
 }
 public function store(Request $r) {
  abort_unless($r->user()->role==='user',403);
  $v=$r->validate(['biography'=>'required|string|min:80|max:2000','specialties'=>'required|string|max:200','languages'=>'required|string|max:200','years_experience'=>'required|integer|min:0|max:60','qualifications'=>'required|string|min:10|max:2000','availability'=>'required|string|min:5|max:500','consent'=>'accepted']); unset($v['consent']);
  DB::transaction(function()use($r,$v){
   $u=User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail(); abort_unless($u->role==='user' && !$u->is_suspended && $u->hasVerifiedEmail(),403);
   $old=DB::table('counselor_applications')->where('user_id',$u->id)->first();
   if($old && $old->status!=='rejected') throw ValidationException::withMessages(['application'=>'Your application has already been submitted.']);
   $data=$v+['status'=>'pending','review_note'=>null,'reviewed_by'=>null,'reviewed_at'=>null,'updated_at'=>now()];
   if($old) {DB::table('counselor_applications')->where('id',$old->id)->update($data);$id=$old->id;}
   else $id=DB::table('counselor_applications')->insertGetId($data+['user_id'=>$u->id,'created_at'=>now()]);
   $audit=DB::table('admin_audits')->insertGetId(['actor_id'=>$u->id,'action'=>'counselor.application_submitted','target'=>'application:'.$id,'before'=>null,'after'=>json_encode(['status'=>'pending']),'created_at'=>now(),'updated_at'=>now()]);
   foreach(User::where('role','admin')->where('is_suspended',false)->pluck('id') as $admin) AppNotifications::send($admin,'application:'.$id.':'.$audit,'Counselor application received',$u->name.' has submitted an application.',route('admin.settings',['section'=>'applications'],false));
  });
  return back()->with('success','Application submitted. An administrator will review it.');
 }
 public function review(Request $r,int $application) {
  abort_unless($r->user()->role==='admin',403);
  $v=$r->validate(['decision'=>'required|in:approved,rejected','review_note'=>'required|string|min:5|max:1000','rate_per_hour'=>'nullable|integer|min:1|max:100000']);
  $ref=DB::table('counselor_applications')->find($application);abort_unless($ref,404);
  DB::transaction(function()use($r,$v,$ref){
   $u=User::whereKey($ref->user_id)->lockForUpdate()->firstOrFail();
   $old=DB::table('counselor_applications')->where('id',$ref->id)->lockForUpdate()->first();
   if($old->status!=='pending') throw ValidationException::withMessages(['decision'=>'This application has already been reviewed. Refresh the list.']);
   if($v['decision']==='approved') {
    if($u->role!=='user'||$u->is_suspended||!$u->hasVerifiedEmail()) throw ValidationException::withMessages(['decision'=>'Only an active, verified User account can become a counselor.']);
    if(ChatSession::where(fn($q)=>$q->where('client_id',$u->id)->orWhere('counselor_id',$u->id))->whereIn('status',['pending','active'])->exists()) throw ValidationException::withMessages(['decision'=>'End or cancel this user’s open readings before approving.']);
    $u->forceFill(['role'=>'counselor','is_approved'=>true,'rate_per_hour'=>$v['rate_per_hour']??null])->save();
   }
   $data=['status'=>$v['decision'],'review_note'=>$v['review_note'],'reviewed_by'=>$r->user()->id,'reviewed_at'=>now(),'updated_at'=>now()];
   DB::table('counselor_applications')->where('id',$old->id)->update($data);
   $audit=DB::table('admin_audits')->insertGetId(['actor_id'=>$r->user()->id,'action'=>'counselor.application_'.$v['decision'],'target'=>'application:'.$old->id,'before'=>json_encode(['status'=>$old->status]),'after'=>json_encode($data+['rate_per_hour'=>$v['rate_per_hour']??null]),'created_at'=>now(),'updated_at'=>now()]);
   AppNotifications::send($u->id,'application:'.$old->id.':'.$audit,'Counselor application '.$v['decision'],$v['review_note'],route('counselor.apply',[],false));
  });
  return back()->with('success','Application reviewed. The applicant has been notified.');
 }
 public function profile(User $counselor) {
  abort_unless($counselor->role==='counselor' && $counselor->is_approved && !$counselor->is_suspended && $counselor->hasVerifiedEmail(),404);
  $profile=DB::table('counselor_applications')->where('user_id',$counselor->id)->where('status','approved')->first(['biography','specialties','languages','years_experience','availability']);
  return Inertia::render('Counselors/Profile',['counselor'=>$counselor->only(['id','name','profile_photo_url']),'profile'=>$profile,'rate'=>app(\App\Services\ReadingBilling::class)->rate($counselor)]);
 }
}
