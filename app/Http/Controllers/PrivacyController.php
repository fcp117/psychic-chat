<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\{TranscriptPrivacy,AppNotifications};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
class PrivacyController extends Controller {
 public function index(Request $r){return Inertia::render('Privacy/Requests',['requests'=>DB::table('privacy_requests as p')->join('users as u','u.id','=','p.user_id')->select('p.*','u.name')->when($r->user()->role!=='admin',fn($q)=>$q->where('p.user_id',$r->user()->id))->orderByRaw("CASE WHEN p.status='pending' THEN 0 ELSE 1 END")->orderByDesc('p.id')->paginate(10),'notice'=>DB::table('transcript_notices')->where('client_id',$r->user()->id)->first()]);}
 public function store(Request $r){
  $v=$r->validate(['kind'=>'required|in:transcripts,account','chat_session_id'=>'nullable|integer|exists:chat_sessions,id','reason'=>'required|string|min:10|max:1000','consent'=>'required|accepted','password'=>'required_if:kind,account|nullable|current_password']);
  abort_if($v['kind']==='account' && $r->user()->role==='admin',422,'Administrator closure requires a separate ownership transfer.');
  DB::transaction(function()use($r,$v){$u=User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();if(DB::table('privacy_requests')->where('user_id',$u->id)->where('status','pending')->exists())throw \Illuminate\Validation\ValidationException::withMessages(['request'=>'You already have a pending request.']);
   $query=app(TranscriptPrivacy::class)->messages($u,$v['chat_session_id']??null);$through=(int)$query->max('id');
   $id=DB::table('privacy_requests')->insertGetId(['user_id'=>$u->id,'kind'=>$v['kind'],'chat_session_id'=>$v['kind']==='account'?null:($v['chat_session_id']??null),'through_message_id'=>$through,'reason'=>$v['reason'],'created_at'=>now(),'updated_at'=>now()]);
   foreach(User::where('role','admin')->where('is_suspended',false)->pluck('id') as $admin)AppNotifications::send($admin,'privacy:'.$id,'Privacy request','A member requested '.($v['kind']==='account'?'account closure':'transcript deletion').'.',route('privacy.requests',[],false));
  });return back()->with('success','Request sent for admin review. Nothing has been deleted yet.');
 }
 public function review(Request $r,int $privacyRequest){$v=$r->validate(['approve'=>'required|boolean','reason'=>'required|string|min:10|max:1000','confirm'=>'required|accepted']);app(TranscriptPrivacy::class)->review($r->user(),$privacyRequest,$v['approve'],$v['reason']);return back()->with('success','Decision recorded.');}
}
