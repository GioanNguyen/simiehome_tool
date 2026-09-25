<?php

namespace Tests\Feature\Admin;

use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunWooCommerceSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_woocommerce_products_into_the_local_catalog(): void
    {
        Http::fake([
            'https://example.com/wp-json/wc/v3/products*' => Http::response([
                [
                    'id' => 10,
                    'type' => 'simple',
                    'sku' => 'BAN-140',
                    'name' => 'Ban 1m4',
                    'regular_price' => '4000000',
                    'status' => 'publish',
                    'categories' => [['name' => 'Bàn - Tables']],
                    'attributes' => [],
                ],
            ], 200),
        ]);

        config()->set('services.woocommerce.base_url', 'https://example.com');
        config()->set('services.woocommerce.key', 'ck_test');
        config()->set('services.woocommerce.secret', 'cs_test');

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.sync-runs.store'))
            ->assertRedirect(route('admin.sync-runs.index'));

        $this->assertDatabaseHas('products', [
            'external_id' => 10,
            'sku' => 'BAN-140',
            'name' => 'Ban 1m4',
            'category' => 'Bàn - Tables',
        ]);

        $this->assertDatabaseHas('sync_runs', [
            'source' => 'woocommerce',
            'status' => 'success',
            'processed_count' => 1,
            'success_count' => 1,
        ]);
    }

    public function test_it_shows_sync_runs_to_authenticated_users(): void
    {
        SyncRun::query()->create([
            'source' => 'woocommerce',
            'status' => 'success',
            'processed_count' => 1,
            'success_count' => 1,
            'failed_count' => 0,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.sync-runs.index'))
            ->assertOk()
            ->assertSee('Sync runs')
            ->assertSee('success');
    }
}
