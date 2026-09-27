<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PhpRagAssistant
{
    private ?array $corpus = null;

    public function category(string $question): string
    {
        return preg_match('/\b(site|website|account|log ?in|password|profile|counselor|find.*counselor|credits?|billing|rate|payment|verify|verification|email|register|sign ?up|how.*(?:use|work)|chat)\b/i', $question)
            ? 'website'
            : 'library';
    }

    public function respond(string $question, array $history = [], ?string $category = null): array
    {
        if (!config('assistant.enabled') || !config('services.together.key')) throw new RuntimeException('The site guide is not configured yet.');
        if ($this->isCrisis($question)) return ['reply' => 'I’m not able to help with an emergency or crisis. If you may be in immediate danger, contact your local emergency number now or a qualified crisis service in your area.', 'sources' => [], 'fallback' => true];
        $category ??= $this->category($question);
        if ($category === 'website') return ['reply' => $this->websiteAnswer($question), 'sources' => [], 'fallback' => true];
        if ($this->requestsPersonalReading($question)) return ['reply' => 'I can’t perform a personal reading, make predictions, or interpret your situation. I can share general educational information from the approved library. For personal guidance, please use Find a Spiritual Advisor.', 'sources' => [], 'fallback' => true];
        $passages = $this->retrieve($question);
        if (!$passages) return ['reply' => 'I can share general educational information from the approved Intuition Island library. I can’t provide a personal reading, prediction, or replace a live spiritual advisor. For personal guidance, please use Find a Spiritual Advisor.', 'sources' => [], 'fallback' => true];

        $context = collect($passages)->map(fn ($p, $i) => '['.($i + 1).'] Source: '.$p['source'].', page '.$p['page']."\n".$p['content'])->join("\n\n");
        $messages = [['role' => 'system', 'content' => "You are the Intuition Island educational library guide. Answer only from the numbered approved excerpts. Never provide a personal reading, prediction, diagnosis, medical, legal, financial, relationship, or crisis advice. Do not claim spiritual certainty. For personal guidance, direct the user to Find a Spiritual Advisor. Keep answers under 120 words. Cite each paragraph with the excerpt number, for example [1].\n\nAPPROVED EXCERPTS:\n".$context]];
        foreach (array_slice($history, -4) as $message) $messages[] = ['role' => $message['role'], 'content' => $message['content']];
        $messages[] = ['role' => 'user', 'content' => $question];
        $response = Http::acceptJson()->withToken(config('services.together.key'))->connectTimeout(3)->timeout((int) config('assistant.timeout', 12))->post('https://api.together.ai/v1/chat/completions', ['model' => config('assistant.model'), 'messages' => $messages, 'temperature' => 0.2, 'max_tokens' => (int) config('assistant.max_output_tokens', 220), 'reasoning' => ['enabled' => false]]);
        if (!$response->successful()) throw new RuntimeException('Together AI rejected the request (HTTP '.$response->status().').');
        $reply = trim((string) data_get($response->json(), 'choices.0.message.content'));
        if ($reply === '') throw new RuntimeException('Together AI returned an empty response.');
        return ['reply' => $reply, 'sources' => array_map(fn ($p) => $p['source'].' (page '.$p['page'].')', $passages), 'fallback' => false];
    }

    private function retrieve(string $question): array
    {
        $terms = $this->terms($question);
        if (!$terms) return [];
        return collect($this->corpus())->map(function ($passage) use ($terms) { $passage['score'] = count(array_intersect($terms, $this->terms($passage['content']))); return $passage; })->filter(fn ($p) => $p['score'] > 0)->sortByDesc('score')->take(3)->values()->all();
    }
    private function corpus(): array { return $this->corpus ??= json_decode(file_get_contents(base_path('rag/corpus.json')), true, 512, JSON_THROW_ON_ERROR); }
    private function terms(string $value): array { preg_match_all('/[a-z0-9]+/i', strtolower($value), $m); $stop=['a','an','and','are','as','at','be','by','can','do','for','from','how','i','in','is','it','me','my','of','on','or','please','the','to','what','with','you','your']; return array_values(array_unique(array_filter($m[0], fn ($t) => strlen($t)>1 && !in_array($t,$stop,true)))); }
    private function isCrisis(string $value): bool { return (bool) preg_match('/\b(suicid|kill myself|self harm|hurt myself|end my life|immediate danger)\b/i', $value); }
    private function requestsPersonalReading(string $value): bool { return (bool) preg_match('/\b(read me|give me (?:a )?(?:psychic )?reading|(?:do|perform|want|need).{0,24}(?:psychic )?reading|my future|will i|will we|do they love|should i|predict|tell me (?:what|if)|relationship|fortune)\b/i', $value); }

    private function websiteAnswer(string $question): string
    {
        if (preg_match('/\b(find|choose|look for).{0,30}counselor|\bcounselor.{0,30}(find|choose)/i', $question)) return 'Open Find a Spiritual Advisor from the navigation, search by name, then open a profile to review their details and rate. Choose Request chat when you are ready.';
        if (preg_match('/\b(bill|billing|charge|rate|cost|price)\b/i', $question)) return 'Each counselor’s rate is shown before you request a chat. Billing begins only after the spiritual advisor accepts. Confirmed elapsed time is charged in credits; disconnected time is not charged.';
        if (preg_match('/\b(credit|payment|pay)\b/i', $question)) return 'Credits are the balance used for spiritual advisor chats. You can view your balance and available credit options from Credits. Payment options marked unavailable are not connected yet.';
        if (preg_match('/\b(verify|verification|email)\b/i', $question)) return 'You need to verify your email before using member features. Check your inbox and spam folder for the verification message, then use the link inside it.';
        if (preg_match('/\b(password|log ?in|sign ?in)\b/i', $question)) return 'Use the Forgot your password? link on the sign-in page to request a password-reset email. Keep your account details private.';
        if (preg_match('/\b(profile|photo|account)\b/i', $question)) return 'Open your profile from the account menu to update your account details or profile photo. Your birthdate is used for age eligibility and is not shown publicly.';
        if (preg_match('/\b(become|apply).{0,30}counselor|\bspiritual advisor application\b/i', $question)) return 'You can submit a spiritual advisor application from Become a Spiritual Advisor. An administrator reviews applications before a spiritual advisor appears in the directory.';
        if (preg_match('/\b(chat|reading)\b/i', $question)) return 'Request a chat from a spiritual advisor’s profile or Find a Spiritual Advisor. The counselor must accept before it begins. Either participant can end it, and the session also ends if credits run out or either side is inactive or disconnected.';
        return 'I can help with how Intuition Island works: accounts, email verification, finding a spiritual advisor, chat requests, credits, billing, or profiles. For a personal reading, please use Find a Spiritual Advisor.';
    }
}
