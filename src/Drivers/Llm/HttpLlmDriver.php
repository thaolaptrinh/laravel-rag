<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Drivers\Llm;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Thaolaptrinh\Rag\Contracts\LlmDriver;
use Thaolaptrinh\Rag\Exceptions\ConfigurationException;
use Thaolaptrinh\Rag\Exceptions\GenerationFailedException;

final class HttpLlmDriver implements LlmDriver
{
    /** @var int<1, max> */
    private readonly int $maxOutputTokens;

    /** @var int<1, max> */
    private readonly int $contextWindow;

    /** @var int<1, max> */
    private readonly int $timeout;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        /** @var int<1, max> */
        int $maxOutputTokens,
        /** @var int<1, max> */
        int $contextWindow,
        private readonly float $temperature,
        /** @var int<1, max> */
        int $timeout,
        private readonly string $apiUrl,
    ) {
        if ($apiKey === '') {
            throw ConfigurationException::create('LLM API key must not be empty');
        }

        assert($maxOutputTokens >= 1);
        assert($contextWindow >= 1);
        assert($timeout >= 1);

        $this->maxOutputTokens = $maxOutputTokens;
        $this->contextWindow = $contextWindow;
        $this->timeout = $timeout;
    }

    public function generate(string $prompt): string
    {
        return $this->generateWithSystem(
            'You are a helpful assistant.',
            $prompt,
        );
    }

    public function generateWithSystem(string $system, string $prompt): string
    {
        $response = $this->sendRequest($this->buildMessages($system, $prompt), stream: false);

        return $this->extractContent($response);
    }

    public function generateStream(string $prompt, callable $callback): string
    {
        return $this->generateStreamWithSystem(
            'You are a helpful assistant.',
            $prompt,
            $callback,
        );
    }

    public function generateStreamWithSystem(string $system, string $prompt, callable $callback): string
    {
        $this->sendRequest($this->buildMessages($system, $prompt), stream: true, callback: $callback);

        return '';
    }

    /**
     * @return int<1, max>
     *
     * @phpstan-return int<1, max>
     */
    public function getContextWindow(): int
    {
        return $this->contextWindow;
    }

    /**
     * @return int<1, max>
     *
     * @phpstan-return int<1, max>
     */
    public function getMaxOutputTokens(): int
    {
        return $this->maxOutputTokens;
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function buildMessages(string $system, string $prompt): array
    {
        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{choices: list<array{message: array{content: string}}>}
     */
    private function sendRequest(array $messages, bool $stream, ?callable $callback = null): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxOutputTokens,
            'stream' => $stream,
        ];

        $retries429 = 0;
        $retries5xx = 0;

        while (true) {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->connectTimeout(5)
                ->acceptJson()
                ->post($this->apiUrl, $payload);

            if ($response->successful()) {
                break;
            }

            $statusCode = $response->status();

            if (in_array($statusCode, [401, 403], true)) {
                throw GenerationFailedException::create(
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

            throw GenerationFailedException::create(
                "LLM API request failed (HTTP {$statusCode}): {$response->body()}",
            );
        }

        if ($stream && $callback !== null) {
            $this->parseSse($response->body(), $callback);

            return ['choices' => [['message' => ['content' => '']]]];
        }

        try {
            $decoded = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw GenerationFailedException::create('Invalid JSON response from LLM API', $e);
        }

        if (! is_array($decoded) || ! isset($decoded['choices']) || ! is_array($decoded['choices'])) {
            throw GenerationFailedException::create('Invalid response structure from LLM API');
        }

        /** @var array{choices: list<array{message: array{content: string}}>} $decoded */
        return $decoded;
    }

    /**
     * @param  array{choices: list<array{message: array{content: string}}>}  $response
     */
    private function extractContent(array $response): string
    {
        if (! isset($response['choices'][0]['message']['content'])) {
            throw GenerationFailedException::create('No content in LLM response');
        }

        return $response['choices'][0]['message']['content'];
    }

    private function parseSse(string $body, callable $callback): void
    {
        $lines = explode("\n", $body);

        foreach ($lines as $line) {
            $line = trim($line);

            if (! str_starts_with($line, 'data: ')) {
                continue;
            }

            $data = substr($line, 6);

            if ($data === '[DONE]') {
                break;
            }

            try {
                $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

                if (! is_array($decoded) || ! isset($decoded['choices']) || ! is_array($decoded['choices'])) {
                    Log::channel('stack')->warning('Malformed SSE chunk in LLM stream', [
                        'raw_line' => strlen($data) > 200 ? substr($data, 0, 200).'...' : $data,
                    ]);

                    continue;
                }

                /** @var list<array{message?: array<string, mixed>, delta?: array<string, mixed>}> $choices */
                $choices = $decoded['choices'];
                $choice0 = $choices[0] ?? null;

                if (! is_array($choice0)) {
                    continue;
                }

                $message = $choice0['message'] ?? $choice0['delta'] ?? null;

                if (! is_array($message)) {
                    continue;
                }

                $content = $message['content'] ?? '';

                if (is_string($content) && $content !== '') {
                    $callback($content);
                }
            } catch (\JsonException $e) {
                Log::channel('stack')->warning('Failed to decode SSE chunk in LLM stream', [
                    'raw_line' => strlen($data) > 200 ? substr($data, 0, 200).'...' : $data,
                ]);
            }
        }
    }
}
