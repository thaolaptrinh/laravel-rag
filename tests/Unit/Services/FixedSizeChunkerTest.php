<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Tests\Unit\Services;

use Thaolaptrinh\Rag\Data\Document;
use Thaolaptrinh\Rag\Exceptions\ChunkingFailedException;
use Thaolaptrinh\Rag\Services\Chunking\FixedSizeChunker;

it('returns empty array for empty content', function (): void {
    $chunks = (new FixedSizeChunker(100, 0))->split(
        Document::create('', ['source' => 'test']),
    );

    expect($chunks)->toBeEmpty();
});

it('returns single chunk for short content', function (): void {
    $chunks = (new FixedSizeChunker(100, 0))->split(
        Document::create('Hello world', ['source' => 'test']),
    );

    expect($chunks)->toHaveCount(1);
    expect($chunks[0]->index)->toBe(0);
});

it('splits long content into multiple chunks', function (): void {
    $content = str_repeat('a', 250);
    $chunks = (new FixedSizeChunker(100, 0))->split(
        Document::create($content),
    );

    expect($chunks)->toHaveCount(3);
    expect($chunks[0]->content)->toBe(str_repeat('a', 100));
    expect($chunks[1]->content)->toBe(str_repeat('a', 100));
    expect($chunks[2]->content)->toBe(str_repeat('a', 50));
});

it('applies overlap between chunks', function (): void {
    $content = str_repeat('a', 100).str_repeat('b', 100).str_repeat('c', 100);
    $chunks = (new FixedSizeChunker(100, 20))->split(
        Document::create($content),
    );

    expect($chunks)->toHaveCount(4);
    expect($chunks[0]->content)->toBe(str_repeat('a', 100));

    $overlapEnd = mb_substr($chunks[0]->content, 80);
    $overlapStart = mb_substr($chunks[1]->content, 0, 20);
    expect($overlapStart)->toBe($overlapEnd);
});

it('verifies overlap region is correct', function (): void {
    $content = str_repeat('a', 150);
    $chunks = (new FixedSizeChunker(100, 50))->split(
        Document::create($content),
    );

    expect($chunks)->toHaveCount(2);

    $tailOfFirst = mb_substr($chunks[0]->content, 50);
    $headOfSecond = mb_substr($chunks[1]->content, 0, 50);
    expect($headOfSecond)->toBe($tailOfFirst);
});

it('inherits document metadata and adds chunk-specific metadata', function (): void {
    $content = str_repeat('a', 200);
    $chunks = (new FixedSizeChunker(100, 0))->split(
        Document::create($content, ['source' => 'pdf', 'file_id' => 42]),
    );

    expect($chunks)->toHaveCount(2);

    expect($chunks[0]->metadata['source'])->toBe('pdf');
    expect($chunks[0]->metadata['file_id'])->toBe(42);
    expect($chunks[0]->metadata['chunk_index'])->toBe(0);
    expect($chunks[0]->metadata['chunk_start'])->toBe(0);
    expect($chunks[0]->metadata['chunk_end'])->toBe(100);

    expect($chunks[1]->metadata['source'])->toBe('pdf');
    expect($chunks[1]->metadata['chunk_index'])->toBe(1);
    expect($chunks[1]->metadata['chunk_start'])->toBe(100);
    expect($chunks[1]->metadata['chunk_end'])->toBe(200);
});

it('throws when overlap is greater than or equal to chunk size', function (): void {
    new FixedSizeChunker(100, 100);
})->throws(ChunkingFailedException::class);

it('throws when overlap is greater than chunk size', function (): void {
    new FixedSizeChunker(50, 100);
})->throws(ChunkingFailedException::class);

it('allows zero overlap', function (): void {
    $chunks = (new FixedSizeChunker(100, 0))->split(
        Document::create(str_repeat('a', 300)),
    );

    expect($chunks)->toHaveCount(3);
});

it('produces deterministic chunk IDs', function (): void {
    $content = str_repeat('a', 200);
    $chunks = (new FixedSizeChunker(100, 0))->split(
        Document::create($content, [], 'doc-123'),
    );

    expect($chunks[0]->id)->toBe('doc-123::chunk::0');
    expect($chunks[1]->id)->toBe('doc-123::chunk::1');
});

it('getters return configured values', function (): void {
    $chunker = new FixedSizeChunker(500, 100);

    expect($chunker->getChunkSize())->toBe(500);
    expect($chunker->getOverlap())->toBe(100);
});
