<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\{DB,Hash};
use RuntimeException;

/** Explicit opt-in fixture set. Never registered in DatabaseSeeder. */
class RoleTestAccountsSeeder extends DemoAccountsSeeder
{
    public const ACCOUNTS = [
        ['name'=>'QA Manager','username'=>'qa_manager','email'=>'qa.manager@example.invalid','role'=>'manager'],
        ['name'=>'QA User','username'=>'qa_user','email'=>'qa.user@example.invalid','role'=>'user'],
        ['name'=>'QA Coach','username'=>'qa_coach','email'=>'qa.coach@example.invalid','role'=>'counselor'],
        ['name'=>'QA Encoder','username'=>'qa_encoder','email'=>'qa.encoder@example.invalid','role'=>'encoder'],
        ['name'=>'QA Support','username'=>'qa_support','email'=>'qa.support@example.invalid','role'=>'support'],
        ['name'=>'QA Admin','username'=>'qa_admin','email'=>'qa.admin@example.invalid','role'=>'admin'],
    ];

    protected function confirmSetup(): bool
    {
        if (!$this->command) throw new RuntimeException('Run this seeder in an interactive terminal.');
        $this->command->warn('Creates privileged, pre-verified TEST accounts in '.app()->environment().'. Never use a live customer database.');
        $this->command->table(['Username','Email','Role'], array_map(fn($a)=>[$a['username'],$a['email'],$a['role']],self::ACCOUNTS));
        return $this->command->confirm('Is this a disposable local/testing/staging database, and do you want these test accounts?',false);
    }

    private function existing(array $account): ?User
    {
        $matches=User::whereRaw('LOWER(username) = ?',[$account['username']])
            ->orWhereRaw('LOWER(email) = ?',[$account['email']])->get();
        if ($matches->isEmpty()) return null;
        $user=$matches->first();
        if ($matches->count()!==1 || $user->username!==$account['username'] || $user->email!==$account['email'] || $user->role!==$account['role']) {
            throw new RuntimeException('Identity conflict for '.$account['username'].'. Nothing was changed.');
        }
        return $user;
    }

    public function run(): void
    {
        if (!app()->environment(['local','testing','staging'])) throw new RuntimeException('Test accounts are blocked in production. Use an isolated test deployment.');
        // Check all identities before requesting passwords or writing anything.
        $missing=[];
        foreach (self::ACCOUNTS as $account) if (!$this->existing($account)) $missing[]=$account;
        if (!$missing) {
            $this->command?->info('All six test accounts already exist. Nothing changed.');
            return;
        }
        if (!$this->confirmSetup()) return;
        $hashes=[];
        foreach ($missing as $account) $hashes[$account['username']]=Hash::make($this->passwordFor($account['username']));
        DB::transaction(function()use($missing,$hashes){
            // Recheck even preserved identities; a concurrent conflict rolls back the whole set.
            foreach (self::ACCOUNTS as $account) $this->existing($account);
            foreach ($missing as $account) {
                if ($this->existing($account)) continue;
                $minutes=$account['role']==='user'?30:0;
                $user=User::forceCreate($account+[
                    'password'=>$hashes[$account['username']], 'birthdate'=>'1990-01-01',
                    'email_verified_at'=>now(), 'available_credits'=>$minutes,
                    'is_approved'=>$account['role']==='counselor', 'is_suspended'=>false,
                    'rate_per_hour'=>null,
                    'manager_id'=>$account['role']==='counselor'?User::where('username','qa_manager')->value('id'):null,
                ]);
                if ($minutes) DB::table('credit_transactions')->insert([
                    'user_id'=>$user->id,'kind'=>'opening','amount_units'=>$minutes*3600,'balance_units'=>$minutes*3600,
                    'earning_units'=>0,'reason'=>'Non-cash QA test minutes','created_at'=>now(),'updated_at'=>now(),
                ]);
            }
        });
        $this->command?->info('Test accounts ready. Existing accounts were preserved. Sign in using the usernames and passwords you entered.');
    }
}
