<?php
namespace App\Services;
use App\Models\ChatSession;
use App\Models\CoachReview;
class CoachFeedback {
    public function eligible(ChatSession $session): bool {
        return $session->status === 'completed' && $session->started_at && $session->ended_at
            && $session->ended_at->gt($session->started_at) && $session->ended_at->lte(now())
            && $session->ended_at->copy()->addHours(config('reviews.window_hours'))->gt(now());
    }
    public function summary(int $coach): array {
        $query = CoachReview::where('counselor_id', $coach);
        return ['average' => round((float) $query->avg('rating'), 1), 'count' => $query->count()];
    }
}
