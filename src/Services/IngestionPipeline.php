<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services;

use Thaolaptrinh\Rag\Contracts\Chunker;
use Thaolaptrinh\Rag\Contracts\DataSource;
use Thaolaptrinh\Rag\Contracts\EmbeddingDriver;
use Thaolaptrinh\Rag\Contracts\VectorStore;
use Thaolaptrinh\Rag\Services\Logging\RagLogger;

class IngestionPipeline
{
    public function __construct(
        private readonly DataSource $dataSource,
        private readonly Chunker $chunker,
        private readonly EmbeddingDriver $embedder,
        private readonly VectorStore $vectorStore,
        private readonly RagLogger $logger
    ) {}

    /**
     * Ingest data from a source
     *
     * @param  string  $source  Data source identifier
     * @return array{stored: int, errors: int, source: string}
     */
    public function ingest(string $source): array
    {
        $this->logger->ingestionStart($source);

        try {
            $documents = $this->dataSource->load($source);

            if (empty($documents)) {
                $this->logger->ingestionComplete($source, 0);

                return [
                    'stored' => 0,
                    'errors' => 0,
                    'source' => $source,
                ];
            }

            $allChunks = [];
            foreach ($documents as $document) {
                $chunks = $this->chunker->chunk($document);
                $allChunks = array_merge($allChunks, $chunks);
            }

            if (empty($allChunks)) {
                $this->logger->ingestionComplete($source, 0);

                return [
                    'stored' => 0,
                    'errors' => 0,
                    'source' => $source,
                ];
            }

            $texts = array_map(fn ($chunk) => $chunk['content'], $allChunks);
            $this->logger->embeddingBatch(count($texts));

            $embeddings = $this->embedder->embedBatch($texts);

            if (count($embeddings) !== count($allChunks)) {
                throw new \RuntimeException('Embedding count mismatch');
            }

            $items = [];
            foreach ($allChunks as $index => $chunk) {
                $items[] = [
                    'chunk' => $chunk,
                    'embedding' => $embeddings[$index],
                ];
            }

            $this->logger->storeBatch(count($items));
            $this->vectorStore->storeMany($items);

            $this->logger->ingestionComplete($source, count($items));

            return [
                'stored' => count($items),
                'errors' => 0,
                'source' => $source,
            ];
        } catch (\Exception $e) {
            $this->logger->ingestionError($source, $e->getMessage());

            return [
                'stored' => 0,
                'errors' => 1,
                'source' => $source,
            ];
        }
    }
}
