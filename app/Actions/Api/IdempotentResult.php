<?php

namespace App\Actions\Api;

final readonly class IdempotentResult
{
    /** @param array<string, mixed> $body */
    public function __construct(
        public int $status,
        public array $body,
        public bool $replayed,
    ) {}
}
