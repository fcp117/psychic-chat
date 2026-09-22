<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';
    protected $description = 'Interactively create a new, email-verified administrator without modifying existing accounts';

    public function handle(): int
    {
        if (!$this->input->isInteractive()) {
            $this->error('An interactive terminal is required. No account was created.');
            return self::FAILURE;
        }

        $data = [
            'name' => trim((string) $this->ask('Full name')),
            'username' => strtolower(trim((string) $this->ask('Username'))),
            'email' => strtolower(trim((string) $this->ask('Email address'))),
            'birthdate' => trim((string) $this->ask('Birthdate (YYYY-MM-DD; age 18 or older)')),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9_]+$/', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'birthdate' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->subYears(18)->toDateString()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) $this->error($message);
            return self::FAILURE;
        }
        if ($this->identityExists($data)) {
            $this->error('That username or email already belongs to an account. Nothing was changed.');
            return self::FAILURE;
        }
        try {
            // Never fall back to visible password entry on unsupported terminals.
            $password = $this->secret('Password', false);
            $confirmation = $this->secret('Confirm password', false);
        } catch (\Throwable $e) {
            $this->error('Secure password entry is unavailable or was cancelled. Use an interactive SSH terminal.');
            return self::FAILURE;
        }
        $validator = Validator::make(['password'=>$password, 'password_confirmation'=>$confirmation], [
            'password'=>['required', 'string', 'confirmed', Password::defaults()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) $this->error($message);
            unset($password, $confirmation, $validator);
            return self::FAILURE;
        }
        $hash = Hash::make($password);
        unset($password, $confirmation, $validator);

        $this->table(['Name', 'Username', 'Email', 'Role'], [[$data['name'], $data['username'], $data['email'], 'Admin']]);
        $this->warn('Environment: '.app()->environment().'. This creates administrator access and marks the email as verified without an OTP. Confirm the address is correct and belongs to the intended administrator.');
        if (!$this->confirm('Create this new administrator?', false)) {
            $this->info('Cancelled. No account was created.');
            return self::SUCCESS;
        }
        try {
            $user = DB::transaction(function () use ($data, $hash) {
                if ($this->identityExists($data)) throw new \RuntimeException('Identity conflict');
                $user = new User($data);
                $user->password = $hash;
                $user->role = 'admin';
                $user->save();
                if (!$user->markEmailAsVerified()) throw new \RuntimeException('Verification failed');
                return $user;
            });
        } catch (\Throwable $e) {
            // Do not expose database exception bindings or credential material.
            $this->error('Account creation failed; nothing was saved. Check for a username/email conflict and ensure migrations and the database are ready.');
            return self::FAILURE;
        }
        $this->info('Administrator created successfully. ID: '.$user->id);
        try { event(new Verified($user)); }
        catch (\Throwable $e) { $this->warn('The account is created and verified, but a verification event listener failed. Do not recreate the account.'); }
        return self::SUCCESS;
    }

    private function identityExists(array $data): bool
    {
        return User::whereRaw('LOWER(username) = ?', [$data['username']])
            ->orWhereRaw('LOWER(email) = ?', [$data['email']])->exists();
    }
}
