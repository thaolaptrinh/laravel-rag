<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Commands;

use Illuminate\Console\Command;
use Thaolaptrinh\Rag\Data\Document;
use Thaolaptrinh\Rag\Rag;

final class RagIngestCommand extends Command
{
    protected $signature = 'rag:ingest
                            {content : The document content to ingest}
                            {--id= : Optional document ID}
                            {--metadata= : Optional metadata as JSON}';

    protected $description = 'Ingest a document into the RAG store';

    public function handle(): int
    {
        /** @var string $content */
        $content = $this->argument('content');

        $idOption = $this->option('id');

        /** @var array<string, mixed> $metadata */
        $metadata = [];
        $metadataOption = $this->option('metadata');
        if (is_string($metadataOption) && $metadataOption !== '') {
            /** @var array<string, mixed> $metadata */
            $metadata = json_decode($metadataOption, true, JSON_THROW_ON_ERROR);
        }

        $documentId = is_string($idOption) && $idOption !== '' ? $idOption : null;

        $document = Document::create(
            content: $content,
            metadata: $metadata,
            id: $documentId,
        );

        $result = Rag::ingest($document);

        $this->info('Document ingested successfully.');
        $this->line("Ingested: {$result->ingested}");
        $this->line("Skipped: {$result->skipped}");
        $this->line("Errors: {$result->errors}");
        $this->line("Trace ID: {$result->traceId}");

        return Command::SUCCESS;
    }
}
