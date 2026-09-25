<?php

namespace Tests\Unit\Services\Import;

use App\Models\Product;
use App\Services\Import\ProductCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCsvImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_products_and_skips_rows_missing_required_fields(): void
    {
        $summary = (new ProductCsvImporter)->import(
            base_path('tests/Fixtures/sample-products.csv'),
        );

        $this->assertSame(10, $summary->totalRows);
        $this->assertSame(6, $summary->created);
        // 1 row missing ID/Name, plus 3 rows excluded for not being
        // "visible" in the catalog (a hidden simple product, a "catalog"
        // visible variable parent, and its variation, which inherits the
        // parent's non-visible status).
        $this->assertSame(4, $summary->skipped);
        $this->assertCount(2, $summary->errors);

        $this->assertDatabaseCount('products', 6);
    }

    public function test_it_uses_external_id_not_sku_as_the_mapping_key(): void
    {
        (new ProductCsvImporter)->import(base_path('tests/Fixtures/sample-products.csv'));

        $mirror = Product::query()->where('external_id', 1)->first();

        $this->assertSame('GUONG-A', $mirror->sku);
        $this->assertSame('simple', $mirror->type);
        $this->assertSame('Trang trí & gia dụng', $mirror->category);
        $this->assertSame(1040000.0, (float) $mirror->sale_price);
        $this->assertSame(900000.0, (float) $mirror->base_price);
    }

    public function test_it_leaves_base_price_null_when_the_csv_column_is_blank(): void
    {
        (new ProductCsvImporter)->import(base_path('tests/Fixtures/sample-products.csv'));

        $variation = Product::query()->where('external_id', 3)->first();

        $this->assertNull($variation->base_price);
    }

    public function test_it_inherits_category_for_a_variation_referenced_by_parent_id(): void
    {
        (new ProductCsvImporter)->import(base_path('tests/Fixtures/sample-products.csv'));

        $variation = Product::query()->where('external_id', 3)->first();

        $this->assertSame('variation', $variation->type);
        $this->assertSame(2, $variation->parent_external_id);
        $this->assertSame('Bàn - Tables', $variation->category);
        $this->assertSame(['Size' => '120x60x75'], $variation->attributes_json);
    }

    public function test_it_inherits_category_for_a_variation_referenced_by_parent_sku(): void
    {
        (new ProductCsvImporter)->import(base_path('tests/Fixtures/sample-products.csv'));

        $parent = Product::query()->where('external_id', 4)->first();
        $variation = Product::query()->where('external_id', 5)->first();

        $this->assertSame($parent->external_id, $variation->parent_external_id);
        $this->assertSame('Ghế - Chairs', $variation->category);
    }

    public function test_it_skips_a_product_whose_visibility_in_catalog_is_not_visible(): void
    {
        (new ProductCsvImporter)->import(base_path('tests/Fixtures/sample-products.csv'));

        $this->assertNull(Product::query()->where('external_id', 7)->first());
    }

    public function test_it_skips_a_variable_parent_and_its_variation_when_the_parent_is_not_visible(): void
    {
        (new ProductCsvImporter)->import(base_path('tests/Fixtures/sample-products.csv'));

        // external_id 8 is the "catalog"-visibility variable parent,
        // external_id 9 is its variation (no visibility value of its own —
        // it inherits the parent's, and must be excluded too).
        $this->assertNull(Product::query()->where('external_id', 8)->first());
        $this->assertNull(Product::query()->where('external_id', 9)->first());
    }

    public function test_it_marks_unpublished_rows_as_draft(): void
    {
        (new ProductCsvImporter)->import(base_path('tests/Fixtures/sample-products.csv'));

        $draft = Product::query()->where('external_id', 6)->first();

        $this->assertSame('draft', $draft->status);
    }

    public function test_reimporting_the_same_file_updates_instead_of_duplicating(): void
    {
        $importer = new ProductCsvImporter;
        $importer->import(base_path('tests/Fixtures/sample-products.csv'));
        $summary = $importer->import(base_path('tests/Fixtures/sample-products.csv'));

        $this->assertSame(0, $summary->created);
        $this->assertSame(6, $summary->updated);
        $this->assertDatabaseCount('products', 6);
    }
}
