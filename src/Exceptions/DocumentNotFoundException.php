<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Exceptions;

final class DocumentNotFoundException extends RagException
{
    private function __construct(
        public readonly string $documentId,
    ) {
        parent::__construct("Document not found: {$documentId}");
    }

    public static function create(string $id, ?\Throwable $previous = null): self
    {
        return new self(documentId: $id);
    }
}
