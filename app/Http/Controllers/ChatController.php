<?php
namespace App\Http\Controllers;
use App\Models\ChatSession;
use App\Models\User;
use App\Events\MessageSent;
use App\Services\ReadingBilling;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatController extends Controller 
{
    public function __construct(private ReadingBilling $billing) {}

    private function member(Request $request, ChatSession $s): void 
    {
        abort_unless(in_array($request->user()->id, [$s->client_id, $s->counselor_id], true), 403);
    }
    
    public function psychics(Request $request) 
    {
        $search = trim($request->validate(['q'=>['nullable','string','max:100']])['q'] ?? '');

        $prefs = DB::table('counselor_preferences')->where('user_id',$request->user()->id)->get()->keyBy('counselor_id');
        $site = app(\App\Services\CoachSite::class)->settings();

        $psychics = User::where('role','counselor')->whereNotNull('email_verified_at')->where('is_approved',true)->where('is_suspended',false)
            ->when($site['rainbow_only'], fn($q)=>$q->where('id',$site['rainbow_user_id'] ?? 0))
            ->where('id','!=',$request->user()->id)->when($search !== '',fn($q)=>$q->where('name','like','%'.$search.'%'))
            ->orderBy('name')->paginate(12,['id','name','rate_per_hour','profile_photo_path'])->withQueryString();

        $ratings = \App\Models\CoachReview::whereIn('counselor_id', $psychics->getCollection()->pluck('id'))
            ->selectRaw('counselor_id, AVG(rating) as average, COUNT(*) as total')
            ->groupBy('counselor_id')->get()->keyBy('counselor_id');

        $psychics->through(function ($u) use ($prefs, $ratings)
        {
            $rate = $this->billing->rate($u); $p = $prefs->get($u->id);

            return ['id'=>$u->id,'name'=>$u->name,'profile_photo_url'=>$u->profile_photo_url,'rate_per_hour'=>$rate,
                'rating'=>['average'=>round((float) ($ratings->get($u->id)?->average ?? 0), 1), 'count'=>(int) ($ratings->get($u->id)?->total ?? 0)],
                'has_rate_agreement'=>$p && (int)$p->accepted_rate === $rate,
                'show_rate_notice'=>!$p || $p->show_rate_notice || (int)$p->accepted_rate !== $rate];
        });

        return Inertia::render('Chat/FindPsychic',['psychics'=>$psychics,'filters'=>['q'=>$search], 'billing'=>$this->billing->settings()]);
    }

    public function preference(Request $request, User $counselor) 
    {
        abort_unless($request->user()->role === 'user' && $counselor->role === 'counselor',403);

        $data=$request->validate(['show_rate_notice'=>'required|boolean','consent'=>'sometimes|boolean','accepted_rate'=>'sometimes|integer|min:1']);

        $old=DB::table('counselor_preferences')->where('user_id',$request->user()->id)->where('counselor_id',$counselor->id)->first();

        $rate=$this->billing->rate($counselor);

        $consented=($data['consent'] ?? false) && ($data['accepted_rate'] ?? 0) === $rate;

        if (!$data['show_rate_notice'] && !$consented && (!$old || (int)$old->accepted_rate !== $rate)) $this->billing->fail('Agree to the current rate before hiding its notification.');

        DB::table('counselor_preferences')->updateOrInsert(['user_id'=>$request->user()->id,'counselor_id'=>$counselor->id],['show_rate_notice'=>$data['show_rate_notice'],'accepted_rate'=>$consented ? $rate : $old?->accepted_rate,'updated_at'=>now(),'created_at'=>$old?->created_at ?? now()]);

        return back();
    }

    public function start(Request $request, User $counselor) 
    {
        abort_unless(app(\App\Services\CoachSite::class)->allows($counselor->id),403,'New readings are currently available with Coach Rainbow only.');
        abort_unless($request->user()->role === 'user',403);
        $data=$request->validate(['accepted_rate'=>'required|integer|min:1','consent'=>'required|boolean','hide_notice'=>'sometimes|boolean']);
        $this->billing->sweep();
        $session=DB::transaction(function () use ($request,$counselor,$data) {
            $client=User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            app(\App\Services\MinuteWallet::class)->expireLocked($client);
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
            if (app(\App\Services\MinuteWallet::class)->available($client) < 3600) $this->billing->fail('Your balance is too low to start this reading. Add minutes first.');
            if ($data['consent']) DB::table('counselor_preferences')->updateOrInsert(['user_id'=>$client->id,'counselor_id'=>$counselor->id],['accepted_rate'=>$rate,'show_rate_notice'=>!($data['hide_notice'] ?? false),'created_at'=>$p?->created_at ?? now(),'updated_at'=>now()]);
            return ChatSession::create(['client_id'=>$client->id,'counselor_id'=>$counselor->id,'status'=>'pending','billing_version'=>2,'agreed_rate'=>$rate,'agreed_at'=>now(),'disconnect_seconds'=>$this->billing->settings()->disconnect_seconds,'client_seen_at'=>now()]);
        },3);
        $this->billing->notify($session);
        return redirect()->route('chat.room',$session->conversationId());
    }

    public function accept(Request $request, ChatSession $chatSession) 
    {
        abort_unless(app(\App\Services\CoachSite::class)->allows($chatSession->counselor_id),403,'New readings are currently available with Coach Rainbow only.');
        abort_unless($request->user()->id === $chatSession->counselor_id && $request->user()->role==='counselor' && $request->user()->is_approved,403);
        $this->billing->sweep();
        DB::transaction(function () use ($chatSession,$request) {
            $client=User::whereKey($chatSession->client_id)->lockForUpdate()->firstOrFail();
            app(\App\Services\MinuteWallet::class)->expireLocked($client);
            $counselor=User::whereKey($chatSession->counselor_id)->lockForUpdate()->firstOrFail();
            $s=ChatSession::whereKey($chatSession->id)->lockForUpdate()->firstOrFail();
            if ($s->status!=='pending') $this->billing->fail('This request is no longer pending.');
            if ($client->is_suspended || $client->role!=='user' || !$counselor->is_approved || $counselor->is_suspended) $this->billing->fail('This account is not available for readings.');
            if (!$s->client_seen_at || $s->client_seen_at->lt(now()->subSeconds($s->disconnect_seconds))) $this->billing->fail('Wait for the user to return to this reading.');
            if (ChatSession::where('counselor_id',$counselor->id)->where('status','active')->exists()) $this->billing->fail('Finish your active reading first.');
            if (app(\App\Services\MinuteWallet::class)->available($client,$s->booking_id) < ((int)$s->billing_version===2?3600:max($this->billing->settings()->minimum_credits*3600,$s->agreed_rate))) $this->billing->fail('The user has insufficient minutes.');
            $stop=null;
            if($s->booking_id){
                $b=\App\Models\Booking::whereKey($s->booking_id)->lockForUpdate()->firstOrFail();
                if($b->status!=='joining'||now()->lt($b->starts_at)||now()->gt($b->starts_at->copy()->addMinutes(10)))$this->billing->fail('The booking grace period has ended. Contact support.');
                $stop=now()->addMinutes($b->minutes);$b->update(['status'=>'active','coach_joined_at'=>now()]);
            }
            $other=\App\Models\Booking::whereIn('status',['booked','joining','active'])->where(fn($q)=>$q->where('coach_id',$counselor->id)->orWhere('client_id',$client->id))->when($s->booking_id,fn($q)=>$q->where('id','!=',$s->booking_id))->where('ends_at','>',now())->orderBy('starts_at')->first();
            if($other){if($other->starts_at->lte(now()->addMinute()))$this->billing->fail('A reserved appointment is starting. Join it from Bookings.');$stop=$stop?$stop->min($other->starts_at):$other->starts_at;}
            $s->update(['status'=>'active','started_at'=>now(),'client_seen_at'=>now(),'counselor_seen_at'=>now(),'hard_stop_at'=>$stop]);
        },3);
        $this->billing->notify($chatSession->fresh());
        return back();
    }

    public function end(Request $request, ChatSession $chatSession) 
    {
        $this->member($request,$chatSession);
        $reasons = ['finished'=>'Reading completed', 'break'=>'Taking a break', 'time'=>'Need to leave', 'technical'=>'Connection or technical issue', 'not_fit'=>'Not the right fit', 'misconduct'=>'Session ended for inappropriate conduct', 'other'=>'Other'];
        $data=$request->validate(['reason'=>['nullable', \Illuminate\Validation\Rule::in(array_keys($reasons))]]);
        $s=$this->billing->settle($chatSession->id,$request->user()->id,true, reason: isset($data['reason']) ? $reasons[$data['reason']] : null);
        return back();
    }

    private function latest(ChatSession $s): ChatSession { return $s->conversationQuery()->latest('id')->firstOrFail(); }

    private function publicSession(ChatSession $s, Request $request): array 
    {
        $data=$s->load(['client:id,name,profile_photo_path','counselor:id,name,profile_photo_path'])->toArray();
        $data['counselor']['rating'] = app(\App\Services\CoachFeedback::class)->summary($s->counselor_id);
        $data['coach_available'] = app(\App\Services\CoachSite::class)->allows($s->counselor_id);
        $data['timing'] = ['elapsed'=>$s->billed_seconds ?? 0,'remaining'=>$s->agreed_rate ? max(0,(int)floor(app(\App\Services\MinuteWallet::class)->available(User::findOrFail($s->client_id),$s->booking_id) / $s->agreed_rate)) : 0,'sampled_at'=>now()->toIso8601String()];
        if($s->hard_stop_at)$data['timing']['remaining']=min($data['timing']['remaining'],max(0,$s->hard_stop_at->timestamp-now()->timestamp));
        $data['invitation'] = DB::table('reading_invitations')->whereIn('chat_session_id',$s->conversationQuery()->select('id'))->whereNull('confirmed_at')->where('expires_at','>',now())->latest('id')->first(['id','rate','expires_at']);
        if ($data['invitation']) $data['invitation']->expires_at = \Illuminate\Support\Carbon::parse($data['invitation']->expires_at)->toIso8601String();
        if ($request->user()->id === $s->counselor_id) unset($data['billed_units'],$data['billed_seconds']);
        return $data;
    }

    public function heartbeat(Request $request, ChatSession $chatSession) 
    {
        $this->member($request,$chatSession);
        $request->validate(['active'=>'sometimes|boolean','idle_seconds'=>'sometimes|integer|min:0|max:86400']);
        $chatSession->conversationMessages()->where('id','<=',max(0,(int)$request->input('last_message_id',0)))->where('sender_id','!=',$request->user()->id)->whereNull('read_at')->update(['read_at'=>now()]);
        $latest=$this->latest($chatSession);
        // A successful poll confirms connection, even without mouse/keyboard activity.
        $s=$this->billing->settle($latest->id,$request->user()->id,false,true);
        return response()->json(['session'=>$this->publicSession($s,$request),'server_time'=>now()->toIso8601String(),'balance'=>app(\App\Services\MinuteWallet::class)->available($request->user()->fresh(),$s->booking_id)/3600, 'messages'=>$s->conversationMessages()->where('id','>',max(0,(int)$request->input('last_message_id',0)))->with('sender:id,name')->orderBy('id')->limit(200)->get()]);
    }

    public function show(ChatSession $chatSession, Request $request) 
    {
        $this->member($request,$chatSession);
        $s=$this->billing->settle($this->latest($chatSession)->id);
        $sessions = ChatSession::query()
            ->where(fn ($query) => $query->where('client_id', $request->user()->id)->orWhere('counselor_id', $request->user()->id))
            ->whereIn('id', ChatSession::selectRaw('MAX(id)')->groupBy('client_id', 'counselor_id'))
            ->with(['client:id,name', 'counselor:id,name'])
            ->latest('updated_at')->get();
        $sessions->each(function ($item) use ($request) {
            $item->setAttribute('conversation_id', $item->conversationId());
            if ($request->user()->role === 'counselor') $item->makeHidden(['billed_units', 'billed_seconds']);
        });
        return Inertia::render('Chat/Room',['session'=>$this->publicSession($s,$request), 'sessions'=>$sessions, 'conversationId'=>$s->conversationId(), 'initialMessages'=>$s->conversationMessages()->with('sender:id,name')->orderBy('id')->get(), 'currentUser'=>$request->user()->fresh()->setAttribute('spendable_minutes',app(\App\Services\MinuteWallet::class)->available($request->user()->fresh(),$s->booking_id)/3600)]);
    }

    public function agreement(Request $request, ChatSession $chatSession) 
    {
        $this->member($request,$chatSession);
        $counselor=User::findOrFail($chatSession->counselor_id);
        $rate=$this->billing->rate($counselor);
        $pref=DB::table('counselor_preferences')->where('user_id',$request->user()->id)->where('counselor_id',$counselor->id)->first();
        return response()->json(['rate'=>$rate,'show_notice'=>!$pref || $pref->show_rate_notice || (int)$pref->accepted_rate!==$rate,'disconnect_seconds'=>$this->billing->settings()->disconnect_seconds]);
    }

    public function requests(Request $request) 
    {
        return response()->json(['requests'=>ChatSession::where('counselor_id',$request->user()->id)->where('status','pending')->with('client:id,name')->latest('id')->get()->map(fn($s)=>['id'=>$s->id,'conversationId'=>$s->conversationId(),'name'=>$s->client->name])]);
    }

    public function store(Request $request, ChatSession $chatSession) 
    {
        $this->member($request,$chatSession);
        $request->validate(['content'=>'nullable|required_without:attachment|string|max:4000','request_key'=>'required|uuid','attachment'=>'nullable|file|mimes:jpg,jpeg,png,webp,pdf|extensions:jpg,jpeg,png,webp,pdf|max:5120']);
        $request->merge(['content'=>$request->input('content') ?? '']);
        if($request->filled('request_key')) {
            $existing=\App\Models\Message::where('sender_id',$request->user()->id)->where('request_key',$request->request_key)->first();
            if($existing) { abort_unless($existing->chat_session_id===$chatSession->id && $existing->content===$request->content,409); return response()->json(['message'=>$existing->load('sender:id,name')]); }
        }
        $this->billing->settle($chatSession->id,$request->user()->id,false,true);
        $message=DB::transaction(function () use ($chatSession,$request) {
            $client=User::whereKey($chatSession->client_id)->lockForUpdate()->firstOrFail();
            $coach=User::whereKey($chatSession->counselor_id)->lockForUpdate()->firstOrFail();
            abort_if($client->closed_at || $coach->closed_at,403,'This conversation belongs to a closed account.');
            $s=ChatSession::whereKey($chatSession->id)->lockForUpdate()->firstOrFail();
            if($request->filled('request_key')) { $existing=\App\Models\Message::where('sender_id',$request->user()->id)->where('request_key',$request->request_key)->first(); if($existing) {abort_unless($existing->chat_session_id===$s->id && $existing->content===$request->content,409); return $existing;} }
            $file=$request->file('attachment');$attachment=[];
            if($file) {
                $path=$file->store('chat-attachments','local');
                if(!$path) $this->billing->fail('The attachment could not be stored. Please retry.');
                $attachment=['attachment_path'=>$path,'attachment_name'=>mb_substr(preg_replace('/[^\pL\pN ._()-]/u','_',basename($file->getClientOriginalName())),0,180),'attachment_mime'=>$file->getMimeType()];
            }
            $m=$s->messages()->create(['sender_id'=>$request->user()->id,'content'=>$request->content,'request_key'=>$request->input('request_key')]+$attachment);
            $recipient=$request->user()->id===$s->client_id?$s->counselor_id:$s->client_id;
            \App\Services\AppNotifications::send($recipient,'message:'.$m->id,'New private message','Open your conversation to read it.',route('chat.room',$s->conversationId(),false));
            $s->touch();
            return $m;
        });
        $message->load('sender:id,name');
        // A broadcast outage must not make a saved message look unsent.
        try { broadcast(new MessageSent($message))->toOthers(); } catch (\Throwable $e) { report($e); }
        return response()->json(['message'=>$message]);
    }

    public function earnings(Request $request) 
    {
        $this->billing->sweep();
        $query=DB::table('credit_transactions')->where('credit_transactions.counselor_id',$request->user()->id);
        $total=(clone $query)->sum('earning_units');
        $rows=(clone $query)->leftJoin('users','users.id','=','credit_transactions.user_id')
            ->selectRaw('credit_transactions.chat_session_id, users.name as client_name, SUM(earning_units) as earning_units, SUM(minute_earning_units) as minute_units')
            ->groupBy('credit_transactions.chat_session_id','users.name')->orderByDesc('credit_transactions.chat_session_id')->paginate(20);
        return Inertia::render('Earnings',['minuteUnits'=>(clone $query)->sum('minute_earning_units'),'totalUnits'=>$total,'readings'=>$rows]);
    }
}
