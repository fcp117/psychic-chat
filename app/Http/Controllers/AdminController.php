<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Models\ChatSession;
use App\Services\ReadingBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminController extends Controller {
    public function __construct(private ReadingBilling $billing) {}
    private function audit(Request $r, string $action, string $target, $before, $after): void {
        DB::table('admin_audits')->insert(['actor_id'=>$r->user()->id,'action'=>$action,'target'=>$target,'before'=>json_encode($before),'after'=>json_encode($after),'created_at'=>now(),'updated_at'=>now()]);
    }
    public function index(Request $request) {
        $this->billing->sweep();
        $v=$request->validate(['q'=>'nullable|string|max:100','verification'=>['nullable',Rule::in(['all','verified','unverified'])],'section'=>['nullable',Rule::in(['users','pricing','transactions','sessions','forecasts','audit','applications','health'])]]);
        $verification=$v['verification'] ?? 'all'; $q=trim($v['q'] ?? ''); $section=$v['section'] ?? 'users';
        $data=['section'=>$section,'filters'=>['q'=>$q,'verification'=>$verification],'settings'=>$this->billing->settings()];
        if ($section==='applications') $data['applications']=DB::table('counselor_applications as a')->join('users as u','u.id','=','a.user_id')->select('a.*','u.name','u.email')->orderByRaw("CASE WHEN a.status='pending' THEN 0 ELSE 1 END")->orderByDesc('a.updated_at')->paginate(15)->withQueryString();
        if ($section==='health') $data['incidents']=DB::table('system_incidents')->orderByDesc('created_at')->paginate(30)->withQueryString();
        if ($section==='pricing') { $data['shop']=app(\App\Services\CreditShop::class)->settings(); $data['packages']=DB::table('credit_packages')->orderBy('id')->get(); $data['paymentMethods']=app(\App\Services\PaymentGateway::class)->methods(); }
        if ($section==='users') $data['users']=User::when($verification==='verified',fn($query)=>$query->whereNotNull('email_verified_at'))->when($verification==='unverified',fn($query)=>$query->whereNull('email_verified_at'))->when($q!=='',fn($query)=>$query->where(fn($sub)=>$sub->where('name','like','%'.$q.'%')->orWhere('email','like','%'.$q.'%')))->orderBy('id')->paginate(15,['id','name','email','email_verified_at','birthdate','created_at','role','credit_units','available_credits','rate_per_hour','is_approved','is_suspended'])->withQueryString();
        if ($section==='users') {
            $eligible=app(\App\Services\UnverifiedAccountCleanup::class)->eligible()->whereIn('id',$data['users']->pluck('id'))->pluck('id')->all();
            $data['users']->through(function($u)use($eligible){
                $u->makeVisible('birthdate');
                $u->setAttribute('age',$u->birthdate ? (int)$u->birthdate->diffInYears(now('Asia/Manila')->startOfDay()) : null);
                $u->setAttribute('cleanup_eligible',in_array($u->id,$eligible,true));
                return $u;
            });
        }
        if ($section==='transactions') $data['transactions']=DB::table('credit_transactions as t')->leftJoin('users as u','u.id','=','t.user_id')->leftJoin('users as a','a.id','=','t.actor_id')->select('t.*','u.name as user_name','a.name as actor_name')->orderByDesc('t.id')->paginate(30)->withQueryString();
        if ($section==='sessions') $data['sessions']=ChatSession::with(['client:id,name','counselor:id,name'])->latest('id')->paginate(20)->withQueryString();
        if ($section==='forecasts') $data['forecasts']=DB::table('forecasts')->orderByDesc('id')->paginate(15)->withQueryString();
        if ($section==='audit') $data['audits']=DB::table('admin_audits as a')->leftJoin('users as u','u.id','=','a.actor_id')->select('a.*','u.name as actor_name')->orderByDesc('a.id')->paginate(30)->withQueryString();
        return Inertia::render('Admin/Settings',$data);
    }
    public function shop(Request $r) {
        $v=$r->validate(['custom_enabled'=>'required|boolean','price'=>'required|decimal:0,2|min:0.01|max:100000','minimum'=>'required|decimal:0,2|min:1|max:100000']);
        $data=['custom_enabled'=>$v['custom_enabled'],'price_per_credit'=>\App\Services\CreditShop::centavos($v['price']),'minimum_amount'=>\App\Services\CreditShop::centavos($v['minimum'])];
        DB::transaction(function() use($r,$data) { $old=DB::table('credit_shop_settings')->where('id',1)->lockForUpdate()->first(); DB::table('credit_shop_settings')->where('id',1)->update($data+['updated_at'=>now()]); $this->audit($r,'shop.updated','credit_shop_settings:1',$old,$data); });
        return back()->with('success','Credit purchase pricing saved.');
    }
    public function package(Request $r) {
        $v=$r->validate(['id'=>'nullable|integer|exists:credit_packages,id','name'=>'required|string|max:100','credits'=>'required|integer|min:1|max:100000','price'=>'required|decimal:0,2|min:1|max:100000','active'=>'required|boolean']);
        DB::transaction(function() use($r,$v) {
            $id=$v['id']??null; $old=$id?DB::table('credit_packages')->where('id',$id)->lockForUpdate()->first():null;
            $data=['name'=>$v['name'],'credits'=>$v['credits'],'amount'=>\App\Services\CreditShop::centavos($v['price']),'active'=>$v['active'],'updated_at'=>now()];
            if($id) DB::table('credit_packages')->where('id',$id)->update($data); else $id=DB::table('credit_packages')->insertGetId($data+['created_at'=>now()]);
            $this->audit($r,'package.saved','credit_package:'.$id,$old,$data);
        });
        return back()->with('success','Credit package saved. Active packages appear on the Credits page.');
    }
    public function pricing(Request $r) {
        $v=$r->validate(['default_rate'=>'required|integer|min:1|max:100000','minimum_credits'=>'required|integer|min:1|max:1000000','disconnect_seconds'=>'required|integer|min:20|max:120']);
        DB::transaction(function () use ($r,$v) {
            $old=DB::table('billing_settings')->where('id',1)->lockForUpdate()->first();
            DB::table('billing_settings')->where('id',1)->update($v+['updated_at'=>now()]);
            $this->audit($r,'pricing.updated','billing_settings:1',$old,$v);
        });
        return back()->with('success','Pricing saved. Existing requests and readings keep their agreed rates.');
    }
    public function user(Request $r, User $user) {
        $v=$r->validate(['role'=>['required',Rule::in(['user','counselor','admin'])],'is_approved'=>'required|boolean','is_suspended'=>'required|boolean','rate_per_hour'=>'nullable|integer|min:1|max:100000']);
        DB::transaction(function () use ($r,$user,$v) {
            $u=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $old=$u->only(array_keys($v));
            if ($u->id===$r->user()->id && ($v['role']!=='admin' || $v['is_suspended'])) $this->billing->fail('You cannot remove your own admin access or suspend yourself.');
            if ($u->role==='admin' && ($v['role']!=='admin' || $v['is_suspended']) && User::where('role','admin')->where('is_suspended',false)->count()<=1) $this->billing->fail('At least one active admin must remain.');
            if (($v['role']!==$u->role || $v['is_suspended'] || !$v['is_approved'] && $u->role==='counselor') && ChatSession::where(fn($q)=>$q->where('client_id',$u->id)->orWhere('counselor_id',$u->id))->whereIn('status',['pending','active'])->exists()) $this->billing->fail('End this account’s open readings in Chat Sessions first.');
            $u->role=$v['role']; $u->is_suspended=$v['is_suspended'];
            $u->is_approved=$v['role']==='counselor' && $v['is_approved'];
            $u->rate_per_hour=$v['role']==='counselor' ? $v['rate_per_hour'] : null;
            $u->save();
            $this->audit($r,'account.updated','user:'.$u->id,$old,$u->only(array_keys($v)));
        });
        return back()->with('success','Account updated.');
    }
    public function credits(Request $r, User $user) {
        $v=$r->validate(['amount'=>'required|integer|min:1|max:1000000','kind'=>['required',Rule::in(['add','remove','refund'])],'reason'=>'required|string|min:5|max:500','session_id'=>'nullable|integer','full_refund'=>'sometimes|boolean']);
        $this->billing->sweep();
        DB::transaction(function () use ($r,$user,$v) {
            $u=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $before=$u->credit_units; $amount=$v['amount']*3600; $s=null; $earning=0;
            if ($v['kind']==='remove') {
                if (ChatSession::where('client_id',$u->id)->whereIn('status',['pending','active'])->exists()) $this->billing->fail('End open readings before removing credits.');
                $amount=-$amount;
            }
            if ($v['kind']==='refund') {
                $s=ChatSession::whereKey($v['session_id'] ?? 0)->where('client_id',$u->id)->where('status','completed')->lockForUpdate()->first();
                if (!$s) $this->billing->fail('Choose a completed reading belonging to this user.');
                $net=(int) DB::table('credit_transactions')->where('chat_session_id',$s->id)->sum('earning_units');
                if ($v['full_refund'] ?? false) $amount=$net;
                if ($amount<=0) $this->billing->fail('No charged credits remain to refund.');
                if ($amount>$net) $this->billing->fail('Refund cannot exceed the reading’s remaining charged credits.');
                $earning=-$amount;
            }
            $this->billing->record($u,$amount,$v['kind']==='refund'?'refund':'admin_adjustment',$v['reason'],$r->user()->id,$s,$earning);
            $this->audit($r,'credits.'.$v['kind'],'user:'.$u->id,['credit_units'=>$before],['credit_units'=>$u->credit_units,'reason'=>$v['reason'],'session_id'=>$s?->id]);
        });
        return back()->with('success','Credit transaction recorded.');
    }
    public function end(Request $r, ChatSession $chatSession) {
        $s=$this->billing->settle($chatSession->id,null,true);
        DB::transaction(fn()=> $this->audit($r,'reading.ended','session:'.$s->id,null,['status'=>$s->status,'billed_units'=>$s->billed_units]));
        return back()->with('success','Reading ended.');
    }
    public function deleteForecast(Request $r, int $forecast) {
        DB::transaction(function () use ($r,$forecast) {
            $old=DB::table('forecasts')->where('id',$forecast)->lockForUpdate()->first();
            abort_unless($old,404);
            DB::table('forecasts')->where('id',$forecast)->delete();
            $this->audit($r,'forecast.deleted','forecast:'.$forecast,$old,null);
        });
        return redirect()->route('admin.settings',['section'=>'forecasts'])->with('success','Forecast deleted.');
    }
    public function forecast(Request $r) {
        $v=$r->validate(['id'=>'nullable|integer|exists:forecasts,id','title'=>'required|string|max:200','body'=>'required|string|max:20000','published_at'=>'nullable|date']);
        DB::transaction(function () use ($r,$v) {
            $id=$v['id'] ?? null; unset($v['id']);
            $v['published_at']=empty($v['published_at'])?null:\Illuminate\Support\Carbon::parse($v['published_at'])->utc()->format('Y-m-d H:i:s');
            $old=$id?DB::table('forecasts')->find($id):null;
            if ($id) DB::table('forecasts')->where('id',$id)->update($v+['updated_at'=>now()]);
            else $id=DB::table('forecasts')->insertGetId($v+['created_at'=>now(),'updated_at'=>now()]);
            $this->audit($r,'forecast.saved','forecast:'.$id,$old,$v);
        });
        return back()->with('success','Forecast saved. Blank publication time keeps it as a draft.');
    }
}
