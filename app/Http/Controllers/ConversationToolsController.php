<?php
namespace App\Http\Controllers;
use App\Models\{ChatSession,Message,User};
use App\Services\{CoachSite,ReadingBilling,AppNotifications};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
class ConversationToolsController extends Controller {
 public function member(Request $r,ChatSession $s): void {abort_unless(in_array($r->user()->id,[$s->client_id,$s->counselor_id],true),403);}
 public function open(Request $r,User $counselor) {
  abort_unless($r->user()->role==='user' && app(CoachSite::class)->allows($counselor->id) && app(CoachSite::class)->eligible()->whereKey($counselor->id)->exists(),403);
  $s=DB::transaction(function() use($r,$counselor){
   User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
   return ChatSession::where('client_id',$r->user()->id)->where('counselor_id',$counselor->id)->oldest('id')->first()
    ?? ChatSession::create(['client_id'=>$r->user()->id,'counselor_id'=>$counselor->id,'status'=>'rejected','end_reason'=>'free_conversation']);
  });
  return redirect()->route('chat.room',$s->conversationId());
 }
 public function notes(Request $r,ChatSession $s) {
  abort_unless($r->user()->id===$s->counselor_id && $r->user()->role==='counselor',403);
  if($r->isMethod('put')) {
   $v=$r->validate(['body'=>'nullable|string|max:20000']);
   DB::table('coach_notes')->updateOrInsert(['client_id'=>$s->client_id,'counselor_id'=>$s->counselor_id],['body'=>$v['body']??'','updated_at'=>now(),'created_at'=>now()]);
  }
  return response()->json(['body'=>DB::table('coach_notes')->where('client_id',$s->client_id)->where('counselor_id',$s->counselor_id)->value('body')??'']);
 }
 public function transcript(Request $r,ChatSession $s) {
  $this->member($r,$s);
  $lines=['Intuition Island — private conversation','Times shown in UTC. Keep this copy private.',''];
  foreach($s->conversationMessages()->with('sender:id,name')->orderBy('id')->cursor() as $m) {
   $lines[]=$m->created_at->utc()->format('Y-m-d H:i:s').' UTC | '.($m->kind==='system'?'Intuition Island':($m->sender?->name??'Former member')).': '.$m->content.($m->attachment_name ? ' [Attachment: '.$m->attachment_name.']':'');
  }
  $text=implode("\n",$lines);
  if($r->boolean('download')) return response($text,200,['Content-Type'=>'text/plain; charset=UTF-8','Content-Disposition'=>'attachment; filename="conversation-'.$s->conversationId().'.txt"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
  return response()->json(['text'=>$text])->header('Cache-Control','private, no-store');
 }
 public function attachment(Request $r,Message $message) {
  $this->member($r,$message->chatSession);abort_unless($message->attachment_path,404);
  return Storage::disk('local')->download($message->attachment_path,$message->attachment_name,['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
 }
 public function invite(Request $r,ChatSession $s) {
  abort_unless($r->user()->id===$s->counselor_id && app(CoachSite::class)->allows($s->counselor_id) && app(CoachSite::class)->eligible()->whereKey($s->counselor_id)->exists(),403);
  $invite=DB::transaction(function() use($s){
   User::whereKey($s->client_id)->lockForUpdate()->firstOrFail();
   abort_if($s->conversationQuery()->whereIn('status',['pending','active'])->exists(),409,'A reading is already open.');
   DB::table('reading_invitations')->whereIn('chat_session_id',$s->conversationQuery()->select('id'))->whereNull('confirmed_at')->update(['expires_at'=>now()]);
   return DB::table('reading_invitations')->insertGetId(['chat_session_id'=>$s->id,'rate'=>app(ReadingBilling::class)->rate(User::findOrFail($s->counselor_id)),'expires_at'=>now()->addHours(24),'created_at'=>now(),'updated_at'=>now()]);
  });
  AppNotifications::send($s->client_id,'invitation:'.$invite,'Reading invitation','Your coach invited you to review and confirm a paid reading.',route('chat.room',$s->conversationId(),false));
  return response()->json(['sent'=>true]);
 }
 public function confirm(Request $r,int $invitation) {
  $r->validate(['consent'=>'required|accepted']);
  return DB::transaction(function() use($r,$invitation){
   User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
   $i=DB::table('reading_invitations')->where('id',$invitation)->lockForUpdate()->first();abort_unless($i,404);
   $s=ChatSession::findOrFail($i->chat_session_id);abort_unless($r->user()->id===$s->client_id,403);
   if($i->confirmed_at || now()->gte($i->expires_at)) throw \Illuminate\Validation\ValidationException::withMessages(['invitation'=>'This invitation has already been confirmed or expired. Ask your coach for a new one.']);
   $r->merge(['accepted_rate'=>(int)$i->rate,'consent'=>true,'hide_notice'=>false]);
   $result=app(ChatController::class)->start($r,User::findOrFail($s->counselor_id));
   DB::table('reading_invitations')->where('id',$i->id)->update(['confirmed_at'=>now(),'updated_at'=>now()]);
   return $result;
  });
 }
}
