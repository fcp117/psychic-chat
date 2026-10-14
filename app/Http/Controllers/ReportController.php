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
        $id=DB::table('user_reports')->insertGetId($v+['reporter_id'=>$r->user()->id,'reported_id'=>$target->id,'chat_session_id'=>$chatSession->id,'reported_ip'=>$target->last_seen_ip,'status'=>'open','created_at'=>now(),'updated_at'=>now()]);
        foreach(User::whereIn('role',['admin','support'])->where('is_suspended',false)->pluck('id') as $idUser)\App\Services\AppNotifications::send($idUser,'report:'.$id.':new','New report','A report is waiting for Support review.',route('admin.reports',[],false));
        return back()->with('success','Report submitted for administrator review.');
    }
    public function index(Request $r) {
        abort_unless(\App\Services\StaffAccess::allows($r->user(),'reports'),403);
        $v=$r->validate(['role'=>['nullable',Rule::in(['all','user','counselor'])]]); $role=$v['role']??'all';
        $reports=DB::table('user_reports as r')->join('users as u','u.id','=','r.reported_id')->join('users as reporter','reporter.id','=','r.reporter_id')
            ->when($r->user()->role==='manager',fn($q)=>$q->where(fn($x)=>$x->where('u.role','!=','counselor')->orWhere('u.manager_id',$r->user()->id)))
            ->when($role!=='all',fn($q)=>$q->where('u.role',$role))->select('r.*','u.name as reported_name','u.role','u.is_suspended','reporter.name as reporter_name')
            ->orderByRaw("CASE WHEN r.status IN ('open','returned','manager_review','admin_review') THEN 0 ELSE 1 END")->orderByDesc('r.id')->paginate(15)->withQueryString();
        return Inertia::render('Admin/Reports',['reports'=>$reports,'filter'=>$role,'blocks'=>$r->user()->role==='admin'?DB::table('blocked_ips')->orderByDesc('id')->get():[]]);
    }
    public function review(Request $r, int $report) {
        abort_unless($r->user()->role==='admin',403);
        $v=$r->validate(['action'=>['required',Rule::in(['resolve','dismiss','suspend','ban','restore','block_ip'])],'note'=>'required|string|min:5|max:1000','confirm_ip'=>'accepted_if:action,block_ip']);
        DB::transaction(function() use($r,$report,$v) {
            $entry=DB::table('user_reports')->where('id',$report)->lockForUpdate()->first(); abort_unless($entry,404);
            abort_unless($entry->status==='admin_review'&&$entry->support_id&&$entry->manager_id,409,'Support and Manager approval are required first.');
            abort_if(in_array($r->user()->id,[$entry->support_id,$entry->manager_id]),403,'Final approval requires a different administrator.');
            abort_unless($v['action']==='dismiss'||$v['action']===$entry->proposed_action,422,'Apply the approved recommendation or reject it.');
            $user=User::whereKey($entry->reported_id)->lockForUpdate()->firstOrFail();
            $wasSuspended=$user->is_suspended;
            if(in_array($v['action'],['suspend','ban','restore','block_ip'])) abort_if($user->id===$r->user()->id || $user->role==='admin',403);
            if(in_array($v['action'],['suspend','ban'])) {
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
        abort_unless($r->user()->role==='admin'&&!$r->user()->is_suspended,403);
        DB::transaction(function() use($r,$block) {
            $entry=DB::table('blocked_ips')->where('id',$block)->lockForUpdate()->first(); abort_unless($entry,404);
            DB::table('blocked_ips')->where('id',$block)->delete();
            DB::table('admin_audits')->insert(['actor_id'=>$r->user()->id,'action'=>'report.unblock_ip','target'=>'report:'.$entry->report_id,'before'=>json_encode(['ip'=>$entry->ip]),'after'=>null,'created_at'=>now(),'updated_at'=>now()]);
        }); return back()->with('success','IP block removed.');
    }
}
