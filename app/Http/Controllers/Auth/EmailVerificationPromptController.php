<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        return $request->user()->hasVerifiedEmail()
                    ? redirect()->intended(route('dashboard', absolute: false))
                    : Inertia::render('Auth/VerifyEmail', ['status' => session('status'), 'resendAt' => (function() use ($request) { $sent=\Illuminate\Support\Facades\DB::table('email_verification_codes')->where('user_id',$request->user()->id)->value('sent_at'); return $sent ? \Illuminate\Support\Carbon::parse($sent)->addMinute()->getTimestampMs() : null; })()]);
    }
}
