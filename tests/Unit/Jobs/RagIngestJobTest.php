<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Tests\Unit\Jobs;

use Thaolaptrinh\Rag\Jobs\RagIngestJob;

it('has correct retry configuration with default timeout', function () {
    $job = new RagIngestJob(
        documentId: 'doc-123',
        documentContent: 'Test content',
        documentMetadata: ['source' => 'test'],
    );

    expect($job->tries)->toBe(3);
    expect($job->timeout)->toBe(600);

    $retryUntil = $job->retryUntil();
    expect($retryUntil)->toBeInstanceOf(\DateTimeInterface::class);
    expect($retryUntil->getTimestamp())->toBeGreaterThan(time());
});

it('accepts custom queue name', function () {
    $job = new RagIngestJob(
        documentId: 'doc-123',
        documentContent: 'Test content',
        documentMetadata: ['source' => 'test'],
        queue: 'rag-embeddings',
    );

    expect($job->queue)->toBe('rag-embeddings');
});

it('stores document properties correctly', function () {
    $job = new RagIngestJob(
        documentId: 'doc-123',
        documentContent: 'Test content',
        documentMetadata: ['source' => 'test', 'page' => 1],
    );

    $reflection = new \ReflectionClass($job);
    $property = $reflection->getProperty('documentId');
    $property->setAccessible(true);

    expect($property->getValue($job))->toBe('doc-123');
});

it('constructs with default null queue', function () {
    $job = new RagIngestJob(
        documentId: 'doc-123',
        documentContent: 'Test content',
        documentMetadata: [],
    );

    expect($job->queue)->toBeNull();
});
