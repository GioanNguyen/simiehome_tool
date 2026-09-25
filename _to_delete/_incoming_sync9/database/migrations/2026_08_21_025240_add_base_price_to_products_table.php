<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Base price for a custom/bespoke size not already covered by a
            // catalog SKU or variation — used by the "custom_formula"
            // pricing rule type (e.g. "base_price + length_cm * 5000") so a
            // sales user can quote a size the CSV catalog doesn't list.
            // Distinct from regular_price/sale_price, which are the fixed
            // prices for the sizes the catalog *does* list.
            $table->decimal('base_price', 15, 2)->nullable()->after('sale_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('base_price');
        });
    }
};
