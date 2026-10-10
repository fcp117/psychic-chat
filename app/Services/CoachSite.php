<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use App\Models\User;
class CoachSite {
 public function settings(): array {
  $s=DB::table('coach_site_settings')->find(1);
  return ['rainbow_only'=>(bool)$s->rainbow_only,'applications_open'=>(bool)$s->applications_open,'rainbow_user_id'=>$s->rainbow_user_id ? (int)$s->rainbow_user_id : null];
 }
 public function allows(int $id): bool {
  $s=$this->settings();
  return !$s['rainbow_only'] || $s['rainbow_user_id']===$id;
 }
 public function eligible() {
  return User::where('role','counselor')->where('is_approved',true)->where('is_suspended',false)->whereNotNull('email_verified_at');
 }
}
