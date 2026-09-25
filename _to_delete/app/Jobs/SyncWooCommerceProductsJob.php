<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\SyncRun;
use App\Services\WooCommerce\WooCommerceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Optional alternate sync path via the WooCommerce REST API. The primary,
 * supported way to load products is the CSV import (see
 * App\Services\Import\ProductCsvImporter and `products:import`), which does
 * not require API credentials or network access to the WordPress site.
 */
class SyncWooCommerceProductsJob implements ShouldQueue
{
    use Queueable;

    public function handle(WooCommerceClient $client): void
    {
        $syncRun = SyncRun::query()->create([
            'source' => 'woocommerce',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $products = $client->fetchProducts();

            foreach ($products as $product) {
                if (empty($product['id'])) {
                    continue;
                }

                Product::query()->updateOrCreate(
                    ['external_id' => $product['id']],
                    [
                        'type' => $product['type'] ?? 'simple',
                        'sku' => $product['sku'] ?? null,
                        'name' => $product['name'] ?? (string) $product['id'],
                        'category' => $product['categories'][0]['name'] ?? null,
                        'regular_price' => $product['regular_price'] ?? ($product['price'] ?? null),
                        'sale_price' => $product['sale_price'] ?? null,
                        'image_url' => $product['images'][0]['src'] ?? null,
                        'attributes_json' => $product['attributes'] ?? [],
                        'status' => ($product['status'] ?? 'publish') === 'publish' ? 'active' : 'draft',
                        'source' => 'woocommerce',
                        'last_synced_at' => now(),
                    ],
                );
            }

            $count = count($products);

            $syncRun->update([
                'status' => 'success',
                'finished_at' => now(),
                'processed_count' => $count,
                'success_count' => $count,
                'failed_count' => 0,
            ]);
        } catch (Throwable $exception) {
            $syncRun->update([
                'status' => 'failed',
                'finished_at' => now(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
