<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AppNotifications;
use App\Models\User;
use Inertia\Inertia;
class SupportInboxController extends Controller {
 private function thread(Request $r,int $id) {
  $t=DB::table('support_threads')->find($id);abort_unless($t,404);
  abort_unless($t->user_id===$r->user()->id || $r->user()->role==='admin',403);return $t;
 }
 public function index(Request $r) {
  $v=$r->validate(['thread'=>'nullable|integer','category'=>'nullable|in:technical,credits']);
  $admin=$r->user()->role==='admin';$thread=null;$messages=null;
  if(!empty($v['thread'])) {
   $thread=$this->thread($r,(int)$v['thread']);
   DB::table('support_messages')->where('support_thread_id',$thread->id)->whereNull('read_at')->when($admin,fn($q)=>$q->where('sender_id',$thread->user_id),fn($q)=>$q->where('sender_id','!=',$thread->user_id))->update(['read_at'=>now()]);
   $messages=DB::table('support_messages as m')->join('users as u','u.id','=','m.sender_id')->where('support_thread_id',$thread->id)->select('m.id','m.body','m.created_at','u.name')->latest('m.id')->paginate(30,['*'],'messages_page')->withQueryString();
   $messages->through(function($m){$m->created_at=\Illuminate\Support\Carbon::parse($m->created_at)->toIso8601String();return $m;});
  }
  $threads=DB::table('support_threads as t')->join('users as u','u.id','=','t.user_id')->when(!$admin,fn($q)=>$q->where('t.user_id',$r->user()->id))->when(!empty($v['category']),fn($q)=>$q->where('category',$v['category']))->select('t.*','u.name')
   ->selectSub(function($q)use($admin){$q->from('support_messages as m')->selectRaw('COUNT(*)')->whereColumn('m.support_thread_id','t.id')->whereNull('read_at');if($admin)$q->whereColumn('m.sender_id','t.user_id');else $q->whereColumn('m.sender_id','!=','t.user_id');},'unread')
   ->latest('t.updated_at')->paginate(12,['*'],'threads_page')->withQueryString();
  return Inertia::render('Support/Inbox',['threads'=>$threads,'thread'=>$thread,'messages'=>$messages,'category'=>$v['category']??'']);
 }
 public function send(Request $r) {
  $v=$r->validate(['thread'=>'nullable|integer','category'=>'required|in:technical,credits','body'=>'required|string|min:1|max:4000','request_key'=>'required|uuid']);
  abort_if($r->user()->role==='admin' && empty($v['thread']),422,'Select a support conversation to reply.');
  $id=DB::transaction(function()use($r,$v){
   User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
   if(!empty($v['thread'])) $t=$this->thread($r,(int)$v['thread']);
   else {
    DB::table('support_threads')->insertOrIgnore(['user_id'=>$r->user()->id,'category'=>$v['category'],'created_at'=>now(),'updated_at'=>now()]);
    $t=DB::table('support_threads')->where('user_id',$r->user()->id)->where('category',$v['category'])->first();
   }
   $existing=DB::table('support_messages')->where('request_key',$v['request_key'])->first();
   if($existing){abort_unless($existing->sender_id===$r->user()->id && $existing->support_thread_id===$t->id && $existing->body===$v['body'],409);return $t->id;}
   $message=DB::table('support_messages')->insertGetId(['support_thread_id'=>$t->id,'sender_id'=>$r->user()->id,'body'=>$v['body'],'request_key'=>$v['request_key'],'created_at'=>now(),'updated_at'=>now()]);
   DB::table('support_threads')->where('id',$t->id)->update(['updated_at'=>now()]);
   $recipients=$r->user()->role==='admin'?[$t->user_id]:User::where('role','admin')->where('is_suspended',false)->pluck('id');
   foreach($recipients as $recipient)AppNotifications::send($recipient,'support:'.$message,'New '.$t->category.' support message','Open your private support conversation to read it.',route('support.index',['thread'=>$t->id],false));
   return $t->id;
  });
  return redirect()->route('support.index',['thread'=>$id]);
 }
}
