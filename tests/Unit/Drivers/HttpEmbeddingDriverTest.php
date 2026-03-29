<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use Thaolaptrinh\Rag\Drivers\Embeddings\HttpEmbeddingDriver;
use Thaolaptrinh\Rag\Exceptions\ConfigurationException;
use Thaolaptrinh\Rag\Exceptions\EmbeddingFailedException;

it('throws ConfigurationException when api key is empty', function () {
    new HttpEmbeddingDriver(
        apiKey: '',
        model: 'text-embedding-3-small',
        dimensions: 1536,
        batchSize: 100,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/embeddings',
    );
})->throws(ConfigurationException::class, 'Configuration error: Embedding API key must not be empty');

it('embeds single text successfully', function () {
    Http::fake(fn () => Http::response(
        '{"data":[{"embedding":[0.1,0.2,0.3]}]}',
        200,
    ));

    $driver = new HttpEmbeddingDriver(
        apiKey: 'test-key',
        model: 'text-embedding-3-small',
        dimensions: 3,
        batchSize: 100,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/embeddings',
    );

    $result = $driver->embed('Hello world');

    expect($result)->toBe([0.1, 0.2, 0.3]);
});

it('embeds batch of texts successfully', function () {
    Http::fake(fn () => Http::response(
        '{"data":[{"embedding":[0.1,0.2]},{"embedding":[0.3,0.4]}]}',
        200,
    ));

    $driver = new HttpEmbeddingDriver(
        apiKey: 'test-key',
        model: 'text-embedding-3-small',
        dimensions: 2,
        batchSize: 100,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/embeddings',
    );

    $result = $driver->embedBatch(['Hello', 'World']);

    expect($result)->toHaveCount(2);
    expect($result[0])->toBe([0.1, 0.2]);
    expect($result[1])->toBe([0.3, 0.4]);
});

it('splits large batch into multiple requests', function () {
    $callCount = 0;

    Http::fake(function () use (&$callCount) {
        $callCount++;
        $n = $callCount === 1 ? 3 : 2;
        $embeddings = array_fill(0, $n, '{"embedding":[0.1,0.2]}');
        $body = '{"data":['.implode(',', $embeddings).']}';

        return Http::response($body, 200);
    });

    $driver = new HttpEmbeddingDriver(
        apiKey: 'test-key',
        model: 'text-embedding-3-small',
        dimensions: 2,
        batchSize: 3,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/embeddings',
    );

    $result = $driver->embedBatch(['a', 'b', 'c', 'd', 'e']);

    expect($result)->toHaveCount(5);
    expect($callCount)->toBe(2);
});

it('returns empty array for empty input', function () {
    $driver = new HttpEmbeddingDriver(
        apiKey: 'test-key',
        model: 'text-embedding-3-small',
        dimensions: 2,
        batchSize: 100,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/embeddings',
    );

    expect($driver->embedBatch([]))->toBeEmpty();
});

it('throws EmbeddingFailedException on 401 without retry', function () {
    $callCount = 0;

    Http::fake(function () use (&$callCount) {
        $callCount++;

        return Http::response('{"error":"Unauthorized"}', 401);
    });

    $driver = new HttpEmbeddingDriver(
        apiKey: 'bad-key',
        model: 'text-embedding-3-small',
        dimensions: 2,
        batchSize: 100,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/embeddings',
    );

    $driver->embed('test');
})->throws(EmbeddingFailedException::class, 'Authentication failed');

it('throws EmbeddingFailedException on 403 without retry', function () {
    $callCount = 0;

    Http::fake(function () use (&$callCount) {
        $callCount++;

        return Http::response('{"error":"Forbidden"}', 403);
    });

    $driver = new HttpEmbeddingDriver(
        apiKey: 'bad-key',
        model: 'text-embedding-3-small',
        dimensions: 2,
        batchSize: 100,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/embeddings',
    );

    $driver->embed('test');
})->throws(EmbeddingFailedException::class, 'Authentication failed');

it('retries on 429 rate limit', function () {
    $callCount = 0;

    Http::fake(function () use (&$callCount) {
        $callCount++;

        if ($callCount === 1) {
            return Http::response('{"error":"Rate limited"}', 429);
        }

        return Http::response('{"data":[{"embedding":[0.1,0.2]}]}', 200);
    });

    $driver = new HttpEmbeddingDriver(
        apiKey: 'test-key',
        model: 'text-embedding-3-small',
        dimensions: 2,
        batchSize: 100,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/embeddings',
    );

    $result = $driver->embed('test');

    expect($result)->toBe([0.1, 0.2]);
    expect($callCount)->toBe(2);
});

it('retries on 500 server error', function () {
    $callCount = 0;

    Http::fake(function () use (&$callCount) {
        $callCount++;

        if ($callCount === 1) {
            return Http::response('{"error":"Internal Server Error"}', 500);
        }

        return Http::response('{"data":[{"embedding":[0.1,0.2]}]}', 200);
    });

    $driver = new HttpEmbeddingDriver(
        apiKey: 'test-key',
        model: 'text-embedding-3-small',
        dimensions: 2,
        batchSize: 100,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/embeddings',
    );

    $result = $driver->embed('test');

    expect($result)->toBe([0.1, 0.2]);
    expect($callCount)->toBe(2);
});

it('throws after exhausting 429 retries', function () {
    Http::fake(fn () => Http::response('{"error":"Rate limited"}', 429));

    $driver = new HttpEmbeddingDriver(
        apiKey: 'test-key',
        model: 'text-embedding-3-small',
        dimensions: 2,
        batchSize: 100,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/embeddings',
    );

    $driver->embed('test');
})->throws(EmbeddingFailedException::class);

it('throws on invalid response structure', function () {
    Http::fake(fn () => Http::response('{"unexpected":"structure"}', 200));

    $driver = new HttpEmbeddingDriver(
        apiKey: 'test-key',
        model: 'text-embedding-3-small',
        dimensions: 2,
        batchSize: 100,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/embeddings',
    );

    $driver->embed('test');
})->throws(EmbeddingFailedException::class, 'Invalid response structure');

it('throws on invalid JSON response', function () {
    Http::fake(fn () => Http::response('not-json', 200));

    $driver = new HttpEmbeddingDriver(
        apiKey: 'test-key',
        model: 'text-embedding-3-small',
        dimensions: 2,
        batchSize: 100,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/embeddings',
    );

    $driver->embed('test');
})->throws(EmbeddingFailedException::class, 'Invalid JSON response');

it('returns configured dimensions', function () {
    $driver = new HttpEmbeddingDriver(
        apiKey: 'test-key',
        model: 'text-embedding-3-small',
        dimensions: 768,
        batchSize: 100,
        timeout: 120,
        apiUrl: 'https://api.openai.com/v1/embeddings',
    );

    expect($driver->dimensions())->toBe(768);
});
