<?php
namespace App\Services;
class StaffAccess {
 public const ROLES=['user','counselor','admin','encoder','manager','support'];
 public static function allows($user,string $permission): bool {
  if(!$user || $user->is_suspended)return false;
  if($user->role==='admin')return true;
  return in_array($permission,match($user->role){
   'encoder'=>['content'],
   'manager'=>['content','reports','schedule'],
   'support'=>['reports','privacy','support','health','email'],
   default=>[],
  },true);
 }
 public static function sections($user): array {
  if($user?->role==='admin')return ['*'];
  return match($user?->role){
   'encoder'=>['birth-chart','moonoscope','tarot'],
   'manager'=>['birth-chart','moonoscope','tarot','reports','schedule'],
   'support'=>['support','privacy','reports','health','email'],
   default=>[],
  };
 }
}
