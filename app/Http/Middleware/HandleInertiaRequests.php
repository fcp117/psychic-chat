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
            'coachSite' => fn () => app(\App\Services\CoachSite::class)->settings(),
            'features' => ['forecast' => (bool) config('features.forecast'), 'birthChart' => (bool) config('birth_chart.enabled')],
            'flash' => ['success' => fn () => $request->session()->get('success'), 'error' => fn () => $request->session()->get('error')],
            'auth' => [
                'user' => $request->user() ? (function()use($request){$wallet=app(\App\Services\MinuteWallet::class);$u=$wallet->refresh($request->user());$u->setAttribute('spendable_minutes',$wallet->available($u)/3600);return $u;})() : null,
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
