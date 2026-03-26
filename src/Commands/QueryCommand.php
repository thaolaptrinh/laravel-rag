<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Commands;

use Illuminate\Console\Command;
use Thaolaptrinh\Rag\Services\QueryPipeline;

class QueryCommand extends Command
{
    protected $signature = 'rag:query {query : The question to ask}';

    protected $description = 'Query the RAG system';

    public function __construct(
        private readonly QueryPipeline $pipeline
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = $this->argument('query');

        $this->info("Query: {$query}");
        $this->newLine();

        try {
            $result = $this->pipeline->query($query);

            $this->info('Answer:');
            $this->line($result['answer']);
            $this->newLine();
            $this->info("Retrieved {$result['chunks']} chunks");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Query failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
