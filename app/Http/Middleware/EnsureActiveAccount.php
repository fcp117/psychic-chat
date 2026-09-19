<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsureActiveAccount {
    public function handle(Request $request, Closure $next) {
        abort_if($request->user()?->is_suspended && !$request->is('logout'), 403, 'This account is suspended. Contact an administrator.');
        return $next($request);
    }
}
