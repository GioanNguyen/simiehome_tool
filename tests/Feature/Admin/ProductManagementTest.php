<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_products_to_an_admin(): void
    {
        Product::factory()->create(['name' => 'Bàn Gỗ Sồi', 'sku' => 'BGS-01']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Bàn Gỗ Sồi')
            ->assertSee('BGS-01');
    }

    public function test_it_filters_by_name(): void
    {
        Product::factory()->create(['name' => 'Bàn Gỗ Sồi', 'sku' => 'BGS-01']);
        Product::factory()->create(['name' => 'Ghế X', 'sku' => 'GHE-X']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.index', ['q' => 'Bàn']))
            ->assertOk()
            ->assertSee('Bàn Gỗ Sồi')
            ->assertDontSee('Ghế X');
    }

    public function test_it_filters_by_sku(): void
    {
        Product::factory()->create(['name' => 'Bàn Gỗ Sồi', 'sku' => 'BGS-01']);
        Product::factory()->create(['name' => 'Ghế X', 'sku' => 'GHE-X']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.index', ['q' => 'GHE-X']))
            ->assertOk()
            ->assertSee('Ghế X')
            ->assertDontSee('Bàn Gỗ Sồi');
    }

    public function test_a_search_with_no_matches_shows_the_empty_state(): void
    {
        Product::factory()->create(['name' => 'Bàn Gỗ Sồi', 'sku' => 'BGS-01']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.index', ['q' => 'Không tồn tại']))
            ->assertOk()
            ->assertSee('Không tìm thấy sản phẩm nào phù hợp.')
            ->assertDontSee('Bàn Gỗ Sồi');
    }

    public function test_it_paginates_results(): void
    {
        Product::factory()->count(15)->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.index', ['per_page' => 10]));

        $response->assertOk();
        $this->assertCount(10, $response->viewData('products'));
    }

    public function test_it_updates_a_products_prices(): void
    {
        $product = Product::factory()->create([
            'name' => 'Bàn Basic',
            'regular_price' => 1_000_000,
            'sale_price' => null,
            'base_price' => null,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.products.update', $product), [
                'regular_price' => 1_200_000,
                'sale_price' => 999_000,
                'base_price' => 800_000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'regular_price' => 1_200_000.00,
            'sale_price' => 999_000.00,
            'base_price' => 800_000.00,
        ]);
    }

    public function test_it_can_clear_a_price_by_leaving_the_field_blank(): void
    {
        $product = Product::factory()->create([
            'regular_price' => 1_000_000,
            'sale_price' => 900_000,
            'base_price' => 700_000,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.products.update', $product), [
                'regular_price' => 1_000_000,
                'sale_price' => '',
                'base_price' => '',
            ])
            ->assertRedirect();

        $product->refresh();
        $this->assertNull($product->sale_price);
        $this->assertNull($product->base_price);
    }

    public function test_it_rejects_a_negative_price(): void
    {
        $product = Product::factory()->create(['regular_price' => 1_000_000]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.products.update', $product), [
                'regular_price' => -500,
            ])
            ->assertSessionHasErrors('regular_price');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'regular_price' => 1_000_000.00]);
    }

    public function test_it_does_not_touch_name_sku_or_category(): void
    {
        $product = Product::factory()->create([
            'name' => 'Bàn Basic',
            'sku' => 'BAN-01',
            'category' => 'Bàn - Tables',
            'regular_price' => 1_000_000,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.products.update', $product), [
                'regular_price' => 1_200_000,
                // Even if a caller tried to sneak these in, the controller
                // never reads them from the request.
                'name' => 'Tên bị đổi',
                'sku' => 'HACKED',
                'category' => 'Nhóm khác',
            ]);

        $product->refresh();
        $this->assertSame('Bàn Basic', $product->name);
        $this->assertSame('BAN-01', $product->sku);
        $this->assertSame('Bàn - Tables', $product->category);
    }

    public function test_sales_cannot_update_a_products_price(): void
    {
        $product = Product::factory()->create(['regular_price' => 1_000_000]);

        $this->actingAs(User::factory()->sales()->create())
            ->patch(route('admin.products.update', $product), ['regular_price' => 1])
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'regular_price' => 1_000_000.00]);
    }
}
