<?php

namespace App\Domain\Pricing\Calculators;

use App\Domain\Pricing\Formula\FormulaEvaluator;
use App\Domain\Pricing\LineCalculationResult;
use App\Models\PricingRule;
use App\Models\Product;

/**
 * Evaluates an admin-authored formula (rule.formula_config['expression'],
 * e.g. "base_price + length_cm * 5000") for products that need a price
 * computed on the fly for a bespoke/custom size the catalog doesn't already
 * list as its own SKU or variation. This is the mechanism behind the
 * "Công thức tuỳ chỉnh" pricing rule type in the admin UI.
 *
 * Falls back to the product's catalog price if no expression is configured
 * (matching FixedPriceCalculator's fallback for a missing fixed_price), so
 * an incompletely-configured rule degrades gracefully instead of crashing a
 * quote. A malformed expression, by contrast, is a real configuration bug —
 * the admin form validates it at save time, so it should be effectively
 * unreachable here, and is allowed to surface as an error rather than be
 * silently swallowed.
 */
final class CustomFormulaCalculator implements PricingCalculator
{
    use ReadsContextLength;

    /**
     * Product/context columns a formula is allowed to reference. Kept in
     * one place so the admin form's validation (which has no real product
     * to evaluate against) and this calculator's actual evaluation always
     * agree on what's available.
     *
     * @var array<int, string>
     */
    public const AVAILABLE_VARIABLES = ['base_price', 'regular_price', 'sale_price', 'length_cm'];

    public function __construct(
        private readonly FormulaEvaluator $evaluator = new FormulaEvaluator,
    ) {}

    public function calculate(Product $product, array $context, PricingRule $rule): LineCalculationResult
    {
        $quantity = max(1, (int) ($context['quantity'] ?? 1));
        $expression = trim((string) ($rule->formula_config['expression'] ?? ''));

        $unitPrice = $expression !== ''
            ? $this->evaluator->evaluate($expression, $this->variablesFor($product, $context))
            : $product->effectivePrice();

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
            formulaType: 'custom_formula',
            lineTotal: $lineTotal,
        );
    }

    /**
     * @return array<string, float>
     */
    private function variablesFor(Product $product, array $context): array
    {
        return [
            'base_price' => (float) ($product->base_price ?? 0),
            'regular_price' => (float) ($product->regular_price ?? 0),
            'sale_price' => (float) ($product->sale_price ?? 0),
            'length_cm' => (float) ($context['length_cm'] ?? 0),
        ];
    }
}
