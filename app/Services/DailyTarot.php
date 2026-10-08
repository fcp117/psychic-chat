<?php

namespace App\Services;

use App\Models\TarotCard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyTarot
{
    public const COOKIE = 'intuition_tarot_guest';

    public function guestKey(string $token): string
    {
        return 'guest:'.hash('sha256', $token);
    }

    private function lockReaders(string $guest, ?User $user): void
    {
        $keys = [$guest];
        if ($user) $keys[] = 'user:'.$user->id;
        sort($keys);
        foreach ($keys as $key) {
            DB::table('tarot_readers')->insertOrIgnore(['id' => $key, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('tarot_readers')->where('id', $key)->lockForUpdate()->first();
            // Also acquire SQLite's write lock before reading the allowance.
            DB::table('tarot_readers')->where('id', $key)->update(['updated_at' => now()]);
        }
        if ($user) {
            // Never transfer a draw that already belongs to a different account.
            DB::table('tarot_draws')->where('guest_id', $guest)->whereNull('user_id')->update(['user_id' => $user->id]);
        }
    }

    private function todayQuery(string $guest, ?User $user)
    {
        return DB::table('tarot_draws')->where('draw_date', now('Asia/Manila')->toDateString())
            ->when($user, fn ($q) => $q->where('user_id', $user->id), fn ($q) => $q->where('guest_id', $guest));
    }

    public function state(string $guest, ?User $user): array
    {
        return DB::transaction(function () use ($guest, $user) {
            $this->lockReaders($guest, $user);
            $rows = $this->todayQuery($guest, $user)->orderBy('id')->get();
            // Claimed readings are only visible to the owning account, not after logout.
            $visible = $user ? $rows : $rows->whereNull('user_id');
            $limit = $user ? 3 : 1;
            return [
                'limit' => $limit,
                'remaining' => max(0, $limit - $rows->count()),
                'date' => now('Asia/Manila')->toDateString(),
                'resets_at' => now('Asia/Manila')->addDay()->startOfDay()->toIso8601String(),
                'available' => TarotCard::where('is_active', true)->exists(),
                'deck_size' => TarotCard::where('is_active', true)->count(),
                'draws' => $visible->map(fn ($draw) => ['id' => $draw->id, 'created_at' => $draw->created_at, 'reading' => json_decode($draw->reading, true)])->values(),
            ];
        }, 5);
    }

    public function draw(string $guest, ?User $user, string $requestToken): void
    {
        DB::transaction(function () use ($guest, $user, $requestToken) {
            $this->lockReaders($guest, $user);
            $previous = DB::table('tarot_draws')->where('request_token', $requestToken)->first();
            if ($previous) {
                $owns = $user ? $previous->user_id === $user->id : !$previous->user_id && $previous->guest_id === $guest;
                if ($owns) return; // Network retries never spend a second draw.
                throw ValidationException::withMessages(['draw' => 'Refresh this page before drawing again.']);
            }
            $today = $this->todayQuery($guest, $user)->get();
            if ($today->count() >= ($user ? 3 : 1)) {
                throw ValidationException::withMessages(['draw' => 'You have used your free draws today. New draws are available at midnight Philippine time.']);
            }
            $cards = TarotCard::where('is_active', true)->get();
            if ($cards->isEmpty()) throw ValidationException::withMessages(['draw' => 'The deck is being prepared. Please come back soon.']);
            $unseen = $cards->whereNotIn('id', $today->pluck('tarot_card_id')->all())->values();
            $pool = $unseen->isNotEmpty() ? $unseen : $cards->values();
            $card = $pool[random_int(0, $pool->count() - 1)];
            DB::table('tarot_draws')->insert([
                'user_id' => $user?->id, 'guest_id' => $user ? null : $guest,
                'tarot_card_id' => $card->id, 'request_token' => $requestToken,
                'draw_date' => now('Asia/Manila')->toDateString(), 'reading' => json_encode($card->reading(), JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 5);
    }
}
