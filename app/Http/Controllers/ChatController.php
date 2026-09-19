<?php
namespace App\Http\Controllers;
use App\Models\ChatSession;
use App\Models\User;
use App\Events\MessageSent;
use App\Services\ReadingBilling;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatController extends Controller {
    public function __construct(private ReadingBilling $billing) {}
    private function member(Request $request, ChatSession $s): void {
        abort_unless(in_array($request->user()->id, [$s->client_id, $s->counselor_id], true), 403);
    }
    public function psychics(Request $request) {
        $search = trim($request->validate(['q'=>['nullable','string','max:100']])['q'] ?? '');
        $prefs = DB::table('counselor_preferences')->where('user_id',$request->user()->id)->get()->keyBy('counselor_id');
        $psychics = User::where('role','counselor')->where('is_approved',true)->where('is_suspended',false)
            ->where('id','!=',$request->user()->id)->when($search !== '',fn($q)=>$q->where('name','like','%'.$search.'%'))
            ->orderBy('name')->paginate(12,['id','name','rate_per_hour'])->withQueryString();
        $psychics->through(function ($u) use ($prefs) {
            $rate = $this->billing->rate($u); $p = $prefs->get($u->id);
            return ['id'=>$u->id,'name'=>$u->name,'rate_per_hour'=>$rate,
                'has_rate_agreement'=>$p && (int)$p->accepted_rate === $rate,
                'show_rate_notice'=>!$p || $p->show_rate_notice || (int)$p->accepted_rate !== $rate];
        });
        return Inertia::render('Chat/FindPsychic',['psychics'=>$psychics,'filters'=>['q'=>$search], 'billing'=>$this->billing->settings()]);
    }
    public function preference(Request $request, User $counselor) {
        abort_unless($request->user()->role === 'user' && $counselor->role === 'counselor',403);
        $data=$request->validate(['show_rate_notice'=>'required|boolean','consent'=>'sometimes|boolean','accepted_rate'=>'sometimes|integer|min:1']);
        $old=DB::table('counselor_preferences')->where('user_id',$request->user()->id)->where('counselor_id',$counselor->id)->first();
        $rate=$this->billing->rate($counselor);
        $consented=($data['consent'] ?? false) && ($data['accepted_rate'] ?? 0) === $rate;
        if (!$data['show_rate_notice'] && !$consented && (!$old || (int)$old->accepted_rate !== $rate)) $this->billing->fail('Agree to the current rate before hiding its notification.');
        DB::table('counselor_preferences')->updateOrInsert(['user_id'=>$request->user()->id,'counselor_id'=>$counselor->id],['show_rate_notice'=>$data['show_rate_notice'],'accepted_rate'=>$consented ? $rate : $old?->accepted_rate,'updated_at'=>now(),'created_at'=>$old?->created_at ?? now()]);
        return back();
    }
    public function start(Request $request, User $counselor) {
        abort_unless($request->user()->role === 'user',403);
        $data=$request->validate(['accepted_rate'=>'required|integer|min:1','consent'=>'required|boolean','hide_notice'=>'sometimes|boolean']);
        $this->billing->sweep();
        $session=DB::transaction(function () use ($request,$counselor,$data) {
            $client=User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $counselor=User::whereKey($counselor->id)->lockForUpdate()->firstOrFail();
            abort_unless($counselor->role==='counselor' && $counselor->is_approved && !$counselor->is_suspended && $client->id !== $counselor->id,404);
            $rate=$this->billing->rate($counselor);
            if ($data['accepted_rate'] !== $rate) $this->billing->fail('The rate changed. Refresh and agree to the new rate.');
            $p=DB::table('counselor_preferences')->where('user_id',$client->id)->where('counselor_id',$counselor->id)->first();
            if (!$data['consent'] && (!$p || $p->show_rate_notice || (int)$p->accepted_rate !== $rate)) $this->billing->fail('Please agree to the hourly rate first.');
            $existing=ChatSession::where('client_id',$client->id)->whereIn('status',['pending','active'])->first();
            if ($existing) {
                if ($existing->counselor_id===$counselor->id) return $existing;
                $this->billing->fail('End or cancel your current reading before starting another.');
            }
            if ($client->credit_units < max($this->billing->settings()->minimum_credits*3600, $rate)) $this->billing->fail('Your balance is too low to start this reading. Add credits first.');
            if ($data['consent']) DB::table('counselor_preferences')->updateOrInsert(['user_id'=>$client->id,'counselor_id'=>$counselor->id],['accepted_rate'=>$rate,'show_rate_notice'=>!($data['hide_notice'] ?? false),'created_at'=>$p?->created_at ?? now(),'updated_at'=>now()]);
            return ChatSession::create(['client_id'=>$client->id,'counselor_id'=>$counselor->id,'status'=>'pending','agreed_rate'=>$rate,'agreed_at'=>now(),'disconnect_seconds'=>$this->billing->settings()->disconnect_seconds,'client_seen_at'=>now()]);
        },3);
        $this->billing->notify($session);
        return redirect()->route('chat.room',$session->conversationId());
    }
    public function accept(Request $request, ChatSession $chatSession) {
        abort_unless($request->user()->id === $chatSession->counselor_id && $request->user()->role==='counselor' && $request->user()->is_approved,403);
        $this->billing->sweep();
        DB::transaction(function () use ($chatSession,$request) {
            $client=User::whereKey($chatSession->client_id)->lockForUpdate()->firstOrFail();
            $counselor=User::whereKey($chatSession->counselor_id)->lockForUpdate()->firstOrFail();
            $s=ChatSession::whereKey($chatSession->id)->lockForUpdate()->firstOrFail();
            if ($s->status!=='pending') $this->billing->fail('This request is no longer pending.');
            if ($client->is_suspended || $client->role!=='user' || !$counselor->is_approved || $counselor->is_suspended) $this->billing->fail('This account is not available for readings.');
            if (!$s->client_seen_at || $s->client_seen_at->lt(now()->subSeconds($s->disconnect_seconds))) $this->billing->fail('Wait for the user to return to this reading.');
            if (ChatSession::where('counselor_id',$counselor->id)->where('status','active')->exists()) $this->billing->fail('Finish your active reading first.');
            if ($client->credit_units < max($this->billing->settings()->minimum_credits*3600,$s->agreed_rate)) $this->billing->fail('The user has insufficient credits.');
            $s->update(['status'=>'active','started_at'=>now(),'client_seen_at'=>now(),'counselor_seen_at'=>now()]);
        },3);
        $this->billing->notify($chatSession->fresh());
        return back();
    }
    public function end(Request $request, ChatSession $chatSession) {
        $this->member($request,$chatSession);
        $s=$this->billing->settle($chatSession->id,$request->user()->id,true);
        return back();
    }
    private function latest(ChatSession $s): ChatSession { return $s->conversationQuery()->latest('id')->firstOrFail(); }
    private function publicSession(ChatSession $s, Request $request): array {
        $data=$s->load(['client:id,name','counselor:id,name'])->toArray();
        if ($request->user()->id === $s->counselor_id) unset($data['billed_units'],$data['billed_seconds']);
        return $data;
    }
    public function heartbeat(Request $request, ChatSession $chatSession) {
        $this->member($request,$chatSession);
        $request->validate(['active'=>'sometimes|boolean','idle_seconds'=>'sometimes|integer|min:0|max:86400']);
        $latest=$this->latest($chatSession);
        $s=$this->billing->settle($latest->id,$request->user()->id,false,$request->boolean('active',true),(int)$request->input('idle_seconds',0));
        return response()->json(['session'=>$this->publicSession($s,$request),'server_time'=>now()->toIso8601String(),'balance'=>$request->user()->fresh()->available_credits, 'messages'=>$s->conversationMessages()->where('id','>',max(0,(int)$request->input('last_message_id',0)))->with('sender:id,name')->orderBy('id')->limit(200)->get()]);
    }
    public function show(ChatSession $chatSession, Request $request) {
        $this->member($request,$chatSession);
        $s=$this->billing->settle($this->latest($chatSession)->id);
        return Inertia::render('Chat/Room',['session'=>$this->publicSession($s,$request), 'conversationId'=>$s->conversationId(), 'initialMessages'=>$s->conversationMessages()->with('sender:id,name')->orderBy('id')->get(), 'currentUser'=>$request->user()->fresh()]);
    }
    public function agreement(Request $request, ChatSession $chatSession) {
        $this->member($request,$chatSession);
        $counselor=User::findOrFail($chatSession->counselor_id);
        $rate=$this->billing->rate($counselor);
        $pref=DB::table('counselor_preferences')->where('user_id',$request->user()->id)->where('counselor_id',$counselor->id)->first();
        return response()->json(['rate'=>$rate,'show_notice'=>!$pref || $pref->show_rate_notice || (int)$pref->accepted_rate!==$rate,'disconnect_seconds'=>$this->billing->settings()->disconnect_seconds]);
    }
    public function requests(Request $request) {
        return response()->json(['requests'=>ChatSession::where('counselor_id',$request->user()->id)->where('status','pending')->with('client:id,name')->latest('id')->get()->map(fn($s)=>['id'=>$s->id,'conversationId'=>$s->conversationId(),'name'=>$s->client->name])]);
    }
    public function store(Request $request, ChatSession $chatSession) {
        $this->member($request,$chatSession);
        $request->validate(['content'=>'required|string|max:4000']);
        $this->billing->settle($chatSession->id,$request->user()->id,false,true);
        $message=DB::transaction(function () use ($chatSession,$request) {
            $s=ChatSession::whereKey($chatSession->id)->lockForUpdate()->firstOrFail();
            if ($s->status!=='active') $this->billing->fail('Messages are available only during an accepted, active reading.');
            return $s->messages()->create(['sender_id'=>$request->user()->id,'content'=>$request->content]);
        });
        $message->load('sender:id,name');
        // A broadcast outage must not make a saved message look unsent.
        try { broadcast(new MessageSent($message))->toOthers(); } catch (\Throwable $e) { report($e); }
        return response()->json(['message'=>$message]);
    }
    public function earnings(Request $request) {
        $this->billing->sweep();
        $query=DB::table('credit_transactions')->where('credit_transactions.counselor_id',$request->user()->id);
        $total=(clone $query)->sum('earning_units');
        $rows=(clone $query)->leftJoin('users','users.id','=','credit_transactions.user_id')
            ->selectRaw('credit_transactions.chat_session_id, users.name as client_name, SUM(earning_units) as earning_units')
            ->groupBy('credit_transactions.chat_session_id','users.name')->orderByDesc('credit_transactions.chat_session_id')->paginate(20);
        return Inertia::render('Earnings',['totalUnits'=>$total,'readings'=>$rows]);
    }
}
