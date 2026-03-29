<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag;

use Thaolaptrinh\Rag\Contracts\VectorStore;
use Thaolaptrinh\Rag\Data\Answer;
use Thaolaptrinh\Rag\Data\Document;
use Thaolaptrinh\Rag\Data\IngestionResult;
use Thaolaptrinh\Rag\Exceptions\DocumentNotFoundException;
use Thaolaptrinh\Rag\Services\Pipelines\IngestionPipeline;
use Thaolaptrinh\Rag\Services\Pipelines\QueryPipeline;

class RagManager
{
    public function __construct(
        private readonly IngestionPipeline $ingestionPipeline,
        private readonly QueryPipeline $queryPipeline,
        private readonly VectorStore $vectorStore,
    ) {}

    public function ingest(Document $document): IngestionResult
    {
        return $this->ingestionPipeline->run($document);
    }

    /**
     * @param  list<Document>  $documents
     */
    public function ingestMany(array $documents): IngestionResult
    {
        return $this->ingestionPipeline->run(...$documents);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function query(string $question, array $options = []): Answer
    {
        return $this->queryPipeline->run($question, $options);
    }

    /**
     * @param  callable(string): void  $callback
     * @param  array<string, mixed>  $options
     */
    public function queryStream(string $question, callable $callback, array $options = []): Answer
    {
        return $this->queryPipeline->runStream($question, $callback, $options);
    }

    public function delete(string $documentId): bool
    {
        $count = $this->vectorStore->deleteByDocumentId($documentId);

        if ($count === 0) {
            throw DocumentNotFoundException::create($documentId);
        }

        return true;
    }

    /**
     * @param  list<string>  $documentIds
     */
    public function deleteMany(array $documentIds): int
    {
        return $this->vectorStore->deleteByDocumentIds($documentIds);
    }

    public function truncate(): void
    {
        $this->vectorStore->truncate();
    }
}
