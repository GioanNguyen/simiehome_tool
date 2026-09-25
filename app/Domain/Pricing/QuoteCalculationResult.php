<?php

namespace App\Domain\Pricing;

final class QuoteCalculationResult
{
    /**
     * @param  LineCalculationResult[]  $addons
     * @param  array<int, array{label: string, amount: float}>  $fees
     */
    public function __construct(
        public readonly ?LineCalculationResult $main,
        public readonly array $addons,
        public readonly array $fees,
        public readonly float $subtotal,
        public readonly float $feeTotal,
        public readonly float $grandTotal,
        public readonly ?string $discountType = null,
        public readonly float $discountValue = 0.0,
        public readonly float $discountTotal = 0.0,
    ) {}
}
