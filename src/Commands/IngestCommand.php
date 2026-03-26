<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Commands;

use Illuminate\Console\Command;
use Thaolaptrinh\Rag\Services\IngestionPipeline;

class IngestCommand extends Command
{
    protected $signature = 'rag:ingest {source : The data source (file path, URL, etc.)}';

    protected $description = 'Ingest data into the RAG system';

    public function __construct(
        private readonly IngestionPipeline $pipeline
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->argument('source');

        $this->info("Starting ingestion from: {$source}");

        try {
            $result = $this->pipeline->ingest($source);

            if ($result['errors'] > 0) {
                $this->error("Ingestion failed for source: {$source}");

                return self::FAILURE;
            }

            $this->info('✓ Ingestion completed successfully');
            $this->info("  Stored: {$result['stored']} chunks");
            $this->info("  Source: {$result['source']}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Ingestion error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
