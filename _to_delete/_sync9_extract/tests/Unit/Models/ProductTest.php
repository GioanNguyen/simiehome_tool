<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_effective_price_prefers_sale_price(): void
    {
        $product = Product::factory()->make([
            'regular_price' => 1_000_000,
            'sale_price' => 900_000,
            'base_price' => 800_000,
        ]);

        $this->assertSame(900_000.0, $product->effectivePrice());
    }

    public function test_effective_price_falls_back_to_regular_price_when_no_sale_price(): void
    {
        $product = Product::factory()->make([
            'regular_price' => 1_000_000,
            'sale_price' => null,
            'base_price' => 800_000,
        ]);

        $this->assertSame(1_000_000.0, $product->effectivePrice());
    }

    /**
     * A "variable" parent row typically has no regular/sale price of its
     * own — only base_price. effectivePrice() should still return something
     * usable instead of 0, so it shows a sane price when picked in a quote.
     */
    public function test_effective_price_falls_back_to_base_price_when_no_catalog_price(): void
    {
        $product = Product::factory()->make([
            'regular_price' => null,
            'sale_price' => null,
            'base_price' => 2_025_000,
        ]);

        $this->assertSame(2_025_000.0, $product->effectivePrice());
    }

    public function test_effective_price_is_zero_when_nothing_is_set(): void
    {
        $product = Product::factory()->make([
            'regular_price' => null,
            'sale_price' => null,
            'base_price' => null,
        ]);

        $this->assertSame(0.0, $product->effectivePrice());
    }
}
