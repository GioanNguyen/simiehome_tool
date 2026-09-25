<?php

namespace App\Domain\Pricing;

use App\Models\Product;

/**
 * Orchestrates a full quote: one optional main item, any number of add-on
 * items, and any number of free-form fees. Main/add-on prices normally go
 * through the PricingEngine (never trust a price the caller supplies
 * directly) so the same rule that governs the calculator preview also
 * governs what actually gets saved. The one deliberate exception is the
 * main item's `unit_price_override`: sales staff may need to quote a price
 * different from the catalog (a one-off discount, a special deal), so when
 * that field is explicitly provided it replaces the engine's computed
 * price entirely — no rule/surcharge is applied on top of it, and the
 * saved line is tagged formula_type = "manual_override" so it stays
 * auditable later.
 */
final class QuoteCalculator
{
    public function __construct(
        private readonly PricingEngine $pricingEngine = new PricingEngine,
    ) {}

    /**
     * @param  array{
     *     main?: array{product_id?: int|null, quantity?: int|null, length_cm?: int|null, unit_price_override?: float|string|null},
     *     addons?: array<int, array{product_id?: int|null, quantity?: int|null, length_cm?: int|null}>,
     *     fees?: array<int, array{label?: string|null, amount?: float|null}>,
     * }  $payload
     */
    public function calculate(array $payload): QuoteCalculationResult
    {
        $main = null;
        $mainInput = $payload['main'] ?? [];

        if (! empty($mainInput['product_id'])) {
            $product = Product::query()->find($mainInput['product_id']);

            if ($product !== null) {
                $quantity = (int) ($mainInput['quantity'] ?? 1);
                $lengthCm = isset($mainInput['length_cm']) ? (int) $mainInput['length_cm'] : null;
                $override = $mainInput['unit_price_override'] ?? null;

                $main = $override !== null && $override !== ''
                    ? $this->manualOverrideLine($product, (float) $override, max(1, $quantity), $lengthCm)
                    : $this->pricingEngine->priceFor($product, ['quantity' => $quantity, 'length_cm' => $lengthCm]);
            }
        }

        $addons = [];

        foreach ($payload['addons'] ?? [] as $addonInput) {
            if (empty($addonInput['product_id'])) {
                continue;
            }

            $product = Product::query()->find($addonInput['product_id']);

            if ($product === null) {
                continue;
            }

            $addons[] = $this->pricingEngine->priceFor($product, [
                'quantity' => (int) ($addonInput['quantity'] ?? 1),
                'length_cm' => isset($addonInput['length_cm']) ? (int) $addonInput['length_cm'] : null,
            ]);
        }

        $fees = [];

        foreach ($payload['fees'] ?? [] as $feeInput) {
            $amount = (float) ($feeInput['amount'] ?? 0);
            $label = trim((string) ($feeInput['label'] ?? ''));

            if ($label === '' && $amount === 0.0) {
                continue;
            }

            $fees[] = [
                'label' => $label !== '' ? $label : 'Phụ phí',
                'amount' => $amount,
            ];
        }

        $subtotal = ($main?->lineTotal ?? 0.0) + array_sum(array_map(
            fn (LineCalculationResult $addon) => $addon->lineTotal,
            $addons,
        ));

        $feeTotal = array_sum(array_column($fees, 'amount'));

        return new QuoteCalculationResult(
            main: $main,
            addons: $addons,
            fees: $fees,
            subtotal: $subtotal,
            feeTotal: $feeTotal,
            grandTotal: $subtotal + $feeTotal,
        );
    }

    private function manualOverrideLine(Product $product, float $unitPrice, int $quantity, ?int $lengthCm): LineCalculationResult
    {
        return new LineCalculationResult(
            productId: $product->id,
            productName: $product->name,
            sku: $product->sku,
            category: $product->category,
            imageUrl: $product->image_url,
            unitPrice: $unitPrice,
            quantity: $quantity,
            lengthCm: $lengthCm,
            surcharge: 0.0,
            formulaType: 'manual_override',
            lineTotal: $unitPrice * $quantity,
        );
    }
}
