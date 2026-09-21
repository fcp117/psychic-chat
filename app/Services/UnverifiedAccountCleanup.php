<?php
namespace App\Services;
use App\Models\User;
class UnverifiedAccountCleanup {
 public function eligible() {
  $q=User::where('role','user')->whereNull('email_verified_at')->whereNull('profile_photo_path')->where('credit_units',0)->where('available_credits',0);
  foreach(['credit_purchases','credit_transactions','counselor_applications'] as $table) $q->whereNotExists(fn($s)=>$s->selectRaw('1')->from($table)->whereColumn($table.'.user_id','users.id'));
  $q->whereNotExists(fn($s)=>$s->selectRaw('1')->from('chat_sessions')->where(fn($w)=>$w->whereColumn('client_id','users.id')->orWhereColumn('counselor_id','users.id')));
  $q->whereNotExists(fn($s)=>$s->selectRaw('1')->from('messages')->whereColumn('sender_id','users.id'));
  return $q;
 }
}