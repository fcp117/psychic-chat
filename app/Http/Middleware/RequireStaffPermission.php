<?php
namespace App\Http\Middleware;
use App\Services\StaffAccess;
use Closure;
use Illuminate\Http\Request;
class RequireStaffPermission {
 public function handle(Request $request,Closure $next,string $permission){
  abort_unless(StaffAccess::allows($request->user(),$permission),403);
  return $next($request);
 }
}
