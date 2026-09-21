<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', ['latestBirthdate'=>now('Asia/Manila')->subYears(18)->toDateString()]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email'=>strtolower(trim((string) $request->email)), 'username'=>strtolower(trim((string) $request->username))]);
        $request->validate([
            'name' => 'required|string|max:255',
            'birthdate' => ['required','date_format:Y-m-d','before_or_equal:'.now('Asia/Manila')->subYears(18)->toDateString()],
            'username' => ['required','string','min:3','max:40','regex:/^[a-z0-9_]+$/','unique:users,username'],
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], ['birthdate.before_or_equal'=>'You must be at least 18 years old to register.', 'birthdate.required'=>'Please enter your birthdate.']);

        $user = User::create([
            'name' => $request->name,
            'birthdate' => $request->birthdate,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);



        Auth::login($user);

        $request->session()->regenerate();
        try { event(new Registered($user)); }
        catch (ValidationException $e) { return redirect()->route('verification.notice')->withErrors($e->errors()); }
        return redirect()->route('verification.notice')->with('status','verification-code-sent');
    }
}
