<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Contracts;

interface LlmDriver
{
    /**
     * Generate a response from a prompt
     *
     * @param string $prompt Input prompt
     * @return string Generated response
     */
    public function generate(string $prompt): string;

    /**
     * Generate a response with system message
     *
     * @param string $system System instructions
     * @param string $prompt User prompt
     * @return string Generated response
     */
    public function generateWithSystem(string $system, string $prompt): string;

    /**
     * Generate a response streaming callback
     *
     * @param string $prompt Input prompt
     * @param callable(string): void $callback Callback for each chunk
     * @return string Full response
     */
    public function generateStream(string $prompt, callable $callback): string;

    /**
     * Get the maximum context window size
     *
     * @return int<1, max> Maximum tokens
     */
    public function getMaxTokens(): int;
}
