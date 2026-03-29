<?php

declare(strict_types=1);

use Thaolaptrinh\Rag\Contracts\Chunker;
use Thaolaptrinh\Rag\Contracts\ContextEnricher;
use Thaolaptrinh\Rag\Contracts\EmbeddingDriver;
use Thaolaptrinh\Rag\Contracts\LlmDriver;
use Thaolaptrinh\Rag\Contracts\PromptBuilder;
use Thaolaptrinh\Rag\Contracts\Retriever;
use Thaolaptrinh\Rag\Contracts\VectorStore;
use Thaolaptrinh\Rag\Exceptions\RagException;

arch('services do not depend on concrete driver implementations')
    ->expect('Thaolaptrinh\Rag\Services')
    ->not->toUse('Thaolaptrinh\Rag\Drivers');

arch('all exceptions extend RagException')
    ->expect('Thaolaptrinh\Rag\Exceptions')
    ->toExtend(RagException::class);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('contracts are interfaces')
    ->expect([
        Chunker::class,
        ContextEnricher::class,
        EmbeddingDriver::class,
        LlmDriver::class,
        PromptBuilder::class,
        Retriever::class,
        VectorStore::class,
    ])->toBeInterfaces();
