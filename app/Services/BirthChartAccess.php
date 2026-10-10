<?php
namespace App\Services;
use App\Models\User;
class BirthChartAccess {
 public function ensure(?User $user): void {
  abort_unless(config('birth_chart.enabled'),404);
  abort_unless($user && $user->hasVerifiedEmail() && !$user->is_suspended && !$user->closed_at,403);
  // Future paid access belongs here, with server-side entitlements and delivery.
  // Never charge minutes merely for visiting or generating a report.
  abort_unless(config('birth_chart.access')==='free',403,'Birth chart access is not configured.');
 }
}
