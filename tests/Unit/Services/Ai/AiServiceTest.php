<?php

use App\Services\Ai\AiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

uses(Tests\TestCase::class);

it('retries a completion after the provider rate limit delay', function () {
    config()->set('ai.base_url', 'https://api.groq.com/openai/v1');
    Http::preventStrayRequests();
    Http::fake([
        'https://api.groq.com/openai/v1/chat/completions' => Http::sequence()
            ->push(['error' => ['message' => 'Rate limit reached. Please try again in 0.001s.']], 429)
            ->push([
                'choices' => [['message' => ['content' => 'Hello!']]],
                'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 1],
            ]),
    ]);
    Sleep::fake();

    $chunks = [];
    try {
        $result = app(AiService::class)->chat(
            [['role' => 'user', 'content' => 'hi']],
            [],
            function (string $chunk) use (&$chunks): void {
                $chunks[] = $chunk;
            },
        );

        expect($result['content'])->toBe('Hello!')
            ->and($chunks)->toBe(['Hello!']);
        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
    } finally {
        Sleep::fake(false);
    }
});
