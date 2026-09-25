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
            // Per-product surcharge from the "Extra fee" export column (e.g.
            // a fixed handling/installation fee added on top of the catalog
            // price for this product). Kept separate from the pricing
            // rules' own manual_extra_fee (entered by hand at quote time) —
            // this one comes from the catalog itself.
            $table->decimal('extra_fee', 15, 2)->nullable()->after('base_price');

            // Per-product minimum size (cm) from the "Minimum length" export
            // column — the smallest length a custom_formula quote is
            // allowed to use for this product, below which the catalog
            // doesn't consider the item makeable/sellable.
            $table->decimal('minimum_length', 15, 2)->nullable()->after('extra_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['extra_fee', 'minimum_length']);
        });
    }
};
