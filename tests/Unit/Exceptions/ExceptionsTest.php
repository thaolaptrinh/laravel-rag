<?php

declare(strict_types=1);

use Thaolaptrinh\Rag\Exceptions\EmbeddingFailedException;
use Thaolaptrinh\Rag\Exceptions\GenerationFailedException;
use Thaolaptrinh\Rag\Exceptions\RetrievalFailedException;
use Thaolaptrinh\Rag\Exceptions\StorageFailedException;

test('EmbeddingFailedException creates with reason', function (): void {
    $e = EmbeddingFailedException::create('API timeout');
    expect($e)->toBeInstanceOf(EmbeddingFailedException::class)
        ->and($e->getMessage())->toContain('API timeout');
});

test('GenerationFailedException creates with reason', function (): void {
    $e = GenerationFailedException::create('rate limit');
    expect($e)->toBeInstanceOf(GenerationFailedException::class)
        ->and($e->getMessage())->toContain('rate limit');
});

test('RetrievalFailedException creates with reason', function (): void {
    $e = RetrievalFailedException::create('no results');
    expect($e)->toBeInstanceOf(RetrievalFailedException::class)
        ->and($e->getMessage())->toContain('no results');
});

test('StorageFailedException creates with reason', function (): void {
    $e = StorageFailedException::create('connection failed');
    expect($e)->toBeInstanceOf(StorageFailedException::class)
        ->and($e->getMessage())->toContain('connection failed');
});
