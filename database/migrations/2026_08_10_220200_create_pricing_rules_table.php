<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            // A rule with product_id = null applies to every product in `category`.
            // A rule with product_id set overrides the category rule for that one product.
            $table->string('category')->nullable()->index();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            // catalog_price | length_ratio | fixed | quantity
            $table->string('formula_type')->default('catalog_price');
            $table->decimal('surcharge_amount', 15, 2)->default(0);
            // Extra parameters the formula needs (e.g. {"unit_cm": 100, "fixed_price": 500000}).
            $table->json('formula_config')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['category', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
