<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Local development tunnels terminate HTTPS before forwarding to loopback.
        // Do not trust forwarded client IPs or arbitrary remote proxies.
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1'],
            headers: Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT,
        );
        $middleware->web(append: [
            \App\Http\Middleware\EnsureActiveAccount::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: ['payment-webhooks/stripe','payment-webhooks/paypal','payment-webhooks/maya','payment-webhooks/gcash']);
        $middleware->alias(['role' => \App\Http\Middleware\RequireRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Throwable $e) { \App\Services\IncidentReporter::record($e); });
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, Request $request) {
            $status=$response->getStatusCode();
            if(in_array($status,[403,404,419,429,500,503])) {
                if($request->expectsJson() && !$request->header('X-Inertia')) return response()->json(['message'=>match($status){403=>'You do not have access to this action.',404=>'This item is no longer available.',419=>'Your session expired. Refresh and sign in again.',429=>'Too many requests. Wait a moment before trying again.',default=>'We could not complete this request. Please try again shortly.'}],$status)->withHeaders($status===429?['Retry-After'=>$response->headers->get('Retry-After','60')]:[]);
                if($request->header('X-Inertia') && !in_array($request->method(),['GET','HEAD'])) {
                    return back()->withErrors(['request'=>match($status){403=>'You do not have access to this action.',404=>'This item is no longer available. Refresh this page.',419=>'Your session expired. Refresh this page before trying again.',429=>'Too many requests. Wait a minute before trying again.',default=>'The request could not be confirmed. Check its current status before retrying.'}]);
                }
                return \Inertia\Inertia::render('Error',['status'=>$status])->toResponse($request)->setStatusCode($status);
            }
            return $response;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
