<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Thaolaptrinh\Rag\Drivers\Llm\OpenAILlmDriver;
use Thaolaptrinh\Rag\Exceptions\GenerationFailedException;

test('generate returns a string response', function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'The answer.']]]], 200)]);
    $driver = new OpenAILlmDriver(apiKey: 'test-key', model: 'gpt-4o-mini');
    expect($driver->generate('What is RAG?'))->toEqual('The answer.');
});

test('generateWithSystem includes system message', function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'Response.']]]], 200)]);
    $driver = new OpenAILlmDriver(apiKey: 'test-key', model: 'gpt-4o-mini');
    $result = $driver->generateWithSystem('You are a helpful assistant.', 'What is RAG?');
    expect($result)->toBeString();
    Http::assertSent(fn ($request) => collect($request->data()['messages'])->contains('role', 'system'));
});

test('throws GenerationFailedException on API error', function (): void {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Rate limit']], 429)]);
    $driver = new OpenAILlmDriver(apiKey: 'test-key', model: 'gpt-4o-mini');
    expect(fn () => $driver->generate('test'))->toThrow(GenerationFailedException::class);
});

test('getMaxTokens returns configured value', function (): void {
    $driver = new OpenAILlmDriver(apiKey: 'key', model: 'gpt-4o-mini', maxTokens: 2048);
    expect($driver->getMaxTokens())->toEqual(2048);
});
