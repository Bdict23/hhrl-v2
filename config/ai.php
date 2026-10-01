<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| AI Chat Assistant Configuration (Groq backend)
|--------------------------------------------------------------------------
| Groq is OpenAI-API-compatible, so the same service class works.
| Models available on this account that support function calling:
|   openai/gpt-oss-20b   — fast, cheap, tool-capable ✅ (default)
|   openai/gpt-oss-120b  — smarter, slightly more expensive
|   qwen/qwen3.8-27b     — Alibaba Qwen, tool-capable
*/

return [

    'api_key'  => env('GROQ_API_KEY', ''),
    'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
    'model'    => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),

    /*
     * Maximum tokens for the completion response.
     */
    'max_tokens' => (int) env('GROQ_MAX_TOKENS', 1024),

    /*
     * Temperature: 0 = deterministic/factual, 1 = creative.
     */
    'temperature' => (float) env('GROQ_TEMPERATURE', 0.3),

    /*
     * HTTP timeout for streaming requests (seconds).
     * Groq is very fast so 30s is plenty.
     */
    'timeout' => 30,

];
