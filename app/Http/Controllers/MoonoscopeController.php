<?php
namespace App\Http\Controllers;
use App\Services\Moonoscope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
class MoonoscopeController extends Controller {
 private function enabled(): void {abort_unless(config('features.forecast'),404);}
 private function admin(Request $r): void {$this->enabled();abort_unless($r->user()?->role==='admin',403);}
 private function decode($d){if(!$d)return null;foreach(['facts','lines','published_lines'] as $k)$d->$k=$d->$k?json_decode($d->$k,true):null;return $d;}
 public function index(Request $r){
  $this->enabled();$today=now('Asia/Manila')->toDateString();
  $d=DB::table('moonoscope_days')->where('reading_date',$today)->whereNotNull('published_at')->where('published_at','<=',now())->first();
  return Inertia::render('Moonoscope',['today'=>$today,'daily'=>$d?['date'=>$d->reading_date,'facts'=>json_decode($d->facts,true),'lines'=>json_decode($d->published_lines,true)]:null,'birthdate'=>$r->user()?->birthdate?->format('Y-m-d'),'forecasts'=>DB::table('forecasts')->whereNotNull('published_at')->where('published_at','<=',now())->latest('published_at')->paginate(5)]);
 }
 public function adminIndex(Request $r,Moonoscope $s){
  $this->admin($r);$v=$r->validate(['date'=>'nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-30']);$date=$v['date']??now('Asia/Manila')->toDateString();
  return Inertia::render('Admin/Moonoscope',['date'=>$date,'mode'=>DB::table('moonoscope_settings')->where('id',1)->value('mode'),'aiReady'=>$s->ready(),'day'=>$this->decode(DB::table('moonoscope_days')->where('reading_date',$date)->first()),'templates'=>DB::table('moonoscope_templates')->orderBy('house')->get(),'themes'=>Moonoscope::THEMES,'signs'=>Moonoscope::SIGNS,'days'=>DB::table('moonoscope_days')->select('reading_date','published_at','mode')->latest('reading_date')->paginate(10)->withQueryString()]);
 }
 public function settings(Request $r,Moonoscope $s){
  $this->admin($r);$v=$r->validate(['mode'=>['required',Rule::in(['manual','templates','ai'])]]);
  DB::transaction(function()use($r,$v,$s){DB::table('moonoscope_settings')->where('id',1)->update($v+['updated_at'=>now()]);$s->audit($r->user()->id,'mode','settings',$v);});return back()->with('success','Content mode saved. Existing drafts and publications are unchanged.');
 }
 public function templates(Request $r,Moonoscope $s){
  $this->admin($r);$v=$r->validate(['templates'=>'required|array|size:12','templates.*.body'=>'nullable|string|max:1000']);
  foreach($v['templates'] as $row)if(count(preg_split('/\s+/u',trim($row['body']??''),-1,PREG_SPLIT_NO_EMPTY))>35)$s->fail('Templates must be 35 words or fewer.');
  DB::transaction(function()use($v,$s,$r){foreach(array_values($v['templates']) as $i=>$row)DB::table('moonoscope_templates')->where('house',$i+1)->update(['body'=>trim($row['body']??''),'updated_at'=>now()]);$s->audit($r->user()->id,'templates','templates',$v);});return back()->with('success','Templates saved. Existing daily sets are unchanged.');
 }
 public function generate(Request $r,Moonoscope $s){
  $this->admin($r);$v=$r->validate(['date'=>'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-30','consent'=>'accepted']);
  if(DB::table('moonoscope_days')->where('reading_date',$v['date'])->exists())$s->fail('This date already has a draft. Open and edit it; generation will not overwrite it.');
  $mode=DB::table('moonoscope_settings')->where('id',1)->value('mode');$calc=$s->calculate($v['date']);$lines=$s->draft($mode,$calc);
  DB::transaction(function()use($r,$s,$v,$mode,$calc,$lines){$inserted=DB::table('moonoscope_days')->insertOrIgnore(['reading_date'=>$v['date'],'mode'=>$mode,'facts'=>json_encode($calc['facts']),'prompt'=>$calc['prompt'],'lines'=>json_encode($lines),'updated_by'=>$r->user()->id,'created_at'=>now(),'updated_at'=>now()]);if(!$inserted)$s->fail('Another administrator created this date. Reload to open their draft.');$s->audit($r->user()->id,'created',$v['date'],['mode'=>$mode]);});return back()->with('success','Draft prepared. Review all 12 messages before publication.');
 }
 public function save(Request $r,int $day,Moonoscope $s){
  $this->admin($r);$v=$r->validate(['version'=>'required|integer|min:1','lines'=>'required|array|size:12','lines.*.sign'=>'required|string','lines.*.text'=>'present|nullable|string|max:1000','action'=>['required',Rule::in(['save','publish','unpublish'])],'reviewed'=>'nullable|boolean']);
  $lines=array_map(fn($l)=>['sign'=>$l['sign'],'text'=>$l['text']??''],$v['lines']);$lines=$s->validateLines($lines,$v['action']==='publish');
  if($v['action']==='publish' && !($v['reviewed']??false))$s->fail('Confirm the editorial checklist before publishing.');
  DB::transaction(function()use($r,$s,$v,$day,$lines){$d=DB::table('moonoscope_days')->where('id',$day)->lockForUpdate()->first();abort_unless($d,404);if((int)$d->version!==(int)$v['version'])$s->fail('This draft changed in another window. Reload before saving.');
   $update=['lines'=>json_encode($lines),'version'=>$d->version+1,'updated_by'=>$r->user()->id,'updated_at'=>now()];
   if($v['action']==='publish'){$update['published_lines']=json_encode($lines);$update['published_at']=now();}
   if($v['action']==='unpublish'){$update['published_at']=null;$update['published_lines']=null;}
   DB::table('moonoscope_days')->where('id',$day)->update($update);$s->audit($r->user()->id,$v['action'],$d->reading_date,['version'=>$d->version+1,'lines'=>$lines]);
  });return back()->with('success',$v['action']==='save'?'Draft saved. Published text is unchanged.':($v['action']==='publish'?'Approved messages published for their reading date.':'Publication withdrawn. Draft retained.'));
 }
}
