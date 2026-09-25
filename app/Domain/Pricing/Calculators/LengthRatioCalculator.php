<?php

namespace App\Domain\Pricing\Calculators;

use App\Domain\Pricing\LineCalculationResult;
use App\Models\PricingRule;
use App\Models\Product;

/**
 * For bespoke/custom sizing that is not one of the catalog's fixed
 * variations: unit price = base_price * length_cm / unit_cm, matching the
 * illustrative formula from the original pricing sketch
 * (đơn giá × kích thước / 100 + phụ phí). `unit_cm` defaults to 100 but can
 * be overridden per rule via formula_config.
 */
final class LengthRatioCalculator implements PricingCalculator
{
    use ReadsContextLength;

    public function calculate(Product $product, array $context, PricingRule $rule): LineCalculationResult
    {
        $quantity = max(1, (int) ($context['quantity'] ?? 1));
        $lengthCm = (int) ($context['length_cm'] ?? 0);
        $unitCm = (float) ($rule->formula_config['unit_cm'] ?? 100);
        $basePrice = $product->effectivePrice();

        $unitPrice = $unitCm > 0 ? ($basePrice * $lengthCm / $unitCm) : 0.0;
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
            formulaType: 'length_ratio',
            lineTotal: $lineTotal,
        );
    }
}
