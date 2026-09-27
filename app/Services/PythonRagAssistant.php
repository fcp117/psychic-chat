<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

class PythonRagAssistant
{
    public function respond(string $message, array $history): array
    {
        if (!config('assistant.enabled') || !config('services.together.key')) {
            throw new RuntimeException('The site guide is not configured yet.');
        }
        $url = config('assistant.rag_url') ?: 'http://127.0.0.1:8011/ask';
        try {
            $response = Http::acceptJson()->connectTimeout(2)->timeout((int) config('assistant.timeout', 12))
                ->post($url, ['message' => $message, 'history' => $history]);
        } catch (ConnectionException $error) {
            throw new RuntimeException('The Python library service is not reachable. Restart Intuition Island with .\\start.bat and try again.', previous: $error);
        }
        if (!$response->successful()) {
            throw new RuntimeException((string) ($response->json('message') ?: 'The site guide is temporarily unavailable.'));
        }
        return $response->json();
    }
}
