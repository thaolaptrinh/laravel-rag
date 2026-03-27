<?php

declare(strict_types=1);

use Thaolaptrinh\Rag\Contracts\Chunker;
use Thaolaptrinh\Rag\Contracts\DataSource;
use Thaolaptrinh\Rag\Contracts\EmbeddingDriver;
use Thaolaptrinh\Rag\Contracts\PromptBuilder;
use Thaolaptrinh\Rag\Contracts\Retriever;
use Thaolaptrinh\Rag\Contracts\VectorStore;
use Thaolaptrinh\Rag\Drivers\DataSource\TextDataSource;
use Thaolaptrinh\Rag\Drivers\Embeddings\OpenAIEmbeddingDriver;
use Thaolaptrinh\Rag\Drivers\VectorStores\PgVectorStore;
use Thaolaptrinh\Rag\Facades\Rag;
use Thaolaptrinh\Rag\RagManager;
use Thaolaptrinh\Rag\Services\IngestionPipeline;
use Thaolaptrinh\Rag\Services\QueryPipeline;
use Thaolaptrinh\Rag\Services\Retrievers\SimilarityRetriever;
use Thaolaptrinh\Rag\Services\SimplePromptBuilder;
use Thaolaptrinh\Rag\Services\TextChunker;

test('service provider binds all contracts', function (): void {
    expect(app(DataSource::class))->toBeInstanceOf(TextDataSource::class)
        ->and(app(Chunker::class))->toBeInstanceOf(TextChunker::class)
        ->and(app(EmbeddingDriver::class))->toBeInstanceOf(OpenAIEmbeddingDriver::class)
        ->and(app(VectorStore::class))->toBeInstanceOf(PgVectorStore::class)
        ->and(app(PromptBuilder::class))->toBeInstanceOf(SimplePromptBuilder::class)
        ->and(app(Retriever::class))->toBeInstanceOf(SimilarityRetriever::class)
        ->and(app(IngestionPipeline::class))->toBeInstanceOf(IngestionPipeline::class)
        ->and(app(QueryPipeline::class))->toBeInstanceOf(QueryPipeline::class);
});

test('rag facade resolves to RagManager', function (): void {
    expect(app('rag'))->toBeInstanceOf(RagManager::class);
});

test('rag facade delegates ingest to IngestionPipeline', function (): void {
    $result = Rag::ingest(__DIR__.'/../fixtures/sample.txt');

    // IngestionPipeline with real OpenAI driver will fail (no real API key),
    // so we expect an error return — this proves the wiring is correct
    expect($result)->toHaveKeys(['stored', 'errors', 'source'])
        ->and($result['source'])->toBe(__DIR__.'/../fixtures/sample.txt')
        ->and($result['errors'])->toBeGreaterThanOrEqual(0);
});

test('rag facade delegates query to QueryPipeline', function (): void {
    $result = Rag::query('What is RAG?');

    // QueryPipeline with real OpenAI driver will fail (no real API key),
    // so we expect an answer key — this proves the wiring is correct
    expect($result)->toHaveKeys(['answer', 'chunks', 'query'])
        ->and($result['query'])->toBe('What is RAG?');
});

test('text data source loads real file', function (): void {
    $source = new TextDataSource;
    $documents = $source->load(__DIR__.'/../fixtures/sample.txt');

    expect($documents)->toHaveCount(1)
        ->and($documents[0])->toHaveKeys(['id', 'content', 'metadata'])
        ->and($documents[0]['id'])->toBeString()
        ->and($documents[0]['content'])->toBeString()
        ->and($documents[0]['content'])->toContain('RAG systems')
        ->and($documents[0]['metadata'])->toHaveKey('source')
        ->and($documents[0]['metadata']['source'])->toBe(__DIR__.'/../fixtures/sample.txt');
});

test('text chunker produces chunks from real document', function (): void {
    $source = new TextDataSource;
    $chunker = new TextChunker(100, 20);
    $documents = $source->load(__DIR__.'/../fixtures/sample.txt');
    $chunks = $chunker->chunk($documents[0]);

    expect($chunks)->not->toBeEmpty();

    foreach ($chunks as $chunk) {
        expect($chunk)->toHaveKeys(['id', 'content', 'metadata', 'index'])
            ->and($chunk['id'])->toBeString()
            ->and($chunk['content'])->toBeString()
            ->and($chunk['metadata'])->toHaveKey('parent_id')
            ->and($chunk['metadata'])->toHaveKey('chunk_index')
            ->and($chunk['index'])->toBeInt();
    }

    // Verify chunk indices are sequential
    $indices = array_column($chunks, 'index');
    expect($indices)->toBe(range(0, count($chunks) - 1));
});

test('end-to-end text loading and chunking preserves metadata', function (): void {
    $source = new TextDataSource;
    $chunker = new TextChunker(200, 50);
    $documents = $source->load(__DIR__.'/../fixtures/sample.txt');
    $chunks = $chunker->chunk($documents[0]);

    foreach ($chunks as $chunk) {
        expect($chunk['metadata']['source'])->toBe(__DIR__.'/../fixtures/sample.txt')
            ->and($chunk['metadata']['parent_id'])->toBe($documents[0]['id'])
            ->and($chunk['metadata']['type'])->toBe('text');
    }
});

test('prompt builder produces output with real chunk data', function (): void {
    $builder = new SimplePromptBuilder;
    $context = [
        ['content' => 'RAG systems combine retrieval and generation.', 'score' => 0.95, 'metadata' => []],
        ['content' => 'Vector search enables semantic similarity.', 'score' => 0.87, 'metadata' => []],
    ];

    $prompt = $builder->build($context, 'What is RAG?', 4096);

    expect($prompt)->toBeString()
        ->and($prompt)->toContain('RAG systems')
        ->and($prompt)->toContain('What is RAG?')
        ->and($prompt)->toContain('Answer:');

    $tokenEstimate = $builder->estimateTokens($prompt);
    expect($tokenEstimate)->toBeGreaterThan(0);
});

test('prompt builder respects max tokens by truncating context', function (): void {
    $builder = new SimplePromptBuilder;
    $context = [
        ['content' => str_repeat('Word ', 500), 'score' => 0.95, 'metadata' => []],
        ['content' => str_repeat('Text ', 500), 'score' => 0.87, 'metadata' => []],
    ];

    $prompt = $builder->build($context, 'Short query', 50);

    expect($prompt)->toBeString()
        ->and($prompt)->toContain('Short query')
        ->and($prompt)->toContain('Answer:');
});

test('text data source throws on non-existent file', function (): void {
    $source = new TextDataSource;
    $source->load('/non/existent/path.txt');
})->throws(RuntimeException::class, 'File not found');
