<?php

namespace App\Services\Import;

final class ImportSummary
{
    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public readonly int $totalRows = 0,
        public readonly int $created = 0,
        public readonly int $updated = 0,
        public readonly int $skipped = 0,
        public readonly array $errors = [],
    ) {}

    public function processedCount(): int
    {
        return $this->created + $this->updated;
    }

    public function isSuccess(): bool
    {
        return $this->errors === [];
    }
}
