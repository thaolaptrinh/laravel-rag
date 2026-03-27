<?php

declare(strict_types=1);

use Thaolaptrinh\Rag\Contracts\Chunker;
use Thaolaptrinh\Rag\Contracts\DataSource;
use Thaolaptrinh\Rag\Contracts\EmbeddingDriver;
use Thaolaptrinh\Rag\Contracts\VectorStore;
use Thaolaptrinh\Rag\Services\IngestionPipeline;
use Thaolaptrinh\Rag\Services\Logging\RagLogger;

function makePipeline(DataSource $source, Chunker $chunker, EmbeddingDriver $embedder, VectorStore $store): IngestionPipeline
{
    $logger = Mockery::mock(RagLogger::class);
    $logger->shouldIgnoreMissing();
    return new IngestionPipeline($source, $chunker, $embedder, $store, $logger);
}

test('ingest returns stats with stored count', function (): void {
    $source = Mockery::mock(DataSource::class);
    $source->shouldReceive('load')->once()->andReturn([
        ['id' => 'doc-1', 'content' => 'Hello world', 'metadata' => []],
    ]);
    $chunker = Mockery::mock(Chunker::class);
    $chunker->shouldReceive('chunk')->once()->andReturn([
        ['id' => 'c-1', 'content' => 'Hello world', 'metadata' => [], 'index' => 0],
    ]);
    $embedder = Mockery::mock(EmbeddingDriver::class);
    $embedder->shouldReceive('embedBatch')->once()->andReturn([array_fill(0, 1536, 0.1)]);
    $store = Mockery::mock(VectorStore::class);
    $store->shouldReceive('storeMany')->once();

    $stats = makePipeline($source, $chunker, $embedder, $store)->ingest('/path/to/file.txt');

    expect($stats)->toHaveKeys(['stored', 'errors', 'source'])
        ->and($stats['stored'])->toBeGreaterThanOrEqual(1)
        ->and($stats['errors'])->toEqual(0);
});

test('ingest calls storeMany with embedding and chunk data', function (): void {
    $source = Mockery::mock(DataSource::class);
    $source->shouldReceive('load')->andReturn([
        ['id' => 'doc-1', 'content' => 'Test', 'metadata' => []],
    ]);
    $chunker = Mockery::mock(Chunker::class);
    $chunker->shouldReceive('chunk')->andReturn([
        ['id' => 'c-1', 'content' => 'Test', 'metadata' => [], 'index' => 0],
    ]);
    $embedder = Mockery::mock(EmbeddingDriver::class);
    $embedder->shouldReceive('embedBatch')->andReturn([array_fill(0, 1536, 0.5)]);
    $store = Mockery::mock(VectorStore::class);
    $store->shouldReceive('storeMany')->once()->withArgs(function (array $items): bool {
        return count($items) > 0;
    });

    makePipeline($source, $chunker, $embedder, $store)->ingest('test.txt');
});

test('ingest returns errors when embedder throws', function (): void {
    $source = Mockery::mock(DataSource::class);
    $source->shouldReceive('load')->andReturn([
        ['id' => 'doc-1', 'content' => 'Test', 'metadata' => []],
    ]);
    $chunker = Mockery::mock(Chunker::class);
    $chunker->shouldReceive('chunk')->andReturn([
        ['id' => 'c-1', 'content' => 'Test', 'metadata' => [], 'index' => 0],
    ]);
    $embedder = Mockery::mock(EmbeddingDriver::class);
    $embedder->shouldReceive('embedBatch')->andThrow(new \RuntimeException('API error'));
    $store = Mockery::mock(VectorStore::class);
    $store->shouldNotReceive('storeMany');

    $stats = makePipeline($source, $chunker, $embedder, $store)->ingest('test.txt');
    expect($stats['errors'])->toBeGreaterThan(0);
});
