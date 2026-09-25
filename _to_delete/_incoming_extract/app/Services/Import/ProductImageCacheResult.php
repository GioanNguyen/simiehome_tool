<?php

namespace App\Services\Import;

final class ProductImageCacheResult
{
    public function __construct(
        public readonly int $total = 0,
        public readonly int $downloaded = 0,
        public readonly int $unchanged = 0,
    ) {}
}
