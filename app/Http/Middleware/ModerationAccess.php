<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ModerationAccess {
    public function handle(Request $request, Closure $next) {
        // Webhooks are provider callbacks, not browsing access.
        if (!$request->is('payment-webhooks/*', 'logout')) {
            abort_if(DB::table('blocked_ips')->where('ip',$request->ip())->exists(),403,'This network address is blocked.');
            $user=$request->user();
            if ($user && $user->last_seen_ip !== $request->ip()) {
                DB::table('users')->where('id',$user->id)->update(['last_seen_ip'=>$request->ip()]);
            }
        }
        return $next($request);
    }
}
