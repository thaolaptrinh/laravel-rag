<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Drivers\Llm;

use Illuminate\Support\Facades\Http;
use Thaolaptrinh\Rag\Contracts\LlmDriver;
use Thaolaptrinh\Rag\Exceptions\GenerationFailedException;

class OpenAILlmDriver implements LlmDriver
{
    private readonly string $apiKey;

    private readonly string $model;

    private readonly int $maxTokens;

    private readonly string $apiUrl;

    private readonly float $temperature;

    public function __construct(
        string $apiKey,
        string $model = 'gpt-4o-mini',
        int $maxTokens = 4096,
        float $temperature = 0.7,
        ?string $apiUrl = null
    ) {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->maxTokens = $maxTokens;
        $this->temperature = $temperature;
        $this->apiUrl = $apiUrl ?? 'https://api.openai.com/v1/chat/completions';
    }

    public function generate(string $prompt): string
    {
        return $this->generateWithSystem('You are a helpful assistant.', $prompt);
    }

    public function generateWithSystem(string $system, string $prompt): string
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->post($this->apiUrl, [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => $this->temperature,
                    'max_tokens' => $this->maxTokens,
                ]);

            if (! $response->successful()) {
                throw new GenerationFailedException(
                    "API request failed: {$response->status()} {$response->body()}"
                );
            }

            $data = $response->json();

            if (! is_array($data) || ! isset($data['choices'][0]['message']['content'])) {
                throw new GenerationFailedException('Invalid response structure');
            }

            return $data['choices'][0]['message']['content'];
        } catch (\Exception $e) {
            if ($e instanceof GenerationFailedException) {
                throw $e;
            }
            throw new GenerationFailedException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function generateStream(string $prompt, callable $callback): string
    {
        $fullResponse = '';

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->withOptions([
                    'stream' => true,
                    'stream_callback' => function ($chunk) use ($callback, &$fullResponse) {
                        if (! empty($chunk)) {
                            $lines = explode("\n", $chunk);
                            foreach ($lines as $line) {
                                if (strpos($line, 'data: ') === 0) {
                                    $data = substr($line, 6);
                                    if ($data === '[DONE]') {
                                        return;
                                    }
                                    $json = json_decode($data, true);
                                    if (isset($json['choices'][0]['delta']['content'])) {
                                        $content = $json['choices'][0]['delta']['content'];
                                        $fullResponse .= $content;
                                        $callback($content);
                                    }
                                }
                            }
                        }
                    },
                ])
                ->post($this->apiUrl, [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => $this->temperature,
                    'max_tokens' => $this->maxTokens,
                    'stream' => true,
                ]);

            if (! $response->successful()) {
                throw new GenerationFailedException(
                    "API request failed: {$response->status()}"
                );
            }

            return $fullResponse;
        } catch (\Exception $e) {
            if ($e instanceof GenerationFailedException) {
                throw $e;
            }
            throw new GenerationFailedException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }
}
