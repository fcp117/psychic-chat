<?php
namespace App\Services;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReadingBilling {
    public function notify(ChatSession $session): void {
        \App\Services\AppNotifications::reading($session);
        DB::afterCommit(function () use ($session) {
            try { broadcast(new \App\Events\ReadingUpdated($session)); } catch (\Throwable $e) { report($e); }
        });
    }
    public function notice(ChatSession $session, string $content): void {
        $session->messages()->create(['sender_id'=>$session->client_id,'kind'=>'system','content'=>$content]);
        $this->notify($session);
    }
    public function settings() { $s=DB::table('billing_settings')->find(1); $s->default_rate=60; $s->minimum_credits=1; return $s; }
    public function rate(User $counselor): int { return 60; } // One minute per minute for every coach.
    public function fail(string $message): never { throw ValidationException::withMessages(['reading' => $message]); }
    public function record(User $user, int $amount, string $kind, string $reason, ?int $actor = null, ?ChatSession $session = null, int $earning = 0): void {
        app(MinuteWallet::class)->change($user,$amount,$reason,$amount<0 && $kind==='reading_charge' ? $session?->booking_id : null);
        $user->credit_units += $amount;
        if ($user->credit_units < 0) $this->fail('Insufficient minutes.');
        $user->save();
        DB::table('credit_transactions')->insert([
            'user_id'=>$user->id, 'actor_id'=>$actor, 'counselor_id'=>$session?->counselor_id,
            'chat_session_id'=>$session?->id, 'kind'=>$kind, 'amount_units'=>$amount,
            'balance_units'=>$user->credit_units, 'earning_units'=>($session?->billing_version === 2 ? 0 : $earning), 'minute_earning_units'=>($session?->billing_version === 2 ? $earning : 0), 'unit_type'=>'minutes', 'reason'=>$reason,
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
    }
    // Call while the user and session rows are locked inside a transaction.
    public function settleLocked(ChatSession $session, User $client, bool $explicitEnd = false, ?string $reason = null): void {
        if ($session->status !== 'active' || $session->agreed_rate === null) return;
        $now = now()->timestamp;
        $start = $session->started_at->timestamp;
        $lastTogether = min($session->client_seen_at?->timestamp ?? $start, $session->counselor_seen_at?->timestamp ?? $start);
        $disconnected = $now - $lastTogether >= $session->disconnect_seconds;
        // Bill only time confirmed by both participants. No charges for disconnect grace.
        $cutoff = $explicitEnd && !$disconnected ? $now : min($now, $lastTogether);
        $scheduledEnd=$session->hard_stop_at && $now >= $session->hard_stop_at->timestamp;
        if($session->hard_stop_at)$cutoff=min($cutoff,$session->hard_stop_at->timestamp);
        $explicitEnd=$explicitEnd || $scheduledEnd;
        if($scheduledEnd)$reason='Reserved time ended';
        $elapsed = max($session->billed_seconds, $cutoff - $start, 0);
        $available=app(MinuteWallet::class)->available($client,$session->booking_id);
        if($session->booking_id)$available=min($available,(int)DB::table('booking_holds')->where('booking_id',$session->booking_id)->whereNull('released_at')->sum('remaining_units'));
        $due = max(0, $elapsed * $session->agreed_rate - $session->billed_units);
        $charge = min($due, $available);
        $minuteMode=(int)$session->billing_version===2;
        if($minuteMode && ($explicitEnd || $disconnected || $due >= $available)) {
            $due=max(0,(int)ceil($elapsed/60)*3600-$session->billed_units);
            $charge=min($due,$available);
        }
        if ($charge > 0) {
            $this->record($client, -$charge, 'reading_charge', $minuteMode ? 'Reading minutes; session total rounds up to a whole minute when ended' : 'Legacy reading at '.$session->agreed_rate.' credits/hour', null, $session, $charge);
            $session->billed_units += $charge;
        }
        $session->billed_seconds = min($elapsed, (int) ceil($session->billed_units / $session->agreed_rate));
        if ($explicitEnd || $disconnected || $available-$charge <= 0) {
            $session->status = 'completed';
            $exhausted = $available-$charge <= 0;
            $bookingComplete = $session->booking_id && ($scheduledEnd || $exhausted);
            if ($bookingComplete) $reason = 'Your reserved reading time has finished';
            $session->end_reason = $bookingComplete ? 'ended' : ($exhausted ? 'insufficient_credits' : ($disconnected ? 'disconnected' : 'ended'));
            $session->ended_at = $session->started_at->copy()->addSeconds($exhausted ? $session->billed_seconds : $elapsed);
        }
        $session->save();
        if ($client->credit_units <= max(3600, $session->agreed_rate * 60)) AppNotifications::send($client->id,'low-balance:'.$session->id,'Your reading balance is low','Review your remaining minutes before continuing.',route('credits',[],false));
        if ($session->status === 'completed') {
            if($session->booking_id){$b=\App\Models\Booking::findOrFail($session->booking_id);app(BookingService::class)->release($b);$b->update(['status'=>'completed']);}
            $this->notice($session, $session->end_reason === 'disconnected'
                ? 'This reading ended because a participant lost connection. Request to continue whenever you are ready.'
                : ($session->end_reason === 'insufficient_credits' ? 'This reading ended because your minutes ran out. Add minutes, then request to continue.' : 'This reading has ended.'.($reason ? ' Reason: '.$reason.'.' : '').' Request to continue whenever you are ready.'));
        }
    }
    public function settle(int $id, ?int $actor = null, bool $end = false, bool $heartbeat = false, int $idleSeconds = 0, ?string $reason = null): ChatSession {
        $ref = ChatSession::findOrFail($id);
        return DB::transaction(function () use ($ref, $actor, $end, $heartbeat, $idleSeconds, $reason) {
            $client = User::whereKey($ref->client_id)->lockForUpdate()->firstOrFail();
            app(MinuteWallet::class)->expireLocked($client);
            $s = ChatSession::whereKey($ref->id)->lockForUpdate()->firstOrFail();
            // Settle before updating heartbeat so a late tab cannot resurrect expired time.
            $this->settleLocked($s, $client, $end, $reason);
            if ($end && $s->status === 'pending') {
                $s->update(['status'=>'rejected','ended_at'=>now(),'end_reason'=>'cancelled']);
                $this->notice($s,'The reading request was cancelled. You can request to continue here.');
            }
            if ($s->status === 'pending' && $s->agreed_at?->lt(now()->subMinutes(10))) {
                $s->update(['status'=>'rejected','ended_at'=>now(),'end_reason'=>'request_expired']);
                $this->notice($s,'The reading request expired. Request to continue when you are ready.');
            }
            if ($heartbeat) {
                // Presence tracks connection, not keyboard or mouse activity.
                $seen = now();
                if ($actor === $s->client_id && (!$s->client_seen_at || $seen->gt($s->client_seen_at))) $s->client_seen_at = $seen;
                if ($actor === $s->counselor_id && (!$s->counselor_seen_at || $seen->gt($s->counselor_seen_at))) $s->counselor_seen_at = $seen;
                $s->save();
                $this->settleLocked($s, $client);
            }
            return $s->fresh();
        }, 3);
    }
    public function sweep(): void {
        ChatSession::whereIn('status',['active','pending'])->pluck('id')->each(fn ($id) => $this->settle($id));
    }
}
