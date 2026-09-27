<?php

namespace App\Http\Controllers;

use App\Services\PhpRagAssistant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AssistantController extends Controller
{
    public function respond(Request $request, PhpRagAssistant $assistant)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:600'],
            'history' => ['nullable', 'array', 'max:4'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:600'],
        ]);
        $category = $assistant->category(trim($data['message']));
        $limit = $category === 'library' ? 5 : 25;
        $limitKey = 'assistant:'.$category.':daily:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($limitKey, $limit)) {
            $label = $category === 'library' ? 'five educational library questions' : '25 website-help questions';
            throw ValidationException::withMessages(['message' => "You have used today’s {$label}. Please come back tomorrow."]);
        }
        try {
            $result = $assistant->respond(trim($data['message']), $data['history'] ?? [], $category);
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 503);
        } catch (\Throwable $error) {
            Log::warning('Virtual guide request failed', ['user_id' => $request->user()->id, 'error_type' => get_class($error)]);
            return response()->json(['message' => 'The virtual guide is temporarily unavailable. Please try again shortly.'], 503);
        }
        RateLimiter::hit($limitKey, now()->diffInSeconds(now()->endOfDay()));
        return response()->json([...$result, 'usage' => ['category' => $category, 'used' => RateLimiter::attempts($limitKey), 'limit' => $limit]]);
    }
}
