<?php

namespace Tests\Unit\Domain\Pricing;

use App\Domain\Pricing\PricingEngine;
use App\Models\PricingRule;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_falls_back_to_catalog_price_when_no_rule_exists(): void
    {
        $product = Product::factory()->create(['regular_price' => 1_000_000, 'sale_price' => 900_000]);

        $result = (new PricingEngine)->priceFor($product, ['quantity' => 2]);

        $this->assertSame('catalog_price', $result->formulaType);
        $this->assertSame(900_000.0, $result->unitPrice);
        $this->assertSame(1_800_000.0, $result->lineTotal);
    }

    public function test_a_product_level_rule_overrides_the_category_rule(): void
    {
        $product = Product::factory()->create([
            'category' => 'Ghế - Chairs',
            'regular_price' => 500_000,
            'sale_price' => null,
        ]);

        PricingRule::query()->create([
            'category' => 'Ghế - Chairs',
            'formula_type' => 'catalog_price',
            'surcharge_amount' => 50_000,
            'active' => true,
        ]);

        PricingRule::query()->create([
            'product_id' => $product->id,
            'formula_type' => 'fixed',
            'formula_config' => ['fixed_price' => 700_000],
            'surcharge_amount' => 0,
            'active' => true,
        ]);

        $result = (new PricingEngine)->priceFor($product, ['quantity' => 1]);

        $this->assertSame('fixed', $result->formulaType);
        $this->assertSame(700_000.0, $result->unitPrice);
    }

    public function test_it_dispatches_to_the_custom_formula_calculator(): void
    {
        $product = Product::factory()->create([
            'category' => 'Bàn - Tables',
            'base_price' => 1_000_000,
        ]);

        PricingRule::query()->create([
            'category' => 'Bàn - Tables',
            'formula_type' => 'custom_formula',
            'formula_config' => ['expression' => 'base_price + length_cm * 5000'],
            'surcharge_amount' => 0,
            'active' => true,
        ]);

        $result = (new PricingEngine)->priceFor($product, ['quantity' => 1, 'length_cm' => 140]);

        $this->assertSame('custom_formula', $result->formulaType);
        $this->assertSame(1_700_000.0, $result->unitPrice);
    }

    public function test_inactive_rules_are_ignored(): void
    {
        $product = Product::factory()->create(['category' => 'Kệ – Shelf', 'regular_price' => 400_000]);

        PricingRule::query()->create([
            'category' => 'Kệ – Shelf',
            'formula_type' => 'fixed',
            'formula_config' => ['fixed_price' => 999_999],
            'active' => false,
        ]);

        $result = (new PricingEngine)->priceFor($product, ['quantity' => 1]);

        $this->assertSame('catalog_price', $result->formulaType);
        $this->assertSame(400_000.0, $result->unitPrice);
    }
    public function test_a_global_rule_applies_to_a_category_without_its_own_rule(): void
    {
        $product = Product::factory()->create([
            'category' => 'Ghế - Chairs',
            'regular_price' => 1_000_000,
            'sale_price' => null,
            'base_price' => 500_000,
        ]);

        PricingRule::query()->create([
            'category' => null,
            'product_id' => null,
            'formula_type' => 'custom_formula',
            'formula_config' => ['expression' => 'base_price * length_cm / 100'],
            'surcharge_amount' => 0,
            'active' => true,
        ]);

        $result = (new PricingEngine)->priceFor($product, ['quantity' => 1, 'length_cm' => 240]);

        $this->assertSame('custom_formula', $result->formulaType);
        $this->assertSame(1_200_000.0, $result->unitPrice);
    }

    public function test_a_category_rule_wins_over_the_global_rule(): void
    {
        $product = Product::factory()->create([
            'category' => 'Bàn - Tables',
            'regular_price' => 1_000_000,
            'sale_price' => null,
        ]);

        PricingRule::query()->create([
            'category' => 'Bàn - Tables',
            'formula_type' => 'fixed',
            'formula_config' => ['fixed_price' => 700_000],
            'surcharge_amount' => 0,
            'active' => true,
        ]);

        PricingRule::query()->create([
            'category' => null,
            'product_id' => null,
            'formula_type' => 'fixed',
            'formula_config' => ['fixed_price' => 300_000],
            'surcharge_amount' => 0,
            'active' => true,
        ]);

        $this->assertSame(700_000.0, (new PricingEngine)->priceFor($product, ['quantity' => 1])->unitPrice);
    }

    public function test_an_inactive_global_rule_is_ignored(): void
    {
        $product = Product::factory()->create(['regular_price' => 1_000_000, 'sale_price' => null]);

        PricingRule::query()->create([
            'category' => null,
            'product_id' => null,
            'formula_type' => 'fixed',
            'formula_config' => ['fixed_price' => 300_000],
            'surcharge_amount' => 0,
            'active' => false,
        ]);

        $result = (new PricingEngine)->priceFor($product, ['quantity' => 1]);

        $this->assertSame('catalog_price', $result->formulaType);
        $this->assertSame(1_000_000.0, $result->unitPrice);
    }
}
