<?php

namespace App\Domain\Pricing\Calculators;

use App\Domain\Pricing\LineCalculationResult;
use App\Models\PricingRule;
use App\Models\Product;

/**
 * Default calculator: uses the price already stored on the product/variation
 * (sale price if set, otherwise regular price) as-is. This covers the large
 * majority of the real catalog, where every SKU/variation already has its
 * own fixed price and nothing needs to be derived from a formula.
 */
final class CatalogPriceCalculator implements PricingCalculator
{
    use ReadsContextLength;

    public function calculate(Product $product, array $context, PricingRule $rule): LineCalculationResult
    {
        $quantity = max(1, (int) ($context['quantity'] ?? 1));
        $unitPrice = $product->effectivePrice();
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
            formulaType: 'catalog_price',
            lineTotal: $lineTotal,
        );
    }
}
