<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The WooCommerce API sync feature (Admin > WooCommerce sync) was removed —
 * CSV import (App\Services\Import\ProductCsvImporter, `products:import`,
 * Admin > Import sản phẩm) is the only supported way to load products now.
 *
 * The `sync_runs` table wasn't WooCommerce-only, though: it also logs CSV
 * import history (`source = 'csv_import'`), which is staying. So instead of
 * dropping it, rename it to `import_runs` — a name that isn't tied to the
 * sync feature that no longer exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sync_runs') && ! Schema::hasTable('import_runs')) {
            Schema::rename('sync_runs', 'import_runs');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('import_runs') && ! Schema::hasTable('sync_runs')) {
            Schema::rename('import_runs', 'sync_runs');
        }
    }
};
