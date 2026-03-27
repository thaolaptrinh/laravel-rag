<?php

declare(strict_types=1);

test('no debug functions used anywhere', function (): void {
    expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
        ->not->toBeUsed();
});

test('contracts namespace contains only interfaces', function (): void {
    expect('Thaolaptrinh\Rag\Contracts')
        ->toBeInterfaces();
});

test('services do not depend on concrete driver implementations', function (): void {
    expect('Thaolaptrinh\Rag\Services')
        ->not->toUse([
            'Thaolaptrinh\Rag\Drivers\Embeddings\OpenAIEmbeddingDriver',
            'Thaolaptrinh\Rag\Drivers\VectorStores\PgVectorStore',
            'Thaolaptrinh\Rag\Drivers\Llm\OpenAILlmDriver',
        ]);
});

test('drivers implement their corresponding contracts', function (): void {
    expect('Thaolaptrinh\Rag\Drivers\Embeddings\OpenAIEmbeddingDriver')
        ->toImplement('Thaolaptrinh\Rag\Contracts\EmbeddingDriver');

    expect('Thaolaptrinh\Rag\Drivers\VectorStores\PgVectorStore')
        ->toImplement('Thaolaptrinh\Rag\Contracts\VectorStore');

    expect('Thaolaptrinh\Rag\Drivers\Llm\OpenAILlmDriver')
        ->toImplement('Thaolaptrinh\Rag\Contracts\LlmDriver');

    expect('Thaolaptrinh\Rag\Drivers\DataSource\TextDataSource')
        ->toImplement('Thaolaptrinh\Rag\Contracts\DataSource');
});

test('all exceptions extend RagException', function (): void {
    expect('Thaolaptrinh\Rag\Exceptions')
        ->toExtend('Thaolaptrinh\Rag\Exceptions\RagException')
        ->ignoring('Thaolaptrinh\Rag\Exceptions\RagException');
});
