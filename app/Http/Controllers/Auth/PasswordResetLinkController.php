<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        if (!app()->environment('testing') && config('mail.default') === 'resend' && blank(config('services.resend.key'))) {
            throw ValidationException::withMessages(['email' => 'Email delivery is not configured yet. Please contact the administrator.']);
        }

        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Throwable $error) {
            // Delivery errors must not reveal which addresses have accounts.
            \Illuminate\Support\Facades\Log::warning('Password recovery delivery failed', ['type'=>get_class($error)]);
        }

        // Unknown addresses and the broker's per-account cooldown must look identical.
        return back()->with('status', 'If an account matches that email, a password reset link will be sent. Please check your inbox.');
    }
}
