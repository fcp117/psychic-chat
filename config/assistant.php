<?php

return [
    // Keep disabled until a valid Together API key is configured privately in .env.
    'enabled' => env('ASSISTANT_ENABLED', false),
    'model' => env('TOGETHER_ASSISTANT_MODEL', 'Qwen/Qwen3.5-9B'),
    'timeout' => env('ASSISTANT_TIMEOUT_SECONDS', 12),
    'max_output_tokens' => env('ASSISTANT_MAX_OUTPUT_TOKENS', 220),
    'rag_url' => env('ASSISTANT_RAG_URL', 'http://127.0.0.1:8011/ask'),
];
