<?php

namespace App\Http\Controllers;

use App\Domain\Pricing\PricingEngine;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live price preview for the quote calculator form: given a product plus
 * the quantity/kích thước tuỳ chỉnh the sales person has typed so far,
 * returns exactly what the real Pricing Engine would charge — the same
 * engine that runs again (from scratch, never trusting this preview) when
 * the quote is actually saved. Without this, the "Đơn giá" field shown on
 * the create-quote form only ever reflected the raw catalog price, so a
 * category rule like length_ratio/fixed/quantity/custom_formula never
 * showed up until the sales person reached the confirmation screen.
 */
class ProductPricePreviewController extends Controller
{
    public function __construct(
        private readonly PricingEngine $pricingEngine,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'length_cm' => ['nullable', 'integer', 'min:1'],
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);

        $result = $this->pricingEngine->priceFor($product, [
            'quantity' => (int) ($validated['quantity'] ?? 1),
            'length_cm' => isset($validated['length_cm']) ? (int) $validated['length_cm'] : null,
        ]);

        return response()->json([
            'unit_price' => $result->unitPrice,
            'formula_type' => $result->formulaType,
        ]);
    }
}
