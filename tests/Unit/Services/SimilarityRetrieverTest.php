<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Tests\Unit\Services;

use Mockery as M;
use Thaolaptrinh\Rag\Contracts\EmbeddingDriver;
use Thaolaptrinh\Rag\Contracts\VectorStore;
use Thaolaptrinh\Rag\Data\QueryResult;
use Thaolaptrinh\Rag\Exceptions\EmbeddingFailedException;
use Thaolaptrinh\Rag\Exceptions\RetrievalFailedException;
use Thaolaptrinh\Rag\Exceptions\StorageFailedException;
use Thaolaptrinh\Rag\Services\Retrieving\SimilarityRetriever;

it('retrieves relevant chunks by embedding query and searching store', function () {
    $embedder = M::mock(EmbeddingDriver::class);
    $store = M::mock(VectorStore::class);

    $embedder->expects('embed')
        ->with('What is RAG?')
        ->andReturn([0.1, 0.2, 0.3]);

    $store->expects('search')
        ->with([0.1, 0.2, 0.3], 5, [], 0.0)
        ->andReturn([
            QueryResult::create('RAG means Retrieval-Augmented Generation', 0.95, ['source' => 'wiki']),
        ]);

    $retriever = new SimilarityRetriever($embedder, $store, 0.0);
    $results = $retriever->retrieve('What is RAG?', 5);

    expect($results)->toHaveCount(1);
    expect($results[0]->content)->toBe('RAG means Retrieval-Augmented Generation');
    expect($results[0]->score)->toBe(0.95);
});

it('passes filters and minScore to store search', function () {
    $embedder = M::mock(EmbeddingDriver::class);
    $store = M::mock(VectorStore::class);

    $embedder->expects('embed')
        ->with('test query')
        ->andReturn([0.5, 0.5]);

    $store->expects('search')
        ->with([0.5, 0.5], 10, ['source' => 'pdf'], 0.7)
        ->andReturn([]);

    $retriever = new SimilarityRetriever($embedder, $store, 0.7);
    $results = $retriever->retrieve('test query', 10, ['source' => 'pdf']);

    expect($results)->toBeEmpty();
});

it('wraps EmbeddingFailedException in RetrievalFailedException', function () {
    $embedder = M::mock(EmbeddingDriver::class);
    $store = M::mock(VectorStore::class);

    $embedder->expects('embed')
        ->andThrow(EmbeddingFailedException::create('API timeout'));

    $retriever = new SimilarityRetriever($embedder, $store);

    $retriever->retrieve('test', 5);
})->throws(RetrievalFailedException::class, 'Failed to embed query');

it('wraps StorageFailedException in RetrievalFailedException', function () {
    $embedder = M::mock(EmbeddingDriver::class);
    $store = M::mock(VectorStore::class);

    $embedder->expects('embed')->andReturn([0.1]);

    $store->expects('search')
        ->andThrow(StorageFailedException::create('Connection refused'));

    $retriever = new SimilarityRetriever($embedder, $store);

    $retriever->retrieve('test', 5);
})->throws(RetrievalFailedException::class, 'Failed to search vector store');
