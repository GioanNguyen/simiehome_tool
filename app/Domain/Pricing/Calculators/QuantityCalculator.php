<?php

namespace App\Domain\Pricing\Calculators;

use App\Domain\Pricing\LineCalculationResult;
use App\Models\PricingRule;
use App\Models\Product;

/**
 * unit_price × quantity, where unit_price can be overridden per rule
 * (formula_config['unit_price']) instead of always reading the product's
 * own catalog price. Useful when the same product needs a different
 * quoted unit price than the public catalog price.
 */
final class QuantityCalculator implements PricingCalculator
{
    use ReadsContextLength;

    public function calculate(Product $product, array $context, PricingRule $rule): LineCalculationResult
    {
        $quantity = max(1, (int) ($context['quantity'] ?? 1));
        $unitPrice = (float) ($rule->formula_config['unit_price'] ?? $product->effectivePrice());
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
            formulaType: 'quantity',
            lineTotal: $lineTotal,
        );
    }
}
