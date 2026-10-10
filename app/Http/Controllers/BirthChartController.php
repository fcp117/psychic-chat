<?php
namespace App\Http\Controllers;
use App\Services\BirthChartAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
class BirthChartController extends Controller {
 public function index(Request $r,BirthChartAccess $access){
  $access->ensure($r->user());return Inertia::render('BirthChart',['birthdate'=>$r->user()->birthdate?->format('Y-m-d'),'reportName'=>$r->user()->name,'library'=>DB::table('birth_chart_templates')->whereNotNull('body')->where('body','!=','')->get(['point','sign','body'])]);
 }
 public function admin(Request $r){abort_unless($r->user()?->role==='admin',403);return Inertia::render('Admin/BirthChart',['templates'=>DB::table('birth_chart_templates')->orderBy('id')->get()]);}
 public function update(Request $r,int $template){
  abort_unless($r->user()?->role==='admin',403);$v=$r->validate(['body'=>'nullable|string|max:1200','version'=>'required|integer|min:1','reviewed'=>'accepted']);
  DB::transaction(function()use($r,$template,$v){$old=DB::table('birth_chart_templates')->where('id',$template)->lockForUpdate()->first();abort_unless($old,404);
   if((int)$old->version!==(int)$v['version'])throw \Illuminate\Validation\ValidationException::withMessages(['version'=>'This entry changed. Reload before saving.']);
   $after=['body'=>trim($v['body']??''),'version'=>$old->version+1,'updated_at'=>now()];DB::table('birth_chart_templates')->where('id',$template)->update($after);
   DB::table('admin_audits')->insert(['actor_id'=>$r->user()->id,'action'=>'birth_chart.template','target'=>'birth_chart_templates:'.$template,'before'=>json_encode($old),'after'=>json_encode($after),'created_at'=>now(),'updated_at'=>now()]);
  });return back()->with('success','Approved interpretation saved. Existing downloaded reports are unchanged.');
 }
}
