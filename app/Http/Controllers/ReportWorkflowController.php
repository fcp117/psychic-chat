<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\{StaffAccess,AppNotifications};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ReportWorkflowController extends Controller {
 public function recommend(Request $r,int $report){
  abort_unless(StaffAccess::allows($r->user(),'support'),403);
  $v=$r->validate(['action'=>'required|in:resolve,dismiss,suspend,ban,restore,block_ip','note'=>'required|string|min:10|max:1000','confirm_ip'=>'accepted_if:action,block_ip']);
  DB::transaction(function()use($r,$report,$v){
   $entry=DB::table('user_reports')->where('id',$report)->lockForUpdate()->first();abort_unless($entry,404);
   abort_unless(in_array($entry->status,['open','returned','reviewed','dismissed']),409,'This case has already advanced.');
   $target=User::findOrFail($entry->reported_id);abort_if($target->id===$r->user()->id||$target->role==='admin',403);
   if($target->role==='counselor')abort_unless(User::whereKey($target->manager_id)->where('role','manager')->where('is_suspended',false)->exists(),422,'Admin must assign an active manager to this coach first.');
   DB::table('user_reports')->where('id',$report)->update(['status'=>'manager_review','proposed_action'=>$v['action'],'support_note'=>$v['note'],'support_id'=>$r->user()->id,'support_reviewed_at'=>now(),'manager_id'=>null,'manager_reviewed_at'=>null,'manager_note'=>null,'updated_at'=>now()]);
   $this->audit($r,$report,'recommended',$v);
   $ids=User::where('role','manager')->where('is_suspended',false)->when($target->role==='counselor',fn($q)=>$q->whereKey($target->manager_id))->pluck('id');
   foreach($ids as $id)AppNotifications::send($id,'report:'.$report.':manager:'.now()->timestamp,'Report awaiting review','Support submitted a report recommendation.',route('admin.reports',[],false));
  });return back()->with('success','Recommendation sent to Manager. Access is unchanged.');
 }
 public function manager(Request $r,int $report){
  abort_unless($r->user()->role==='manager'&&!$r->user()->is_suspended,403);
  $v=$r->validate(['approve'=>'required|boolean','note'=>'required|string|min:10|max:1000']);
  DB::transaction(function()use($r,$report,$v){
   $entry=DB::table('user_reports')->where('id',$report)->lockForUpdate()->first();abort_unless($entry,404);
   abort_unless($entry->status==='manager_review'&&$entry->support_id,409);
   $target=User::findOrFail($entry->reported_id);
   abort_if($target->id===$r->user()->id||$entry->support_id===$r->user()->id||$target->role==='admin',403);
   abort_unless($target->role!=='counselor'||(int)$target->manager_id===$r->user()->id,403);
   DB::table('user_reports')->where('id',$report)->update(['status'=>$v['approve']?'admin_review':'returned','manager_note'=>$v['note'],'manager_id'=>$r->user()->id,'manager_reviewed_at'=>now(),'updated_at'=>now()]);
   $this->audit($r,$report,$v['approve']?'manager_approved':'manager_returned',$v);
   $ids=$v['approve']?User::where('role','admin')->where('is_suspended',false)->pluck('id'):[$entry->support_id];
   foreach($ids as $id)AppNotifications::send($id,'report:'.$report.':decision:'.now()->timestamp,'Report review update',$v['approve']?'Manager approved a recommendation for final Admin review.':'Manager returned a recommendation to Support.',route('admin.reports',[],false));
  });return back()->with('success',$v['approve']?'Sent to Admin for final approval. Access is unchanged.':'Returned to Support.');
 }
 private function audit(Request $r,int $id,string $action,array $data):void{
  DB::table('admin_audits')->insert(['actor_id'=>$r->user()->id,'action'=>'report.'.$action,'target'=>'report:'.$id,'before'=>null,'after'=>json_encode($data),'created_at'=>now(),'updated_at'=>now()]);
 }
}
