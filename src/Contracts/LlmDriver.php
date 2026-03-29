<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Contracts;

interface LlmDriver
{
    /**
     * Generate a response from a prompt.
     */
    public function generate(string $prompt): string;

    /**
     * Generate a response with system message.
     */
    public function generateWithSystem(string $system, string $prompt): string;

    /**
     * Generate a response streaming tokens via callback.
     *
     * @param  callable(string): void  $callback
     */
    public function generateStream(string $prompt, callable $callback): string;

    /**
     * Generate streaming with system message.
     *
     * @param  callable(string): void  $callback
     */
    public function generateStreamWithSystem(string $system, string $prompt, callable $callback): string;

    /**
     * Get the model's total context window size (input + output).
     *
     * @return int<1, max>
     */
    public function getContextWindow(): int;

    /**
     * Get the maximum output tokens for the model.
     *
     * @return int<1, max>
     */
    public function getMaxOutputTokens(): int;
}
