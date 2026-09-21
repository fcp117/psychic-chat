<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class DemoAccountsSeeder extends Seeder
{
    protected function passwordFor(string $username): string
    {
        if (!$this->command || !$this->command->getInput()->isInteractive()) {
            throw new RuntimeException('Run this seeder in an interactive terminal to enter private passwords.');
        }
        while (true) {
            $password = $this->command->secret("Choose password for {$username}", false);
            if (!is_string($password) || $password === '') throw new RuntimeException('Password entry cancelled. No demo accounts were created.');
            $validator = Validator::make(['password' => $password], ['password' => ['required', Password::defaults()]]);
            if ($validator->fails()) { $this->command->warn($validator->errors()->first('password')); continue; }
            if ($password !== $this->command->secret("Confirm password for {$username}", false)) {
                $this->command->warn('Passwords did not match. Try again.'); continue;
            }
            return $password;
        }
    }

    private function existing(array $account): ?User
    {
        $matches = User::whereRaw('LOWER(username) = ?', [$account['username']])
            ->orWhereRaw('LOWER(email) = ?', [$account['email']])->get();
        if ($matches->isEmpty()) return null;
        $user = $matches->first();
        if ($matches->count() !== 1 || $user->username !== $account['username'] || $user->email !== $account['email'] || $user->role !== $account['role']) {
            throw new RuntimeException("Account conflict for {$account['username']}. Existing accounts were not changed; resolve the username/email/role conflict manually.");
        }
        return $user;
    }

    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) throw new RuntimeException('Demo accounts are allowed only in local or testing environments.');
        $accounts = [
            ['name'=>'Test User', 'username'=>'testuser', 'email'=>'abc@chat.com', 'role'=>'user', 'credits'=>10],
            ['name'=>'Test Counselor', 'username'=>'testcounselor', 'email'=>'counselor@chat.com', 'role'=>'counselor', 'credits'=>0],
            ['name'=>'Admin User', 'username'=>'admin', 'email'=>'admin@chat.com', 'role'=>'admin', 'credits'=>0],
        ];
        // Check every identity before asking for secrets or writing anything.
        $missing = [];
        foreach ($accounts as $account) {
            if ($this->existing($account)) $this->command?->info("Kept existing {$account['username']} unchanged.");
            else $missing[] = $account;
        }
        $hashes = [];
        foreach ($missing as $account) $hashes[$account['username']] = Hash::make($this->passwordFor($account['username']));
        $created = DB::transaction(function () use ($missing, $hashes) {
            $names = [];
            foreach ($missing as $account) {
                if ($this->existing($account)) continue;
                $credits = $account['credits']; unset($account['credits']);
                $user = User::forceCreate($account + [
                    'password'=>$hashes[$account['username']], 'email_verified_at'=>now(),
                    'available_credits'=>$credits, 'birthdate'=>'1990-01-01',
                    'is_approved'=>$account['role']==='counselor', 'is_suspended'=>false,
                    'rate_per_hour'=>null,
                ]);
                if ($credits > 0) DB::table('credit_transactions')->insert([
                    'user_id'=>$user->id, 'kind'=>'opening', 'amount_units'=>$credits*3600,
                    'balance_units'=>$credits*3600, 'earning_units'=>0,
                    'reason'=>'Initial development demo credits', 'created_at'=>now(), 'updated_at'=>now(),
                ]);
                $names[] = $user->username;
            }
            return $names;
        });
        foreach ($created as $name) $this->command?->info("Created {$name}.");
        $this->command?->info('Demo setup complete. Existing passwords, balances, verification, and roles were preserved.');
    }
}
