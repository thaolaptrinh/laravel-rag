<?php

declare(strict_types=1);
use Thaolaptrinh\Rag\Contracts\EmbeddingDriver;
use Thaolaptrinh\Rag\Exceptions\RagException;

arch('services do not depend on concrete driver implementations')
    ->expect('Thaolaptrinh\Rag\Services')
    ->not->toUse('Thaolaptrinh\Rag\Drivers');

arch('drivers implement their corresponding contracts')
    ->expect('Thaolaptrinh\Rag\Drivers\Embeddings\HttpEmbeddingDriver')
    ->toImplement(EmbeddingDriver::class);

arch('all exceptions extend RagException')
    ->expect('Thaolaptrinh\Rag\Exceptions')
    ->toExtend(RagException::class);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();
