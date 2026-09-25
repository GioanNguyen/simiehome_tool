<?php

namespace Tests\Feature\Products;

use App\Models\PricingRule;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPricePreviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * This is the exact scenario a sales person hits on the quote form: a
     * "variable" group product (base_price only, no catalog price) with a
     * custom_formula rule on its category. Before this endpoint existed,
     * the "Đơn giá" field only ever showed the raw catalog price and never
     * reacted to length_cm — this is what makes it react correctly.
     */
    public function test_it_prices_a_product_through_the_assigned_custom_formula_rule(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'category' => 'Bàn - Tables',
            'type' => 'variable',
            'regular_price' => null,
            'sale_price' => null,
            'base_price' => 2_025_000,
        ]);

        PricingRule::query()->create([
            'category' => 'Bàn - Tables',
            'formula_type' => 'custom_formula',
            'formula_config' => ['expression' => 'base_price + length_cm'],
            'surcharge_amount' => 0,
            'active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('products.price-preview', [
                'product_id' => $product->id,
                'quantity' => 1,
                'length_cm' => 150,
            ]));

        $response
            ->assertOk()
            ->assertJson([
                'unit_price' => 2_025_150,
                'formula_type' => 'custom_formula',
            ]);
    }

    public function test_it_defaults_to_the_catalog_price_when_no_rule_is_assigned(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'category' => 'Không có rule',
            'regular_price' => 1_000_000,
            'sale_price' => 900_000,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('products.price-preview', ['product_id' => $product->id]));

        $response
            ->assertOk()
            ->assertJson([
                'unit_price' => 900_000,
                'formula_type' => 'catalog_price',
            ]);
    }

    public function test_it_requires_a_valid_product_id(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->getJson(route('products.price-preview', ['product_id' => 999_999]))
            ->assertJsonValidationErrors('product_id');
    }

    public function test_it_requires_authentication(): void
    {
        $product = Product::factory()->create();

        $this
            ->getJson(route('products.price-preview', ['product_id' => $product->id]))
            ->assertUnauthorized();
    }
}
