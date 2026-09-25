<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->numberBetween(1, 999999),
            'parent_external_id' => null,
            'type' => 'simple',
            'sku' => strtoupper(fake()->bothify('SKU-###??')),
            'name' => fake()->words(3, true),
            'category' => fake()->randomElement(['Bàn - Tables', 'Ghế - Chairs', 'Giường - Beds']),
            'regular_price' => fake()->numberBetween(500_000, 5_000_000),
            'sale_price' => null,
            'base_price' => null,
            'extra_fee' => null,
            'minimum_length' => null,
            'image_url' => null,
            'attributes_json' => [],
            'status' => 'active',
            'source' => 'csv_import',
            'last_synced_at' => now(),
        ];
    }
}
