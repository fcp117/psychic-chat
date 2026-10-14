<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\{StaffAccess,ServiceEmailTemplates};
use App\Jobs\SendStaffEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
class StaffEmailController extends Controller {
 public function index(Request $r){
  abort_unless(StaffAccess::allows($r->user(),'email'),403);
  $v=$r->validate(['q'=>'nullable|string|max:100','role'=>['nullable',Rule::in(StaffAccess::ROLES)]]);
  $recipients=User::whereNull('closed_at')->whereNotNull('email_verified_at')->when(!empty($v['role']),fn($q)=>$q->where('role',$v['role']))
   ->when(!empty($v['q']),fn($q)=>$q->where(fn($q)=>$q->where('name','like','%'.$v['q'].'%')->orWhere('email','like','%'.$v['q'].'%')))->orderBy('name')->paginate(15,['id','name','email','role'], 'recipients_page')->withQueryString();
  $history=DB::table('staff_emails as e')->join('users as s','s.id','=','e.sender_id')->join('users as u','u.id','=','e.recipient_id')->select('e.id','e.template','e.subject','e.status','e.created_at','e.sent_at','s.name as sender_name','u.name as recipient_name')->orderByDesc('e.id')->paginate(15,['*'],'history_page')->withQueryString();
  return Inertia::render('Staff/Emails',['recipients'=>$recipients,'history'=>$history,'templates'=>ServiceEmailTemplates::all(),'roles'=>StaffAccess::ROLES,'filters'=>$v]);
 }
 public function send(Request $r){
  abort_unless(StaffAccess::allows($r->user(),'email'),403);
  $v=$r->validate(['recipients'=>'required|array|min:1|max:10','recipients.*'=>'required|integer|distinct|exists:users,id','template'=>['required',Rule::in(array_keys(ServiceEmailTemplates::all()))],'details'=>'required|string|min:10|max:3000','confirmed'=>'required|accepted','request_key'=>'required|uuid']);
  DB::transaction(function()use($r,$v){
   User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
   if(DB::table('staff_emails')->where('sender_id',$r->user()->id)->where('request_key',$v['request_key'])->exists())return;
   $count=DB::table('staff_emails')->where('sender_id',$r->user()->id)->where('created_at','>=',now()->subDay())->count();
   if($count+count($v['recipients'])>50)throw \Illuminate\Validation\ValidationException::withMessages(['recipients'=>'Limit: 50 service emails in 24 hours.']);
   $users=User::whereIn('id',$v['recipients'])->whereNull('closed_at')->whereNotNull('email_verified_at')->get();
   if($users->count()!==count($v['recipients']))throw \Illuminate\Validation\ValidationException::withMessages(['recipients'=>'Choose verified accounts that are not closed.']);
   foreach($users as $user){
    $email=ServiceEmailTemplates::render($v['template'],$user->name,$v['details']);
    $id=DB::table('staff_emails')->insertGetId($email+['sender_id'=>$r->user()->id,'recipient_id'=>$user->id,'recipient_email'=>$user->email,'template'=>$v['template'],'request_key'=>$v['request_key'],'status'=>'queued','created_at'=>now(),'updated_at'=>now()]);
    SendStaffEmail::dispatch($id)->afterCommit();
   }
   DB::table('admin_audits')->insert(['actor_id'=>$r->user()->id,'action'=>'support.email_queued','target'=>'email_batch:'.$v['request_key'],'before'=>null,'after'=>json_encode(['template'=>$v['template'],'recipient_ids'=>$v['recipients']]),'created_at'=>now(),'updated_at'=>now()]);
  });return back()->with('success','Individual emails queued. Check the sending history for their status.');
 }
}
