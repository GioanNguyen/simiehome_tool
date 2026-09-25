<?php

namespace App\Domain\Pricing;

final class LineCalculationResult
{
    public function __construct(
        public readonly ?int $productId,
        public readonly ?string $productName,
        public readonly ?string $sku,
        public readonly ?string $category,
        public readonly ?string $imageUrl,
        public readonly float $unitPrice,
        public readonly int $quantity,
        public readonly ?int $lengthCm,
        public readonly float $surcharge,
        public readonly string $formulaType,
        public readonly float $lineTotal,
        public readonly ?string $warning = null,
        // Per-piece fee already folded into $unitPrice (e.g. the product's
        // extra_fee for a custom length ≤ minimum_length). Informational —
        // unlike $surcharge it is NOT added again to $lineTotal.
        public readonly float $unitSurcharge = 0.0,
    ) {}
}
