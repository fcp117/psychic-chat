<?php
namespace App\Http\Controllers;
use App\Models\{ChatSession,CoachReview,User};
use App\Services\CoachFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
class CoachReviewController extends Controller {
    public function index(Request $r, CoachFeedback $feedback) {
        $sessions = ChatSession::where('client_id',$r->user()->id)->where('status','completed')->whereNotNull('started_at')
            ->with('counselor:id,name')->orderByDesc('ended_at')->paginate(10);
        $existing = CoachReview::withTrashed()->whereIn('chat_session_id',$sessions->pluck('id'))->get()->keyBy('chat_session_id');
        $sessions->through(fn($s)=>[
            'id'=>$s->id,'coach'=>$s->counselor?->name,'ended_at'=>$s->ended_at?->toIso8601String(),
            'deadline'=>$s->ended_at?->copy()->addHours(config('reviews.window_hours'))->toIso8601String(),
            'eligible'=>$feedback->eligible($s) && !$existing->has($s->id),
            'submitted'=>$existing->has($s->id),
        ]);
        return Inertia::render('Reviews/Index',['sessions'=>$sessions,'windowHours'=>config('reviews.window_hours')]);
    }
    public function store(Request $r, ChatSession $chatSession, CoachFeedback $feedback) {
        abort_unless($chatSession->client_id === $r->user()->id && $chatSession->counselor_id !== $r->user()->id,403);
        $data=$r->validate(['rating'=>'required|integer|min:1|max:5','comment'=>'required|string|min:5|max:2000','publish_consent'=>'required|boolean']);
        DB::transaction(function() use($chatSession,$feedback,$data) {
            $s=ChatSession::whereKey($chatSession->id)->lockForUpdate()->firstOrFail();
            if (!$feedback->eligible($s)) throw ValidationException::withMessages(['feedback'=>'Feedback is available only within '.config('reviews.window_hours').' hours of a completed reading.']);
            if(CoachReview::withTrashed()->where('chat_session_id',$s->id)->exists()) throw ValidationException::withMessages(['feedback'=>'Feedback has already been submitted for this reading.']);
            CoachReview::create($data+['chat_session_id'=>$s->id,'client_id'=>$s->client_id,'counselor_id'=>$s->counselor_id]);
        });
        return back()->with('success','Thank you. Your rating now contributes to the coach’s profile. Your comment is private unless approved for highlighting with your permission.');
    }
    public function coaches(Request $r) {
        $search=mb_substr(trim((string)$r->input('q','')),0,100);
        $coaches=User::where(function($q){$q->where('role','counselor')->orWhereIn('id',CoachReview::select('counselor_id'));})
            ->when($search,fn($q)=>$q->where('name','like','%'.$search.'%'))
            ->select(['id','name','profile_photo_path'])
            ->selectSub(CoachReview::selectRaw('COUNT(*)')->whereColumn('counselor_id','users.id'),'review_count')
            ->selectSub(CoachReview::selectRaw('AVG(rating)')->whereColumn('counselor_id','users.id'),'rating_average')
            ->selectSub(CoachReview::selectRaw('COUNT(*)')->whereColumn('counselor_id','users.id')->whereNull('reviewed_at'),'pending_count')
            ->orderBy('name')->paginate(12)->withQueryString();
        return Inertia::render('Admin/Reviews',['coaches'=>$coaches,'filters'=>['q'=>$search]]);
    }
    public function show(User $coach, CoachFeedback $feedback) {
        return Inertia::render('Admin/Reviews',['coach'=>$coach->only(['id','name']), 'summary'=>$feedback->summary($coach->id),
            'reviews'=>CoachReview::where('counselor_id',$coach->id)->orderByDesc('id')->paginate(5)]);
    }
    public function moderate(Request $r, CoachReview $review) {
        $data=$r->validate(['action'=>'required|in:reviewed,highlight,unhighlight,delete','reason'=>'required|string|min:5|max:500']);
        DB::transaction(function() use($r,$review,$data) {
            $review=CoachReview::whereKey($review->id)->lockForUpdate()->firstOrFail();
            if($data['action']==='highlight' && !$review->publish_consent) throw ValidationException::withMessages(['action'=>'The client did not give permission to publish this comment.']);
            $before=$review->only(['highlighted','reviewed_at']);
            if($data['action']==='delete') $review->delete();
            else {
                if($data['action']==='highlight') $review->highlighted=true;
                if($data['action']==='unhighlight') $review->highlighted=false;
                $review->reviewed_at=now(); $review->save();
            }
            DB::table('admin_audits')->insert(['actor_id'=>$r->user()->id,'action'=>'review.'.$data['action'],'target'=>'review:'.$review->id,
                'before'=>json_encode($before),'after'=>json_encode(['reason'=>$data['reason']]),'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Review updated. Profile ratings now reflect this change.');
    }
}
