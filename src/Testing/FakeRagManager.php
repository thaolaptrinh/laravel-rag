<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Testing;

use Illuminate\Support\Str;
use Thaolaptrinh\Rag\Contracts\VectorStore;
use Thaolaptrinh\Rag\Data\Answer;
use Thaolaptrinh\Rag\Data\Document;
use Thaolaptrinh\Rag\Data\IngestionResult;
use Thaolaptrinh\Rag\Exceptions\DocumentNotFoundException;

final class FakeRagManager
{
    /** @var list<array{document: Document}> */
    private array $ingested = [];

    /** @var list<string> */
    private array $queries = [];

    /** @var list<string> */
    private array $deleted = [];

    private readonly VectorStore $store;

    public function __construct()
    {
        $this->store = new InMemoryVectorStore;
    }

    public function ingest(Document $document): IngestionResult
    {
        $this->ingested[] = ['document' => $document];
        $this->store->addDocument($document);

        return IngestionResult::create(
            ingested: 1,
            skipped: 0,
            errors: 0,
            traceId: (string) Str::uuid(),
        );
    }

    /**
     * @param  list<Document>  $documents
     */
    public function ingestMany(array $documents): IngestionResult
    {
        foreach ($documents as $document) {
            $this->ingested[] = ['document' => $document];
            $this->store->addDocument($document);
        }

        return IngestionResult::create(
            ingested: count($documents),
            skipped: 0,
            errors: 0,
            traceId: (string) Str::uuid(),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function query(string $question, array $options = []): Answer
    {
        $this->queries[] = $question;

        return Answer::create(
            text: 'Fake response',
            sources: [],
            traceId: (string) Str::uuid(),
        );
    }

    /**
     * @param  callable(string): void  $callback
     * @param  array<string, mixed>  $options
     */
    public function queryStream(string $question, callable $callback, array $options = []): Answer
    {
        $this->queries[] = $question;

        return Answer::create(
            text: 'Fake response',
            sources: [],
            traceId: (string) Str::uuid(),
        );
    }

    public function delete(string $documentId): bool
    {
        $count = $this->store->deleteByDocumentId($documentId);

        if ($count === 0) {
            throw DocumentNotFoundException::create($documentId);
        }

        $this->deleted[] = $documentId;

        return true;
    }

    /**
     * @param  list<string>  $documentIds
     */
    public function deleteMany(array $documentIds): int
    {
        $count = $this->store->deleteByDocumentIds($documentIds);

        foreach ($documentIds as $id) {
            $this->deleted[] = $id;
        }

        return $count;
    }

    public function truncate(): void
    {
        $this->store->truncate();
    }

    public function assertIngested(string $documentId): void
    {
        $found = false;

        foreach ($this->ingested as $entry) {
            if ($entry['document']->id === $documentId) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            throw new \RuntimeException("Document '{$documentId}' was not ingested.");
        }
    }

    public function assertQueried(string $question): void
    {
        if (! in_array($question, $this->queries, true)) {
            throw new \RuntimeException("No query matching '{$question}' was made.");
        }
    }

    public function assertDeleted(string $documentId): void
    {
        if (! in_array($documentId, $this->deleted, true)) {
            throw new \RuntimeException("Document '{$documentId}' was not deleted.");
        }
    }

    public function reset(): void
    {
        $this->ingested = [];
        $this->queries = [];
        $this->deleted = [];
    }
}
