<?php

declare(strict_types=1);

use Thaolaptrinh\Rag\Services\TextChunker;

test('splits long text into multiple chunks', function (): void {
    $chunker = new TextChunker(maxChunkSize: 50, overlap: 10);
    $document = ['id' => 'doc-1', 'content' => str_repeat('word ', 30), 'metadata' => []];
    $chunks = $chunker->chunk($document);
    expect(count($chunks))->toBeGreaterThan(1);
});

test('each chunk has required keys', function (): void {
    $chunker = new TextChunker(maxChunkSize: 100, overlap: 20);
    $document = ['id' => 'doc-1', 'content' => 'Hello world. This is a test document.', 'metadata' => []];
    $chunks = $chunker->chunk($document);
    foreach ($chunks as $chunk) {
        expect($chunk)->toHaveKeys(['id', 'content', 'metadata', 'index']);
    }
});

test('chunk metadata inherits parent metadata', function (): void {
    $chunker = new TextChunker(maxChunkSize: 500, overlap: 0);
    $document = ['id' => 'doc-abc', 'content' => 'Some content here.', 'metadata' => ['source' => 'file.txt', 'author' => 'test']];
    $chunks = $chunker->chunk($document);
    expect($chunks[0]['metadata']['source'])->toEqual('file.txt')
        ->and($chunks[0]['metadata']['parent_id'])->toEqual('doc-abc');
});

test('short text produces single chunk', function (): void {
    $chunker = new TextChunker(maxChunkSize: 1000, overlap: 0);
    $document = ['id' => 'doc-1', 'content' => 'Short text.', 'metadata' => []];
    $chunks = $chunker->chunk($document);
    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]['index'])->toEqual(0);
});

test('getMaxChunkSize and getOverlap return configured values', function (): void {
    $chunker = new TextChunker(maxChunkSize: 750, overlap: 50);
    expect($chunker->getMaxChunkSize())->toEqual(750)
        ->and($chunker->getOverlap())->toEqual(50);
});
