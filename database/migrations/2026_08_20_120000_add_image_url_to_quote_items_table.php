<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of the product's image URL at the moment the quote was saved —
 * same reasoning as product_name/sku/category already being duplicated
 * onto quote_items instead of always joining back to `products`: a quote
 * is a point-in-time record, so a later catalog edit (or the product being
 * deleted) must never change what an already-saved quote/PDF shows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
