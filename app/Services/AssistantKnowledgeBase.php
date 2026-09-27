<?php

namespace App\Services;

class AssistantKnowledgeBase
{
    /**
     * This is deliberately a closed, reviewed corpus. It does not include chat
     * messages, profiles, account data, or anything submitted by other users.
     */
    public function retrieve(string $question, int $limit = 3): array
    {
        $terms = $this->terms($question);
        $matches = collect($this->documents())
            ->map(function (array $document) use ($terms) {
                $haystack = $this->terms($document['title'].' '.$document['keywords'].' '.$document['content']);
                $score = count(array_intersect($terms, $haystack));
                return $document + ['score' => $score];
            })
            ->filter(fn (array $document) => $document['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();

        return $matches;
    }

    public function documents(): array
    {
        return [
            ['title' => 'What the site guide can do', 'keywords' => 'assistant guide help support feature', 'content' => 'The Intuition Island assistant is a free site guide. It can explain how the platform works, including accounts, spiritual advisors, readings, credits, and safety. It is not a live spiritual advisor and does not provide readings or personal spiritual guidance.'],
            ['title' => 'Finding a spiritual advisor', 'keywords' => 'find counselor browse profile request reading rate', 'content' => 'Verified User accounts can open Find a Spiritual Advisor, review approved spiritual advisor profiles, specialties, languages, availability, and hourly credit rate, then request a reading. The counselor must accept before a paid reading starts.'],
            ['title' => 'Reading billing', 'keywords' => 'billing credits cost rate charge price disconnect end active', 'content' => 'Before requesting a reading, a User reviews the spiritual advisor rate and agrees to it. Billing begins only after the spiritual advisor accepts. Charges use confirmed elapsed time and the agreed rate. A reading can end when either participant ends it, a participant is inactive, or the User has insufficient credits.'],
            ['title' => 'Credits and payments', 'keywords' => 'credits buy purchase payment balance refund', 'content' => 'Credits are used for paid readings. The Credits page shows the available balance and purchase options. Payment status is confirmed by the provider before credits are added. Administrators can record approved adjustments and reading-credit refunds.'],
            ['title' => 'Accounts and verification', 'keywords' => 'account register login email otp verification password reset profile', 'content' => 'New accounts must verify their email with a six-digit code before using protected features. Users can correct an email address by confirming their current password. Password reset and profile management are available from the account screens.'],
            ['title' => 'Spiritual Advisor applications', 'keywords' => 'become spiritual advisor application approval qualifications earnings', 'content' => 'Verified Users can apply to become a spiritual advisor. An administrator reviews the application. Once approved, the account gains counselor access, appears in Find a Spiritual Advisor, and can accept reading requests. Displayed earnings are platform credits; cash payouts are not currently provided by the platform.'],
            ['title' => 'Privacy and safety', 'keywords' => 'privacy safety emergency medical legal financial crisis personal advice', 'content' => 'Do not share passwords, payment details, identification documents, or other highly sensitive information in the site guide. The guide is not medical, legal, financial, crisis, or emergency support. For immediate danger or a mental-health crisis, contact local emergency services or a qualified crisis service.'],
        ];
    }

    private function terms(string $value): array
    {
        $stopWords = ['a','an','and','are','as','at','be','by','can','do','for','from','how','i','in','is','it','me','my','of','on','or','please','the','to','what','with','you','your'];
        preg_match_all('/[a-z0-9]+/i', strtolower($value), $matches);
        return array_values(array_unique(array_filter($matches[0], fn (string $term) => strlen($term) > 1 && !in_array($term, $stopWords, true))));
    }
}
