<?php
namespace App\Http\Controllers;
use App\Services\UnverifiedAccountCleanup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class AccountCleanupController extends Controller {
 public function preview(Request $r, UnverifiedAccountCleanup $cleanup) {
  abort_unless($r->user()?->role==='admin',403);
  $v=$r->validate(['mode'=>'required|in:selected,abandoned','ids'=>'required_if:mode,selected|array|max:500','ids.*'=>'integer|distinct']);
  $query=$cleanup->eligible()->where('id','!=',$r->user()->id);
  if($v['mode']==='abandoned') $query->where('created_at','<=',now()->subDays(7));
  else $query->whereIn('id',$v['ids']);
  $ids=$query->orderBy('id')->limit(500)->pluck('id')->all();
  if(!$ids) throw ValidationException::withMessages(['cleanup'=>'No eligible accounts remain for deletion.']);
  $token=(string)Str::uuid();
  $r->session()->put('account_cleanup',['token'=>$token,'ids'=>$ids,'mode'=>$v['mode'],'expires'=>now()->addMinutes(10)->timestamp]);
  return response()->json(['token'=>$token,'count'=>count($ids),'mode'=>$v['mode']]);
 }
 public function destroy(Request $r, UnverifiedAccountCleanup $cleanup) {
  abort_unless($r->user()?->role==='admin',403);
  $v=$r->validate(['token'=>'required|uuid','confirmation'=>'required|in:DELETE']);
  $preview=$r->session()->get('account_cleanup');
  if(!$preview || !hash_equals($preview['token'],$v['token']) || $preview['expires']<now()->timestamp) throw ValidationException::withMessages(['cleanup'=>'The confirmation expired. Preview the accounts again.']);
  $deleted=DB::transaction(function()use($r,$cleanup,$preview){
   $count=0;
   foreach($preview['ids'] as $id){
    $user=\App\Models\User::whereKey($id)->lockForUpdate()->first();
    if(!$user || $user->id===$r->user()->id || !$cleanup->eligible()->whereKey($id)->exists())continue;
    if($preview['mode']==='abandoned' && $user->created_at->gt(now()->subDays(7)))continue;
    DB::table('sessions')->where('user_id',$id)->delete();
    DB::table('password_reset_tokens')->where('email',$user->email)->delete();
    // An unused registration should not own files; protect unexpected files for review.
    if($user->profile_photo_path)continue;
    $user->delete();
    DB::table('admin_audits')->insert(['actor_id'=>$r->user()->id,'action'=>'account.unverified_deleted','target'=>'user:'.$id,'before'=>json_encode(['id'=>$id,'verified'=>false]),'after'=>null,'created_at'=>now(),'updated_at'=>now()]);
    $count++;
   }
   return $count;
  });
  $r->session()->forget('account_cleanup');
  return redirect()->route('admin.settings',['section'=>'users','verification'=>'unverified'])->with('success',"Deleted $deleted unverified account(s). Accounts no longer eligible were skipped.");
 }
}