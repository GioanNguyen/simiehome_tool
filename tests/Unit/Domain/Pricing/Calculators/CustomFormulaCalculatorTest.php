<?php

namespace Tests\Unit\Domain\Pricing\Calculators;

use App\Domain\Pricing\Calculators\CustomFormulaCalculator;
use App\Models\PricingRule;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomFormulaCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_evaluates_the_configured_expression_against_the_product_and_context(): void
    {
        $product = Product::factory()->create(['base_price' => 1_000_000]);
        $rule = new PricingRule([
            'formula_type' => 'custom_formula',
            'formula_config' => ['expression' => 'base_price + length_cm * 5000'],
            'surcharge_amount' => 200_000,
        ]);

        $result = (new CustomFormulaCalculator)->calculate($product, ['quantity' => 2, 'length_cm' => 140], $rule);

        // unit_price = 1_000_000 + 140*5000 = 1_700_000
        // line_total = 1_700_000 * 2 + 200_000 (surcharge, not multiplied by quantity)
        $this->assertSame(1_700_000.0, $result->unitPrice);
        $this->assertSame(3_600_000.0, $result->lineTotal);
        $this->assertSame('custom_formula', $result->formulaType);
        $this->assertSame(140, $result->lengthCm);
    }

    public function test_it_treats_a_missing_length_cm_as_zero(): void
    {
        $product = Product::factory()->create(['base_price' => 1_000_000]);
        $rule = new PricingRule([
            'formula_type' => 'custom_formula',
            'formula_config' => ['expression' => 'base_price + length_cm * 5000'],
            'surcharge_amount' => 0,
        ]);

        $result = (new CustomFormulaCalculator)->calculate($product, ['quantity' => 1], $rule);

        $this->assertSame(1_000_000.0, $result->unitPrice);
    }

    public function test_it_falls_back_to_the_catalog_price_when_no_expression_is_configured(): void
    {
        $product = Product::factory()->create([
            'base_price' => 1_000_000,
            'regular_price' => 750_000,
            'sale_price' => null,
        ]);
        $rule = new PricingRule([
            'formula_type' => 'custom_formula',
            'formula_config' => null,
            'surcharge_amount' => 0,
        ]);

        $result = (new CustomFormulaCalculator)->calculate($product, ['quantity' => 1], $rule);

        $this->assertSame(750_000.0, $result->unitPrice);
    }

    public function test_it_applies_the_product_extra_fee_when_length_is_at_or_below_minimum(): void
    {
        $product = Product::factory()->create([
            'base_price' => 100_000,
            'extra_fee' => 50_000,
            'minimum_length' => 100,
        ]);
        $rule = new PricingRule([
            'formula_type' => 'custom_formula',
            'formula_config' => [
                'expression' => 'base_price * length_cm',
                'use_product_extra_fee' => true,
            ],
            // Deliberately non-zero to prove it's ignored in favour of the
            // product's own extra_fee when the toggle is on.
            'surcharge_amount' => 999_999,
        ]);

        $result = (new CustomFormulaCalculator)->calculate($product, ['quantity' => 1, 'length_cm' => 80], $rule);

        // length_cm (80) <= minimum_length (100) => extra_fee applies.
        $this->assertSame(50_000.0, $result->surcharge);
        $this->assertSame((100_000.0 * 80) + 50_000.0, $result->lineTotal);
        $this->assertNull($result->warning);
    }

    public function test_it_applies_the_product_extra_fee_when_length_equals_minimum_exactly(): void
    {
        $product = Product::factory()->create([
            'base_price' => 100_000,
            'extra_fee' => 50_000,
            'minimum_length' => 100,
        ]);
        $rule = new PricingRule([
            'formula_type' => 'custom_formula',
            'formula_config' => ['expression' => 'base_price * length_cm', 'use_product_extra_fee' => true],
            'surcharge_amount' => 0,
        ]);

        $result = (new CustomFormulaCalculator)->calculate($product, ['quantity' => 1, 'length_cm' => 100], $rule);

        $this->assertSame(50_000.0, $result->surcharge);
    }

    public function test_it_does_not_apply_the_product_extra_fee_when_length_exceeds_minimum(): void
    {
        $product = Product::factory()->create([
            'base_price' => 100_000,
            'extra_fee' => 50_000,
            'minimum_length' => 100,
        ]);
        $rule = new PricingRule([
            'formula_type' => 'custom_formula',
            'formula_config' => ['expression' => 'base_price * length_cm', 'use_product_extra_fee' => true],
            'surcharge_amount' => 0,
        ]);

        $result = (new CustomFormulaCalculator)->calculate($product, ['quantity' => 1, 'length_cm' => 150], $rule);

        $this->assertSame(0.0, $result->surcharge);
        $this->assertSame(100_000.0 * 150, $result->lineTotal);
    }

    public function test_it_warns_and_skips_the_extra_fee_when_the_product_has_no_minimum_length_configured(): void
    {
        $product = Product::factory()->create([
            'base_price' => 100_000,
            'extra_fee' => 50_000,
            'minimum_length' => null,
        ]);
        $rule = new PricingRule([
            'formula_type' => 'custom_formula',
            'formula_config' => ['expression' => 'base_price * length_cm', 'use_product_extra_fee' => true],
            'surcharge_amount' => 0,
        ]);

        $result = (new CustomFormulaCalculator)->calculate($product, ['quantity' => 1, 'length_cm' => 80], $rule);

        $this->assertSame(0.0, $result->surcharge);
        $this->assertStringContainsString('chưa cấu hình', $result->warning);
    }

    public function test_it_skips_the_extra_fee_without_a_warning_when_no_length_was_given(): void
    {
        $product = Product::factory()->create([
            'base_price' => 100_000,
            'extra_fee' => 50_000,
            'minimum_length' => 100,
        ]);
        $rule = new PricingRule([
            'formula_type' => 'custom_formula',
            'formula_config' => ['expression' => 'base_price', 'use_product_extra_fee' => true],
            'surcharge_amount' => 0,
        ]);

        $result = (new CustomFormulaCalculator)->calculate($product, ['quantity' => 1], $rule);

        $this->assertSame(0.0, $result->surcharge);
        $this->assertNull($result->warning);
    }

    public function test_it_ignores_the_rule_surcharge_amount_entirely_when_the_product_extra_fee_toggle_is_on(): void
    {
        $product = Product::factory()->create([
            'base_price' => 100_000,
            'extra_fee' => null,
            'minimum_length' => 100,
        ]);
        $rule = new PricingRule([
            'formula_type' => 'custom_formula',
            'formula_config' => ['expression' => 'base_price * length_cm', 'use_product_extra_fee' => true],
            'surcharge_amount' => 999_999,
        ]);

        // Below minimum, but the product itself has no extra_fee set — the
        // condition is met yet there's nothing to charge, and the rule's
        // own surcharge_amount must NOT be used as a fallback.
        $result = (new CustomFormulaCalculator)->calculate($product, ['quantity' => 1, 'length_cm' => 50], $rule);

        $this->assertSame(0.0, $result->surcharge);
    }

    public function test_it_can_reference_regular_and_sale_price_too(): void
    {
        $product = Product::factory()->create([
            'base_price' => null,
            'regular_price' => 1_200_000,
            'sale_price' => 1_000_000,
        ]);
        $rule = new PricingRule([
            'formula_type' => 'custom_formula',
            'formula_config' => ['expression' => 'regular_price - sale_price'],
            'surcharge_amount' => 0,
        ]);

        $result = (new CustomFormulaCalculator)->calculate($product, ['quantity' => 1], $rule);

        $this->assertSame(200_000.0, $result->unitPrice);
    }
}
