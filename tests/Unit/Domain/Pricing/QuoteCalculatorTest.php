<?php

namespace Tests\Unit\Domain\Pricing;

use App\Domain\Pricing\QuoteCalculator;
use App\Models\PricingRule;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_the_catalog_price_by_default_for_the_main_item(): void
    {
        $table = Product::factory()->create([
            'category' => 'Bàn - Tables',
            'regular_price' => 1_850_000,
            'sale_price' => 1_665_000,
        ]);

        $calculator = new QuoteCalculator;

        $result = $calculator->calculate([
            'main' => ['product_id' => $table->id, 'quantity' => 1],
        ]);

        // Sale price wins when set.
        $this->assertSame(1_665_000.0, $result->main->unitPrice);
        $this->assertSame(1_665_000.0, $result->subtotal);
        $this->assertSame(1_665_000.0, $result->grandTotal);
    }

    public function test_a_custom_size_is_kept_on_the_line_even_when_the_default_catalog_formula_ignores_it_for_pricing(): void
    {
        // Regression test: CatalogPriceCalculator (and FixedPriceCalculator/
        // QuantityCalculator) don't use length_cm in their own math, but the
        // value the sales person typed into "Kích thước tuỳ chỉnh (cm)" must
        // still survive onto the LineCalculationResult — it's shown on the
        // confirm screen, saved to quote_items, printed on the PDF, and used
        // to prefill the form again after "Quay lại".
        $table = Product::factory()->create([
            'category' => 'Bàn - Tables',
            'regular_price' => 1_850_000,
            'sale_price' => null,
        ]);

        $calculator = new QuoteCalculator;

        $result = $calculator->calculate([
            'main' => ['product_id' => $table->id, 'quantity' => 1, 'length_cm' => 150],
        ]);

        $this->assertSame('catalog_price', $result->main->formulaType);
        $this->assertSame(150, $result->main->lengthCm);
    }

    public function test_it_applies_a_length_ratio_rule_configured_for_the_category(): void
    {
        $table = Product::factory()->create([
            'category' => 'Bàn - Tables',
            'regular_price' => 4_000_000,
            'sale_price' => null,
        ]);

        PricingRule::query()->create([
            'category' => 'Bàn - Tables',
            'formula_type' => 'length_ratio',
            'surcharge_amount' => 500_000,
            'active' => true,
        ]);

        $calculator = new QuoteCalculator;

        $result = $calculator->calculate([
            'main' => ['product_id' => $table->id, 'quantity' => 1, 'length_cm' => 140],
        ]);

        // 4_000_000 * 140 / 100 + 500_000
        $this->assertSame(6_100_000.0, $result->main->lineTotal);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pricingRuleFormulaProvider(): iterable
    {
        yield 'catalog_price (no rule)' => [null];
        yield 'fixed' => ['fixed'];
        yield 'quantity' => ['quantity'];
        yield 'length_ratio' => ['length_ratio'];
    }

    /**
     * Regression test, same shape as the length_cm one above: every
     * PricingCalculator implementation builds its own LineCalculationResult
     * by hand, so it's easy for one of them to forget a field that isn't
     * used in its own pricing math (image_url isn't priced on — it's only
     * carried through for display). Covers all four formula types so a
     * future one can't silently drop it the way length_cm once was.
     *
     * @dataProvider pricingRuleFormulaProvider
     */
    public function test_the_product_image_is_carried_onto_the_line_for_every_formula_type(?string $formulaType): void
    {
        $product = Product::factory()->create([
            'category' => 'Bàn - Tables',
            'regular_price' => 1_000_000,
            'sale_price' => null,
            'image_url' => 'https://example.com/images/ban-1m4.jpg',
        ]);

        if ($formulaType !== null) {
            PricingRule::query()->create([
                'category' => 'Bàn - Tables',
                'formula_type' => $formulaType,
                'surcharge_amount' => 0,
                'active' => true,
            ]);
        }

        $calculator = new QuoteCalculator;

        $result = $calculator->calculate([
            'main' => ['product_id' => $product->id, 'quantity' => 1, 'length_cm' => 100],
        ]);

        $this->assertSame('https://example.com/images/ban-1m4.jpg', $result->main->imageUrl);
    }

    public function test_the_product_image_is_carried_onto_a_manually_overridden_line(): void
    {
        $product = Product::factory()->create([
            'regular_price' => 4_000_000,
            'sale_price' => null,
            'image_url' => 'https://example.com/images/ban-1m4.jpg',
        ]);

        $calculator = new QuoteCalculator;

        $result = $calculator->calculate([
            'main' => ['product_id' => $product->id, 'quantity' => 1, 'unit_price_override' => 3_500_000],
        ]);

        $this->assertSame('manual_override', $result->main->formulaType);
        $this->assertSame('https://example.com/images/ban-1m4.jpg', $result->main->imageUrl);
    }

    public function test_the_line_image_is_null_when_the_product_has_no_image(): void
    {
        $product = Product::factory()->create(['regular_price' => 1_000_000, 'sale_price' => null, 'image_url' => null]);

        $calculator = new QuoteCalculator;

        $result = $calculator->calculate([
            'main' => ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $this->assertNull($result->main->imageUrl);
    }

    public function test_it_totals_an_arbitrary_number_of_addon_products_and_fees(): void
    {
        $table = Product::factory()->create(['regular_price' => 1_000_000, 'sale_price' => null]);
        $chairA = Product::factory()->create(['regular_price' => 500_000, 'sale_price' => null]);
        $chairB = Product::factory()->create(['regular_price' => 300_000, 'sale_price' => null]);
        $mirror = Product::factory()->create(['regular_price' => 200_000, 'sale_price' => null]);

        $calculator = new QuoteCalculator;

        $result = $calculator->calculate([
            'main' => ['product_id' => $table->id, 'quantity' => 1],
            'addons' => [
                ['product_id' => $chairA->id, 'quantity' => 4],
                ['product_id' => $chairB->id, 'quantity' => 2],
                ['product_id' => $mirror->id, 'quantity' => 1],
            ],
            'fees' => [
                ['label' => 'Phí ship', 'amount' => 300_000],
                ['label' => 'Phí lắp đặt', 'amount' => 200_000],
            ],
        ]);

        $this->assertCount(3, $result->addons);
        // 1_000_000 + (500_000*4) + (300_000*2) + 200_000 = 3_800_000
        $this->assertSame(3_800_000.0, $result->subtotal);
        $this->assertSame(500_000.0, $result->feeTotal);
        $this->assertSame(4_300_000.0, $result->grandTotal);
    }

    public function test_a_manual_price_override_replaces_the_engine_and_ignores_category_rules(): void
    {
        $table = Product::factory()->create([
            'category' => 'Bàn - Tables',
            'regular_price' => 4_000_000,
            'sale_price' => null,
        ]);

        // Even with a surcharge-heavy category rule in place, the override
        // must win outright — no engine, no surcharge on top of it.
        PricingRule::query()->create([
            'category' => 'Bàn - Tables',
            'formula_type' => 'length_ratio',
            'surcharge_amount' => 500_000,
            'active' => true,
        ]);

        $calculator = new QuoteCalculator;

        $result = $calculator->calculate([
            'main' => [
                'product_id' => $table->id,
                'quantity' => 2,
                'unit_price_override' => 3_500_000,
            ],
        ]);

        $this->assertSame('manual_override', $result->main->formulaType);
        $this->assertSame(3_500_000.0, $result->main->unitPrice);
        $this->assertSame(0.0, $result->main->surcharge);
        $this->assertSame(7_000_000.0, $result->main->lineTotal);
    }

    public function test_an_empty_override_falls_back_to_the_engine(): void
    {
        $table = Product::factory()->create(['regular_price' => 1_000_000, 'sale_price' => null]);

        $calculator = new QuoteCalculator;

        $result = $calculator->calculate([
            'main' => ['product_id' => $table->id, 'quantity' => 1, 'unit_price_override' => ''],
        ]);

        $this->assertSame('catalog_price', $result->main->formulaType);
        $this->assertSame(1_000_000.0, $result->main->unitPrice);
    }

    public function test_it_allows_saving_a_quote_with_no_main_product(): void
    {
        $calculator = new QuoteCalculator;

        $result = $calculator->calculate([
            'fees' => [['label' => 'Phí tư vấn', 'amount' => 100_000]],
        ]);

        $this->assertNull($result->main);
        $this->assertSame(0.0, $result->subtotal);
        $this->assertSame(100_000.0, $result->grandTotal);
    }
}
