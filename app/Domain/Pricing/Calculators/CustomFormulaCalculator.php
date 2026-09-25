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
 *
 * Surcharge: normally the rule's own fixed rule.surcharge_amount (same as
 * every other formula type), added once per line. When
 * rule.formula_config['use_product_extra_fee'] is true, the rule surcharge is
 * ignored and the *product's own* extra_fee is added to the unit price
 * instead (charged per piece, reported as unitSurcharge), only applying
 * conditionally: the customer's chosen length_cm
 * must be at or below the product's minimum_length. This is the
 * "phụ phí lấy từ sản phẩm, chỉ áp dụng khi chiều dài ≤ chiều dài tối
 * thiểu" admin-configurable option — e.g. a short custom-cut piece needs
 * an extra cutting/handling fee that a longer one doesn't. See
 * productExtraFeeSurcharge() for exactly how the condition is evaluated.
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

        // A configured formula can legitimately evaluate to 0 (e.g. the
        // custom size falls outside what the admin's expression covers).
        // Charging 0đ would look like a free item, so fall back to the
        // product's normal selling price (sale price if set, else the
        // list price) and flag it so staff can double-check the formula
        // instead of silently shipping a 0đ line.
        $warning = null;
        if ($expression !== '' && $unitPrice == 0.0) {
            $unitPrice = $product->effectivePrice();
            $warning = 'Giá tính theo công thức hiện đang bằng 0, đang hiển thị '
                .($product->sale_price > 0 ? 'giá khuyến mãi' : 'giá niêm yết')
                .' thay thế. Vui lòng kiểm tra lại công thức.';
        }

        $unitSurcharge = 0.0;

        if ($rule->formula_config['use_product_extra_fee'] ?? false) {
            // The product's extra_fee is charged per piece: folded into the
            // unit price (so Thành tiền = Đơn giá × SL) and reported via
            // unitSurcharge so the UI/PDF can note what's included.
            [$unitSurcharge, $extraFeeWarning] = $this->productExtraFeeSurcharge($product, $context);
            $unitPrice += $unitSurcharge;
            $surcharge = 0.0;

            if ($extraFeeWarning !== null) {
                $warning = $warning !== null ? $warning.' '.$extraFeeWarning : $extraFeeWarning;
            }
        } else {
            $surcharge = (float) $rule->surcharge_amount;
        }

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
            warning: $warning,
            unitSurcharge: $unitSurcharge,
        );
    }

    /**
     * The conditional, product-driven surcharge for
     * formula_config['use_product_extra_fee'] === true: the product's own
     * extra_fee column, but only when the customer's chosen length_cm is
     * at or below the product's minimum_length — e.g.
     * "chiều dài ≤ chiều dài tối thiểu → giá = công thức + extra_fee;
     * chiều dài > chiều dài tối thiểu → giá = công thức (không phụ phí)".
     *
     * Deliberately conservative when the condition can't actually be
     * evaluated — no minimum_length configured on the product, or no
     * length_cm was given in this context (not every quote line for a
     * custom_formula product necessarily involves a custom length) — by
     * not charging the fee rather than guessing, with a warning for the
     * first case so the admin notices the product needs configuring.
     *
     * @return array{0: float, 1: ?string} [surcharge, warning]
     */
    private function productExtraFeeSurcharge(Product $product, array $context): array
    {
        if ($product->minimum_length === null) {
            return [0.0, 'Sản phẩm chưa cấu hình "Chiều dài tối thiểu" nên phụ phí (extra_fee) không được áp dụng.'];
        }

        $lengthCm = $this->contextLengthCm($context);

        if ($lengthCm === null) {
            return [0.0, null];
        }

        if ($lengthCm <= (float) $product->minimum_length) {
            return [(float) ($product->extra_fee ?? 0), null];
        }

        return [0.0, null];
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
