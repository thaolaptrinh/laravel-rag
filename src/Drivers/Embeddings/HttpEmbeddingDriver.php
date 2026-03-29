<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Drivers\Embeddings;

use Illuminate\Support\Facades\Http;
use Thaolaptrinh\Rag\Contracts\EmbeddingDriver;
use Thaolaptrinh\Rag\Exceptions\ConfigurationException;
use Thaolaptrinh\Rag\Exceptions\EmbeddingFailedException;

final class HttpEmbeddingDriver implements EmbeddingDriver
{
    /** @var int<1, max> */
    private readonly int $dimensions;

    /** @var int<1, max> */
    private readonly int $batchSize;

    /** @var int<1, max> */
    private readonly int $timeout;

    /**
     * @param  int<1, max>  $dimensions
     * @param  int<1, max>  $batchSize
     * @param  int<1, max>  $timeout
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        int $dimensions,
        int $batchSize,
        int $timeout,
        private readonly string $apiUrl,
    ) {
        if ($apiKey === '') {
            throw ConfigurationException::create('Embedding API key must not be empty');
        }

        $this->dimensions = $dimensions;
        $this->batchSize = $batchSize;
        $this->timeout = $timeout;
    }

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $batches = array_chunk($texts, $this->batchSize);
        $allEmbeddings = [];

        foreach ($batches as $batch) {
            $response = $this->sendRequest($batch);
            $batchEmbeddings = $this->parseResponse($response['data']);

            foreach ($batchEmbeddings as $embedding) {
                $allEmbeddings[] = $embedding;
            }
        }

        return $allEmbeddings;
    }

    /**
     * @return int<1, max>
     */
    public function dimensions(): int
    {
        return $this->dimensions;
    }

    /**
     * @param  list<string>  $texts
     * @return array{data: list<array{embedding: list<float>}>}
     */
    private function sendRequest(array $texts): array
    {
        $retries429 = 0;
        $retries5xx = 0;

        while (true) {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->connectTimeout(5)
                ->acceptJson()
                ->post($this->apiUrl, [
                    'model' => $this->model,
                    'input' => $texts,
                    'dimensions' => $this->dimensions,
                    'encoding_format' => 'float',
                ]);

            if ($response->successful()) {
                break;
            }

            $statusCode = $response->status();

            if (in_array($statusCode, [401, 403], true)) {
                throw EmbeddingFailedException::create(
                    "Authentication failed (HTTP {$statusCode})",
                );
            }

            if ($statusCode === 429 && $retries429 < 3) {
                sleep(2 ** $retries429);
                $retries429++;

                continue;
            }

            if (in_array($statusCode, [500, 502, 503], true) && $retries5xx < 2) {
                sleep(2 ** $retries5xx);
                $retries5xx++;

                continue;
            }

            throw EmbeddingFailedException::create(
                "Embedding API request failed (HTTP {$statusCode}): {$response->body()}",
            );
        }

        try {
            $decoded = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw EmbeddingFailedException::create(
                'Invalid JSON response from embedding API',
                $e,
            );
        }

        if (! is_array($decoded) || ! isset($decoded['data']) || ! is_array($decoded['data'])) {
            throw EmbeddingFailedException::create(
                'Invalid response structure from embedding API',
            );
        }

        /** @var array{data: list<array{embedding: list<float>}>} $decoded */
        return $decoded;
    }

    /**
     * @param  list<array{embedding: list<float>}>  $items
     * @return list<list<float>>
     */
    private function parseResponse(array $items): array
    {
        $embeddings = [];

        foreach ($items as $item) {
            $embeddings[] = $item['embedding'];
        }

        return $embeddings;
    }
}
