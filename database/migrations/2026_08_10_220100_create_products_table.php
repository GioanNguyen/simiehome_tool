<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // Stable identifier from the source catalog export (WooCommerce "ID" column).
            // SKU is not reliable enough to be the mapping key: most rows in the real
            // export have no SKU at all, so external_id is the primary lookup key.
            $table->unsignedBigInteger('external_id')->nullable()->unique();
            // For "variation" rows, points at the parent "variable" product's external_id.
            $table->unsignedBigInteger('parent_external_id')->nullable()->index();
            // simple | variable | variation (WooCommerce product types)
            $table->string('type')->default('simple')->index();
            $table->string('sku')->nullable()->index();
            $table->string('name')->index();
            // Top-level product group (e.g. "Bàn - Tables", "Ghế - Chairs"). Drives which
            // pricing rule the Pricing Engine applies.
            $table->string('category')->nullable()->index();
            $table->decimal('regular_price', 15, 2)->nullable();
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->string('image_url')->nullable();
            // Free-form attribute map (Size, Color, ...) kept for detail display.
            $table->json('attributes_json')->nullable();
            $table->string('status')->default('active')->index();
            $table->string('source')->default('csv_import');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
