<?php

declare(strict_types=1);

namespace LaravelDoctor;

final class Finding
{
    public function __construct(
        public readonly string $checkId,
        public readonly string $category,
        public readonly string $severity,
        public readonly string $message,
        public readonly string $file,
        public readonly ?int $line = null,
        public readonly ?string $suggestion = null,
        public readonly ?string $snippet = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'check' => $this->checkId,
            'category' => $this->category,
            'severity' => $this->severity,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'suggestion' => $this->suggestion,
            'snippet' => $this->snippet,
        ];
    }
}
