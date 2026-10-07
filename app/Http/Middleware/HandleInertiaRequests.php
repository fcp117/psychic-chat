<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'features' => ['forecast' => (bool) config('features.forecast')],
            'flash' => ['success' => fn () => $request->session()->get('success'), 'error' => fn () => $request->session()->get('error')],
            'auth' => [
                'user' => $request->user(),
            ],
            'assistant' => [
                'enabled' => (bool) config('assistant.enabled') && filled(config('services.together.key')),
                'usage' => $request->user() ? [
                    'website' => ['used' => RateLimiter::attempts('assistant:website:daily:'.$request->user()->id), 'limit' => 25],
                    'library' => ['used' => RateLimiter::attempts('assistant:library:daily:'.$request->user()->id), 'limit' => 5],
                ] : null,
            ],
        ];
    }
}
