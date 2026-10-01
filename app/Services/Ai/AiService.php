<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI-compatible chat service.
 * Works with Groq (default), DeepSeek, or any OpenAI-compatible endpoint.
 *
 * Strategy:
 *  - First call is NON-streaming so we can cleanly detect tool_calls vs content.
 *  - Follow-up calls after tool results are also non-streaming (fast on Groq).
 *  - $onChunk is called once with the full content so the Livewire component
 *    gets its update the same way regardless of streaming mode.
 */
class AiService
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private int    $maxTokens;
    private float  $temperature;
    private int    $timeout;

    public function __construct()
    {
        $this->apiKey      = (string) config('ai.api_key');
        $this->baseUrl     = rtrim((string) config('ai.base_url', 'https://api.groq.com/openai/v1'), '/');
        $this->model       = (string) config('ai.model', 'llama-3.3-70b-versatile');
        $this->maxTokens   = (int) config('ai.max_tokens', 1024);
        $this->temperature = (float) config('ai.temperature', 0.3);
        $this->timeout     = (int) config('ai.timeout', 30);
    }

    /**
     * Run a full chat completion with optional function-calling loop.
     *
     * Uses non-streaming mode so tool_calls are returned cleanly.
     * $onChunk is called once with the complete text when content is ready.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools   OpenAI-compatible tool definitions
     * @param  callable(string): void             $onChunk Called with each streamed text chunk
     * @return array{content: string, tool_calls: list<array<string,mixed>>}
     */
    public function chat(array $messages, array $tools, callable $onChunk): array
    {
        $payload = [
            'model'       => $this->model,
            'messages'    => $messages,
            'max_tokens'  => $this->maxTokens,
            'temperature' => $this->temperature,
        ];

        if ($tools !== []) {
            $payload['tools']       = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::withToken($this->apiKey)
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/chat/completions", $payload);

        if (! $response->successful()) {
            $error = $response->json('error.message') ?? $response->body();
            Log::error('[AiService] API error', [
                'status' => $response->status(),
                'error'  => $error,
            ]);
            throw new \RuntimeException("AI API error ({$response->status()}): {$error}");
        }

        $message   = $response->json('choices.0.message') ?? [];
        $content   = (string) ($message['content'] ?? '');
        $toolCalls = $message['tool_calls'] ?? [];

        // Log token usage for monitoring
        $usage = $response->json('usage') ?? [];
        if ($usage !== []) {
            Log::info('[AiService] token usage', [
                'model'             => $this->model,
                'prompt_tokens'     => $usage['prompt_tokens'] ?? 0,
                'completion_tokens' => $usage['completion_tokens'] ?? 0,
                'total_tokens'      => $usage['total_tokens'] ?? 0,
            ]);
        }

        // Deliver content to Livewire via the chunk callback
        if ($content !== '') {
            $onChunk($content);
        }

        return [
            'content'           => $content,
            'tool_calls'        => is_array($toolCalls) ? array_values($toolCalls) : [],
            'prompt_tokens'     => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
        ];

    }

    /**
     * Simple non-streaming call — returns the full content string.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function ask(array $messages): string
    {
        $response = Http::withToken($this->apiKey)
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/chat/completions", [
                'model'       => $this->model,
                'messages'    => $messages,
                'max_tokens'  => $this->maxTokens,
                'temperature' => $this->temperature,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "AI API error ({$response->status()}): " .
                ($response->json('error.message') ?? $response->body())
            );
        }

        return (string) ($response->json('choices.0.message.content') ?? '');
    }
}
