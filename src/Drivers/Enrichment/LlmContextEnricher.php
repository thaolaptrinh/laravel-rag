<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Drivers\Enrichment;

use Thaolaptrinh\Rag\Contracts\ContextEnricher;
use Thaolaptrinh\Rag\Contracts\LlmDriver;
use Thaolaptrinh\Rag\Data\Chunk;

final class LlmContextEnricher implements ContextEnricher
{
    public function __construct(
        private readonly LlmDriver $llm,
    ) {}

    /**
     * @param  array<string, mixed>  $documentMetadata
     */
    public function enrich(Chunk $chunk, string $documentContent, array $documentMetadata): Chunk
    {
        $context = $this->llm->generateWithSystem(
            'You are a helpful assistant. Given the following document and a chunk from it, '
            .'provide a short, succinct context (50-100 tokens) that situates the chunk within '
            .'the overall document. Answer only with the succinct context and nothing else.',
            "<document>\n{$documentContent}\n</document>\n\n"
            ."<chunk>\n{$chunk->content}\n</chunk>",
        );

        return Chunk::create(
            documentId: $chunk->documentId,
            content: $context."\n\n".$chunk->content,
            index: $chunk->index,
            metadata: $chunk->metadata,
        );
    }
}
