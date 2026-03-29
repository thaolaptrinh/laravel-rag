<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services\Pipelines;

use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Thaolaptrinh\Rag\Contracts\Chunker;
use Thaolaptrinh\Rag\Contracts\ContextEnricher;
use Thaolaptrinh\Rag\Contracts\EmbeddingDriver;
use Thaolaptrinh\Rag\Contracts\VectorStore;
use Thaolaptrinh\Rag\Data\Document;
use Thaolaptrinh\Rag\Data\IngestionResult;
use Thaolaptrinh\Rag\Events\DocumentIngested;
use Thaolaptrinh\Rag\Events\DocumentIngestionFailed;
use Thaolaptrinh\Rag\Events\DocumentSkipped;
use Thaolaptrinh\Rag\Events\IngestionCompleted;
use Thaolaptrinh\Rag\Exceptions\ChunkingFailedException;
use Thaolaptrinh\Rag\Services\RagLogger;

final class IngestionPipeline
{
    public function __construct(
        private readonly Chunker $chunker,
        private readonly EmbeddingDriver $embeddingDriver,
        private readonly VectorStore $vectorStore,
        private readonly RagLogger $logger,
        private readonly ?ContextEnricher $contextEnricher = null,
        private readonly int $maxContentLength = 100_000,
        private readonly int $subBatchSize = 10,
        private readonly int $pipelineTimeout = 600,
        private readonly string $connection = 'rag',
    ) {}

    public function run(Document ...$documents): IngestionResult
    {
        $traceId = Str::uuid()->toString();
        $startTime = microtime(true);
        $ingested = 0;
        $skipped = 0;
        $errors = 0;

        $this->logger->ingestionStart($traceId, count($documents));

        if ($documents === []) {
            $this->logger->ingestionComplete($traceId, 0, 0, 0, 0);

            return IngestionResult::create(0, 0, 0, $traceId);
        }

        $this->validateDocumentSizes(array_values($documents));

        $ids = array_map(fn (Document $d) => $d->id, $documents);
        $existing = $this->vectorStore->getDocumentsByIds(array_values($ids));

        $existingByHash = [];
        foreach ($existing as $doc) {
            $existingByHash[$doc['id']] = $doc['content_hash'];
        }

        $toProcess = [];

        foreach ($documents as $doc) {
            $hash = $doc->contentHash();

            if (isset($existingByHash[$doc->id]) && $existingByHash[$doc->id] === $hash) {
                $this->logger->ingestionSkipped($traceId, $doc->id, 'Content unchanged');
                event(new DocumentSkipped($doc->id, 'Content unchanged', $traceId, CarbonImmutable::now()));
                $skipped++;

                continue;
            }

            $toProcess[] = $doc;
        }

        $subBatches = array_chunk($toProcess, max(1, $this->subBatchSize));

        foreach ($subBatches as $subBatch) {
            $this->checkPipelineTimeout($startTime, $ingested, $skipped, $errors, $traceId);

            foreach ($subBatch as $doc) {
                try {
                    $chunkCount = $this->processDocument($doc, $traceId);
                    $ingested++;
                    event(new DocumentIngested($doc->id, $chunkCount, $traceId, CarbonImmutable::now()));
                    $this->logger->ingestionDocumentComplete($traceId, $doc->id, $chunkCount);
                } catch (\Throwable $e) {
                    $errors++;
                    $reason = $e->getMessage();
                    event(new DocumentIngestionFailed($doc->id, $reason, $traceId, $e, CarbonImmutable::now()));
                    $this->logger->ingestionDocumentFailed($traceId, $doc->id, $reason);
                }
            }
        }

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        $this->logger->ingestionComplete($traceId, $ingested, $skipped, $errors, $durationMs);
        event(new IngestionCompleted($ingested, $skipped, $errors, $durationMs, $traceId, CarbonImmutable::now()));

        return IngestionResult::create($ingested, $skipped, $errors, $traceId);
    }

    /**
     * @param  list<Document>  $documents
     */
    private function validateDocumentSizes(array $documents): void
    {
        foreach ($documents as $doc) {
            if (strlen($doc->content) > $this->maxContentLength) {
                throw ChunkingFailedException::create(
                    "Document '{$doc->id}' exceeds max content length ({$this->maxContentLength})",
                );
            }
        }
    }

    private function checkPipelineTimeout(float $startTime, int $ingested, int $skipped, int $errors, string $traceId): void
    {
        $elapsed = microtime(true) - $startTime;

        if ($elapsed >= $this->pipelineTimeout) {
            throw ChunkingFailedException::create(
                "Pipeline timeout exceeded after {$elapsed}s. Progress: {$ingested} ingested, {$skipped} skipped, {$errors} errors.",
            );
        }
    }

    private function processDocument(Document $document, string $traceId): int
    {
        $chunks = $this->chunker->split($document);

        if ($chunks === []) {
            return 0;
        }

        if ($this->contextEnricher !== null) {
            $chunks = array_map(
                fn ($chunk) => $this->contextEnricher->enrich(
                    $chunk,
                    $document->content,
                    $document->metadata,
                ),
                $chunks,
            );
        }

        $texts = array_map(fn ($chunk) => $chunk->content, $chunks);
        $embeddings = $this->embeddingDriver->embedBatch($texts);

        $items = [];
        foreach ($chunks as $i => $chunk) {
            $items[] = [
                'chunk' => $chunk,
                'embedding' => $embeddings[$i],
            ];
        }

        $db = DB::connection($this->connection);
        $lockKey = $this->advisoryLockKey($document->id);

        $db->transaction(function () use ($db, $lockKey, $document, $items): void {
            $acquired = $db->selectOne('SELECT pg_try_advisory_lock(?) AS acquired', [$lockKey]);

            if (! is_array($acquired) || ! isset($acquired['acquired']) || $acquired['acquired'] !== true) {
                throw new \RuntimeException("Failed to acquire advisory lock for document '{$document->id}'");
            }

            try {
                $this->checkHashUnchanged($db, $document);

                $db->table('rag_documents')->upsert(
                    [
                        'id' => $document->id,
                        'content_hash' => $document->contentHash(),
                        'metadata' => json_encode($document->metadata, JSON_THROW_ON_ERROR),
                        'updated_at' => $db->raw('NOW()'),
                    ],
                    ['id'],
                    ['content_hash', 'metadata', 'updated_at'],
                );

                $db->table('rag_chunks')
                    ->where('document_id', $document->id)
                    ->delete();

                $rows = array_map(fn ($item) => [
                    'document_id' => $item['chunk']->documentId,
                    'content' => $item['chunk']->content,
                    'chunk_index' => $item['chunk']->index,
                    'embedding' => '['.implode(',', $item['embedding']).']',
                    'metadata' => json_encode($item['chunk']->metadata, JSON_THROW_ON_ERROR),
                ], $items);

                $db->table('rag_chunks')->insert($rows);
            } finally {
                $db->statement('SELECT pg_advisory_unlock(?)', [$lockKey]);
            }
        });

        return count($chunks);
    }

    private function checkHashUnchanged(Connection $db, Document $document): void
    {
        $row = $db->table('rag_documents')
            ->where('id', $document->id)
            ->value('content_hash');

        if ($row !== null && $row === $document->contentHash()) {
            throw ChunkingFailedException::create(
                "Document '{$document->id}' content unchanged after lock (concurrent ingestion)",
            );
        }
    }

    private function advisoryLockKey(string $documentId): int
    {
        return abs(crc32("rag:document:{$documentId}"));
    }
}
