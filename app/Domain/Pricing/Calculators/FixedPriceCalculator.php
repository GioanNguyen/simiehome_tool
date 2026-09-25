<?php

namespace App\Domain\Pricing\Calculators;

use App\Domain\Pricing\LineCalculationResult;
use App\Models\PricingRule;
use App\Models\Product;

/**
 * Uses a fixed price configured on the rule instead of the product's own
 * catalog price (e.g. an admin-set flat price for a whole category,
 * independent of quantity/size). Falls back to the product's catalog price
 * if no fixed_price is configured.
 */
final class FixedPriceCalculator implements PricingCalculator
{
    use ReadsContextLength;

    public function calculate(Product $product, array $context, PricingRule $rule): LineCalculationResult
    {
        $quantity = max(1, (int) ($context['quantity'] ?? 1));
        $unitPrice = (float) ($rule->formula_config['fixed_price'] ?? $product->effectivePrice());
        $surcharge = (float) $rule->surcharge_amount;
        $lineTotal = ($unitPrice * $quantity) + $surcharge;

        return new LineCalculationResult(
            productId: $product->id,
            productName: $product->name,
            sku: $product->sku,
            category: $product->category,
            imageUrl: $product->image_url,
            unitPrice: $unitPrice,
            quantity: $quantity,
            lengthCm: $this->contextLengthCm($context),
            surcharge: $surcharge,
            formulaType: 'fixed',
            lineTotal: $lineTotal,
        );
    }
}
