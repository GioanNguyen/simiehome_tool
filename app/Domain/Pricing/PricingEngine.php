<?php

namespace App\Domain\Pricing;

use App\Domain\Pricing\Calculators\CatalogPriceCalculator;
use App\Domain\Pricing\Calculators\CustomFormulaCalculator;
use App\Domain\Pricing\Calculators\FixedPriceCalculator;
use App\Domain\Pricing\Calculators\LengthRatioCalculator;
use App\Domain\Pricing\Calculators\PricingCalculator;
use App\Domain\Pricing\Calculators\QuantityCalculator;
use App\Models\PricingRule;
use App\Models\Product;

/**
 * Resolves which pricing rule applies to a product and delegates the actual
 * math to the matching calculator. Rule resolution order:
 *
 *   1. An active rule tied directly to this product (product_id override).
 *   2. An active rule for the product's category (product_id is null).
 *   3. A default rule: catalog_price, no surcharge.
 *
 * This is what makes the engine "linh hoạt theo nhóm sản phẩm": admins
 * configure a formula per category (Bàn, Ghế, Giường, ...) without touching
 * code, and can still override a single SKU when it needs its own formula.
 */
final class PricingEngine
{
    /**
     * @var array<string, class-string<PricingCalculator>>
     */
    private const CALCULATORS = [
        'catalog_price' => CatalogPriceCalculator::class,
        'length_ratio' => LengthRatioCalculator::class,
        'fixed' => FixedPriceCalculator::class,
        'quantity' => QuantityCalculator::class,
        'custom_formula' => CustomFormulaCalculator::class,
    ];

    /**
     * @param  array{quantity?: int, length_cm?: int|null}  $context
     */
    public function priceFor(Product $product, array $context = []): LineCalculationResult
    {
        $rule = $this->resolveRule($product);
        $calculatorClass = self::CALCULATORS[$rule->formula_type] ?? CatalogPriceCalculator::class;

        return (new $calculatorClass)->calculate($product, $context, $rule);
    }

    private function resolveRule(Product $product): PricingRule
    {
        $productRule = PricingRule::query()
            ->where('product_id', $product->id)
            ->where('active', true)
            ->latest('id')
            ->first();

        if ($productRule !== null) {
            return $productRule;
        }

        if ($product->category !== null) {
            $categoryRule = PricingRule::query()
                ->whereNull('product_id')
                ->where('category', $product->category)
                ->where('active', true)
                ->latest('id')
                ->first();

            if ($categoryRule !== null) {
                return $categoryRule;
            }
        }

        return new PricingRule([
            'formula_type' => 'catalog_price',
            'surcharge_amount' => 0,
        ]);
    }
}
