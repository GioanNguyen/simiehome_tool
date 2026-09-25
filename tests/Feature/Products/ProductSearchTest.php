<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_matching_products_by_name_or_sku(): void
    {
        $user = User::factory()->create();

        Product::factory()->create([
            'sku' => 'BAN-140',
            'name' => 'Ban 1m4',
            'type' => 'simple',
            'regular_price' => 4_000_000,
        ]);

        Product::factory()->create([
            'sku' => 'GHE-A',
            'name' => 'Ghe A',
            'type' => 'simple',
            'regular_price' => 1_000_000,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('products.search', ['q' => 'BAN']));

        $response
            ->assertOk()
            ->assertJsonFragment([
                'sku' => 'BAN-140',
                'name' => 'Ban 1m4',
            ])
            ->assertJsonMissing([
                'sku' => 'GHE-A',
            ]);
    }

    public function test_it_excludes_variable_parent_rows_which_have_no_price_of_their_own(): void
    {
        $user = User::factory()->create();

        Product::factory()->create([
            'sku' => 'BAN-PARENT',
            'name' => 'Ban Group',
            'type' => 'variable',
            'regular_price' => null,
            'base_price' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('products.search', ['q' => 'Ban Group']));

        $response->assertOk()->assertJsonMissing(['sku' => 'BAN-PARENT']);
    }

    /**
     * A "variable" parent row (e.g. "Bàn Borna" grouping several sizes)
     * carries base_price precisely so sales can quote it directly with a
     * custom, not-yet-listed size via a custom_formula rule — so it must be
     * searchable even though it has no regular/sale price of its own.
     */
    public function test_it_includes_variable_parent_rows_that_have_a_base_price(): void
    {
        $user = User::factory()->create();

        Product::factory()->create([
            'sku' => 'BAN-PARENT',
            'name' => 'Ban Group',
            'type' => 'variable',
            'regular_price' => null,
            'sale_price' => null,
            'base_price' => 2_025_000,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('products.search', ['q' => 'Ban Group']));

        $response
            ->assertOk()
            ->assertJsonFragment([
                'sku' => 'BAN-PARENT',
                'price' => 2_025_000,
            ]);
    }

    public function test_it_excludes_draft_products(): void
    {
        $user = User::factory()->create();

        Product::factory()->create([
            'sku' => 'DRAFT-1',
            'name' => 'Draft item',
            'type' => 'simple',
            'status' => 'draft',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('products.search', ['q' => 'Draft item']));

        $response->assertOk()->assertJsonMissing(['sku' => 'DRAFT-1']);
    }
}
