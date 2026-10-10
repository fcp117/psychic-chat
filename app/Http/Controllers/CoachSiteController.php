<?php
namespace App\Http\Controllers;
use App\Services\CoachSite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
class CoachSiteController extends Controller {
 public function index(CoachSite $site) {
  return Inertia::render('Admin/CoachSite',['settings'=>$site->settings(),'coaches'=>$site->eligible()->orderBy('name')->get(['id','name','email'])]);
 }
 public function update(Request $r, CoachSite $site) {
  $v=$r->validate(['rainbow_only'=>'required|boolean','applications_open'=>'required|boolean','rainbow_user_id'=>'present|nullable|integer']);
  DB::transaction(function() use($r,$site,$v) {
   $old=DB::table('coach_site_settings')->where('id',1)->lockForUpdate()->first();
   if (($v['rainbow_only'] && !$v['rainbow_user_id']) || ($v['rainbow_user_id'] && !$site->eligible()->whereKey($v['rainbow_user_id'])->exists())) {
    throw ValidationException::withMessages(['rainbow_user_id'=>'Select Rainbow’s approved, verified, active coach account.']);
   }
   DB::table('coach_site_settings')->where('id',1)->update($v+['updated_at'=>now()]);
   DB::table('admin_audits')->insert(['actor_id'=>$r->user()->id,'action'=>'coach_site.updated','target'=>'coach_site_settings:1','before'=>json_encode($old),'after'=>json_encode($v),'created_at'=>now(),'updated_at'=>now()]);
  });
  return back()->with('success','Coach settings saved. Existing conversations and active readings are unchanged.');
 }
}
