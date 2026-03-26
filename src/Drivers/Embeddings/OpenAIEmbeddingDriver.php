<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Drivers\Embeddings;

use Illuminate\Support\Facades\Http;
use Thaolaptrinh\Rag\Contracts\EmbeddingDriver;
use Thaolaptrinh\Rag\Exceptions\EmbeddingFailedException;

class OpenAIEmbeddingDriver implements EmbeddingDriver
{
    private readonly string $apiKey;

    private readonly string $model;

    private readonly int $dimension;

    private readonly string $apiUrl;

    public function __construct(
        string $apiKey,
        string $model = 'text-embedding-3-small',
        int $dimension = 1536,
        ?string $apiUrl = null
    ) {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->dimension = $dimension;
        $this->apiUrl = $apiUrl ?? 'https://api.openai.com/v1/embeddings';
    }

    public function embed(string $text): array
    {
        $embeddings = $this->embedBatch([$text]);

        return $embeddings[0] ?? throw new EmbeddingFailedException('No embedding returned');
    }

    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->post($this->apiUrl, [
                    'model' => $this->model,
                    'input' => $texts,
                    'encoding_format' => 'float',
                ]);

            if (! $response->successful()) {
                throw new EmbeddingFailedException(
                    "API request failed: {$response->status()} {$response->body()}"
                );
            }

            $data = $response->json();

            if (! is_array($data) || ! isset($data['data'])) {
                throw new EmbeddingFailedException('Invalid response structure');
            }

            $embeddings = [];
            foreach ($data['data'] as $item) {
                if (! isset($item['embedding']) || ! is_array($item['embedding'])) {
                    throw new EmbeddingFailedException('Invalid embedding structure');
                }
                $embeddings[] = $item['embedding'];
            }

            return $embeddings;
        } catch (\Exception $e) {
            if ($e instanceof EmbeddingFailedException) {
                throw $e;
            }
            throw new EmbeddingFailedException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getDimension(): int
    {
        return $this->dimension;
    }
}
