<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            // main | addon | fee — a quote has exactly one "main" row, any number of
            // "addon" and "fee" rows.
            $table->string('role')->index();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            // Snapshots so the quote stays correct even if the product catalog changes later.
            $table->string('product_name')->nullable();
            $table->string('sku')->nullable();
            $table->string('category')->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('length_cm')->nullable();
            $table->decimal('surcharge', 15, 2)->default(0);
            $table->string('formula_type')->nullable();
            $table->decimal('line_total', 15, 2)->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
