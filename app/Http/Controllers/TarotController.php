<?php

namespace App\Http\Controllers;

use App\Services\DailyTarot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TarotController extends Controller
{
    private function guest(Request $request, bool $create = false): string
    {
        $token = $request->cookie(DailyTarot::COOKIE);
        if (!is_string($token) || !Str::isUuid($token)) {
            if (!$create) throw ValidationException::withMessages(['draw' => 'Please refresh this page and allow cookies before drawing.']);
            $token = (string) Str::uuid();
            Cookie::queue(cookie(DailyTarot::COOKIE, $token, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'lax'));
        }
        return app(DailyTarot::class)->guestKey($token);
    }

    public function index(Request $request, DailyTarot $tarot)
    {
        return Inertia::render('Tarot/Index', ['tarot' => $tarot->state($this->guest($request, true), $request->user())]);
    }

    public function draw(Request $request, DailyTarot $tarot)
    {
        $data = $request->validate(['request_token' => ['required', 'uuid']]);
        $tarot->draw($this->guest($request), $request->user(), $data['request_token']);
        return redirect()->route('tarot.index');
    }
}
