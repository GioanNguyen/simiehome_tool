<?php

namespace App\Domain\Pricing\Calculators;

use App\Domain\Pricing\LineCalculationResult;
use App\Models\PricingRule;
use App\Models\Product;

interface PricingCalculator
{
    /**
     * @param  array{quantity?: int, length_cm?: int|null}  $context
     */
    public function calculate(Product $product, array $context, PricingRule $rule): LineCalculationResult;
}
