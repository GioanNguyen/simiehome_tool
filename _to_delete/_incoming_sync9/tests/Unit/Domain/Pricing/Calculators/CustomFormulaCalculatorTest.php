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
