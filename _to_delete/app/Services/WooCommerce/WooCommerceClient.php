<?php

namespace App\Services\WooCommerce;

use Illuminate\Support\Facades\Http;

class WooCommerceClient
{
    public function fetchProducts(int $page = 1): array
    {
        return Http::withBasicAuth(
            (string) config('services.woocommerce.key'),
            (string) config('services.woocommerce.secret'),
        )->get(rtrim((string) config('services.woocommerce.base_url'), '/').'/wp-json/wc/v3/products', [
            'per_page' => 100,
            'page' => $page,
        ])->throw()->json();
    }
}
