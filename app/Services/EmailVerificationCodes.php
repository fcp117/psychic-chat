<?php
namespace App\Services;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
class EmailVerificationCodes {
    public function send(User $user): void {
        DB::transaction(function() use ($user) {
            $fresh=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if($fresh->hasVerifiedEmail()) return;
            $old=DB::table('email_verification_codes')->where('user_id',$fresh->id)->first();
            if($old && strtotime($old->sent_at)>now()->subMinute()->timestamp)
                throw ValidationException::withMessages(['code'=>'Please wait 60 seconds before requesting another code.']);
            if(!app()->environment('testing') && in_array(config('mail.default'),['log','array','failover']))
                throw ValidationException::withMessages(['code'=>'Email delivery is not configured yet. Please contact the administrator.']);
            if(!app()->environment('testing') && config('mail.default')==='smtp' && (!config('mail.mailers.smtp.username') || !config('mail.mailers.smtp.password')))
                throw ValidationException::withMessages(['code'=>'Email delivery is not configured yet. Please contact the administrator.']);
            $code=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
            DB::table('email_verification_codes')->updateOrInsert(['user_id'=>$fresh->id],[
                'email'=>$fresh->email,'code_hash'=>Hash::make($code),'attempts'=>0,
                'expires_at'=>now()->addMinutes(10),'sent_at'=>now(),
            ]);
            try { $fresh->notify(new EmailVerificationCode($code)); }
            catch(\Throwable $e) {
                Log::warning('Verification email delivery failed',['type'=>get_class($e)]);
                throw ValidationException::withMessages(['code'=>'We could not send your code. Please try again shortly.']);
            }
        });
    }
    public function verify(User $user,string $code): void {
        $result=DB::transaction(function() use($user,$code) {
            $fresh=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if($fresh->hasVerifiedEmail()) return 'already';
            $row=DB::table('email_verification_codes')->where('user_id',$fresh->id)->lockForUpdate()->first();
            if(!$row || $row->email!==$fresh->email || strtotime($row->expires_at)<=now()->timestamp)
                return 'Your code has expired or is unavailable. Request a new code.';
            if($row->attempts>=5) return 'Too many incorrect attempts. Request a new code.';
            if(!Hash::check($code,$row->code_hash)) {
                DB::table('email_verification_codes')->where('user_id',$fresh->id)->increment('attempts');
                return 'That code is incorrect. Please check your email.';
            }
            $fresh->markEmailAsVerified();
            DB::table('email_verification_codes')->where('user_id',$fresh->id)->delete();
            return 'verified';
        });
        if($result==='verified') event(new Verified($user->fresh()));
        elseif($result!=='already') throw ValidationException::withMessages(['code'=>$result]);
    }
}
