<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Commands;

use Illuminate\Console\Command;
use Thaolaptrinh\Rag\Rag;

final class RagQueryCommand extends Command
{
    protected $signature = 'rag:query
                            {question : The question to ask}
                            {--top-k= : Number of results to retrieve}
                            {--filters= : Metadata filters as JSON}';

    protected $description = 'Query the RAG store';

    public function handle(): int
    {
        /** @var string $question */
        $question = $this->argument('question');

        $options = [];

        $topK = $this->option('top-k');
        if (is_string($topK) && $topK !== '') {
            $options['top_k'] = (int) $topK;
        }

        $filtersOption = $this->option('filters');
        if (is_string($filtersOption) && $filtersOption !== '') {
            /** @var array<string, mixed> $filters */
            $filters = json_decode($filtersOption, true, JSON_THROW_ON_ERROR);
            $options['filters'] = $filters;
        }

        $answer = Rag::query($question, $options);

        $this->info('Answer:');
        $this->line($answer->text);

        if (count($answer->sources) > 0) {
            $this->info("\nSources:");
            foreach ($answer->sources as $source) {
                $this->line("- {$source->content} (score: {$source->score})");
            }
        }

        $this->line("\nTrace ID: {$answer->traceId}");

        return Command::SUCCESS;
    }
}
