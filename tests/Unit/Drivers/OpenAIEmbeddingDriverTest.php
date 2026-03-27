<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Thaolaptrinh\Rag\Drivers\Embeddings\OpenAIEmbeddingDriver;
use Thaolaptrinh\Rag\Exceptions\EmbeddingFailedException;

test('embed returns a float array of the correct dimension', function (): void {
    Http::fake([
        '*' => Http::response(['data' => [['embedding' => array_fill(0, 1536, 0.1)]]], 200),
    ]);
    $driver = new OpenAIEmbeddingDriver(apiKey: 'test-key', model: 'text-embedding-3-small', dimension: 1536);
    $embedding = $driver->embed('Hello world');
    expect($embedding)->toBeArray()->and(count($embedding))->toEqual(1536);
});

test('embedBatch returns embeddings for all inputs', function (): void {
    Http::fake([
        '*' => Http::response(['data' => [['embedding' => array_fill(0, 1536, 0.1)], ['embedding' => array_fill(0, 1536, 0.2)]]], 200),
    ]);
    $driver = new OpenAIEmbeddingDriver(apiKey: 'test-key', model: 'text-embedding-3-small', dimension: 1536);
    $embeddings = $driver->embedBatch(['text one', 'text two']);
    expect($embeddings)->toHaveCount(2);
});

test('throws EmbeddingFailedException on API error', function (): void {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Unauthorized']], 401)]);
    $driver = new OpenAIEmbeddingDriver(apiKey: 'bad-key', model: 'text-embedding-3-small', dimension: 1536);
    expect(fn () => $driver->embed('test'))->toThrow(EmbeddingFailedException::class);
});

test('getDimension returns configured dimension', function (): void {
    $driver = new OpenAIEmbeddingDriver(apiKey: 'key', model: 'model', dimension: 768);
    expect($driver->getDimension())->toEqual(768);
});
