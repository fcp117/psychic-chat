<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class NotificationController extends Controller {
 public function index(Request $r) {
  $q=DB::table('app_notifications')->where('user_id',$r->user()->id);
  return response()->json(['items'=>(clone $q)->orderByDesc('id')->limit(30)->get(['id','title','body','url','read_at','created_at']),'unread'=>(clone $q)->whereNull('read_at')->count()]);
 }
 public function read(Request $r) {
  $v=$r->validate(['id'=>'nullable|integer|min:1']);
  $q=DB::table('app_notifications')->where('user_id',$r->user()->id)->whereNull('read_at');
  if(!empty($v['id'])) $q->where('id',$v['id']);
  $q->update(['read_at'=>now(),'updated_at'=>now()]); return response()->json(['ok'=>true]);
 }
}
