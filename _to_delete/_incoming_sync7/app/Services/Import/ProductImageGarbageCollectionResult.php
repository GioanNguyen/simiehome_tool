<?php

namespace App\Services\Import;

final class ProductImageGarbageCollectionResult
{
    public function __construct(
        public readonly int $deletedCount = 0,
        public readonly int $freedBytes = 0,
        public readonly int $keptCount = 0,
    ) {}
}
