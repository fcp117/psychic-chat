<?php
namespace App\Http\Controllers;
use App\Models\{ChatSession,User};
use App\Services\ReadingBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
class ReportController extends Controller {
    public function store(Request $r, ChatSession $chatSession) {
        abort_unless(in_array($r->user()->id,[$chatSession->client_id,$chatSession->counselor_id],true),403);
        $v=$r->validate(['reason'=>['required',Rule::in(['harassment','inappropriate','scam','safety','other'])],'details'=>'required|string|min:10|max:2000']);
        $target=User::findOrFail($r->user()->id===$chatSession->client_id ? $chatSession->counselor_id : $chatSession->client_id);
        DB::table('user_reports')->insert($v+['reporter_id'=>$r->user()->id,'reported_id'=>$target->id,'chat_session_id'=>$chatSession->id,'reported_ip'=>$target->last_seen_ip,'status'=>'open','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Report submitted for administrator review.');
    }
    public function index(Request $r) {
        $v=$r->validate(['role'=>['nullable',Rule::in(['all','user','counselor'])]]); $role=$v['role']??'all';
        $reports=DB::table('user_reports as r')->join('users as u','u.id','=','r.reported_id')->join('users as reporter','reporter.id','=','r.reporter_id')
            ->when($role!=='all',fn($q)=>$q->where('u.role',$role))->select('r.*','u.name as reported_name','u.role','u.is_suspended','reporter.name as reporter_name')
            ->orderByRaw("CASE WHEN r.status='open' THEN 0 ELSE 1 END")->orderByDesc('r.id')->paginate(15)->withQueryString();
        return Inertia::render('Admin/Reports',['reports'=>$reports,'filter'=>$role,'blocks'=>DB::table('blocked_ips')->orderByDesc('id')->get()]);
    }
    public function review(Request $r, int $report) {
        $v=$r->validate(['action'=>['required',Rule::in(['resolve','dismiss','suspend','restore','block_ip'])],'note'=>'required|string|min:5|max:1000','confirm_ip'=>'accepted_if:action,block_ip']);
        DB::transaction(function() use($r,$report,$v) {
            $entry=DB::table('user_reports')->where('id',$report)->lockForUpdate()->first(); abort_unless($entry,404);
            $user=User::whereKey($entry->reported_id)->lockForUpdate()->firstOrFail();
            $wasSuspended=$user->is_suspended;
            if(in_array($v['action'],['suspend','restore','block_ip'])) abort_if($user->id===$r->user()->id || $user->role==='admin',403);
            if($v['action']==='suspend') {
                ChatSession::where(fn($q)=>$q->where('client_id',$user->id)->orWhere('counselor_id',$user->id))->whereIn('status',['active','pending'])->pluck('id')->each(fn($id)=>app(ReadingBilling::class)->settle($id,$r->user()->id,true));
                $user->forceFill(['is_suspended'=>true])->save();
            }
            if($v['action']==='restore') $user->forceFill(['is_suspended'=>false])->save();
            if($v['action']==='block_ip') {
                $ip=$entry->reported_ip;
                abort_unless(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE) && $ip!==$r->ip(),422,'No safe public IP is available.');
                abort_if(User::where('role','admin')->where('last_seen_ip',$ip)->exists(),422,'This address is also used by an administrator.');
                DB::table('blocked_ips')->updateOrInsert(['ip'=>$ip],['report_id'=>$report,'blocked_by'=>$r->user()->id,'reason'=>$v['note'],'created_at'=>now(),'updated_at'=>now()]);
            }
            DB::table('user_reports')->where('id',$report)->update(['status'=>$v['action']==='dismiss'?'dismissed':'reviewed','review_note'=>$v['note'],'reviewer_id'=>$r->user()->id,'updated_at'=>now()]);
            DB::table('admin_audits')->insert(['actor_id'=>$r->user()->id,'action'=>'report.'.$v['action'],'target'=>'report:'.$report,'before'=>json_encode(['suspended'=>$wasSuspended,'status'=>$entry->status]),'after'=>json_encode(['note'=>$v['note'],'reported_id'=>$user->id,'suspended'=>$user->is_suspended]),'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Moderation action saved.');
    }
    public function unblock(Request $r,int $block) {
        DB::transaction(function() use($r,$block) {
            $entry=DB::table('blocked_ips')->where('id',$block)->lockForUpdate()->first(); abort_unless($entry,404);
            DB::table('blocked_ips')->where('id',$block)->delete();
            DB::table('admin_audits')->insert(['actor_id'=>$r->user()->id,'action'=>'report.unblock_ip','target'=>'report:'.$entry->report_id,'before'=>json_encode(['ip'=>$entry->ip]),'after'=>null,'created_at'=>now(),'updated_at'=>now()]);
        }); return back()->with('success','IP block removed.');
    }
}
