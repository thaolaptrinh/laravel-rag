<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use Thaolaptrinh\Rag\Drivers\Llm\HttpLlmDriver;
use Thaolaptrinh\Rag\Exceptions\ConfigurationException;
use Thaolaptrinh\Rag\Exceptions\GenerationFailedException;

it('throws ConfigurationException when api key is empty', function (): void {
    new HttpLlmDriver(
        apiKey: '',
        model: 'gpt-4o-mini',
        maxOutputTokens: 4096,
        contextWindow: 128000,
        temperature: 0.7,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/chat/completions',
    );
})->throws(ConfigurationException::class);

it('generates response successfully', function (): void {
    Http::fake(fn () => Http::response(
        '{"choices":[{"message":{"content":"Hello from LLM"}}]}',
        200,
    ));

    $driver = new HttpLlmDriver(
        apiKey: 'test-key',
        model: 'gpt-4o-mini',
        maxOutputTokens: 4096,
        contextWindow: 128000,
        temperature: 0.7,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/chat/completions',
    );

    $result = $driver->generate('Say hello');

    expect($result)->toBe('Hello from LLM');
});

it('generate delegates to generateWithSystem with default system prompt', function (): void {
    Http::fake(fn () => Http::response(
        '{"choices":[{"message":{"content":"default system"}}]}',
        200,
    ));

    $driver = new HttpLlmDriver(
        apiKey: 'test-key',
        model: 'gpt-4o-mini',
        maxOutputTokens: 4096,
        contextWindow: 128000,
        temperature: 0.7,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/chat/completions',
    );

    $result = $driver->generate('Test');

    expect($result)->toBe('default system');
});

it('generates with custom system message', function (): void {
    Http::fake(fn () => Http::response(
        '{"choices":[{"message":{"content":"Custom response"}}]}',
        200,
    ));

    $driver = new HttpLlmDriver(
        apiKey: 'test-key',
        model: 'gpt-4o-mini',
        maxOutputTokens: 4096,
        contextWindow: 128000,
        temperature: 0.7,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/chat/completions',
    );

    $result = $driver->generateWithSystem('Be concise.', 'Explain X');

    expect($result)->toBe('Custom response');
});

it('streams tokens via callback', function (): void {
    $sseBody = implode("\n", [
        'data: {"choices":[{"delta":{"content":"Hello"}}]}',
        'data: {"choices":[{"delta":{"content":" world"}}]}',
        'data: [DONE]',
        '',
    ]);

    Http::fake(fn () => Http::response($sseBody, 200));

    $driver = new HttpLlmDriver(
        apiKey: 'test-key',
        model: 'gpt-4o-mini',
        maxOutputTokens: 4096,
        contextWindow: 128000,
        temperature: 0.7,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/chat/completions',
    );

    $tokens = [];
    $driver->generateStream('Test', function (string $token) use (&$tokens): void {
        $tokens[] = $token;
    });

    expect($tokens)->toBe(['Hello', ' world']);
});

it('throws on 401 auth failure', function (): void {
    Http::fake(fn () => Http::response('{"error":"Unauthorized"}', 401));

    $driver = new HttpLlmDriver(
        apiKey: 'bad-key',
        model: 'gpt-4o-mini',
        maxOutputTokens: 4096,
        contextWindow: 128000,
        temperature: 0.7,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/chat/completions',
    );

    $driver->generate('test');
})->throws(GenerationFailedException::class, 'Authentication failed');

it('throws on invalid response structure', function (): void {
    Http::fake(fn () => Http::response('{"unexpected":"data"}', 200));

    $driver = new HttpLlmDriver(
        apiKey: 'test-key',
        model: 'gpt-4o-mini',
        maxOutputTokens: 4096,
        contextWindow: 128000,
        temperature: 0.7,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/chat/completions',
    );

    $driver->generate('test');
})->throws(GenerationFailedException::class, 'Invalid response structure');

it('returns configured context window and max output tokens', function (): void {
    $driver = new HttpLlmDriver(
        apiKey: 'test-key',
        model: 'gpt-4o-mini',
        maxOutputTokens: 8192,
        contextWindow: 200000,
        temperature: 0.7,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/chat/completions',
    );

    expect($driver->getContextWindow())->toBe(200000);
    expect($driver->getMaxOutputTokens())->toBe(8192);
});
