<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_the_import_page_to_authenticated_users(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.product-imports.index'))
            ->assertOk()
            ->assertSee('Import sản phẩm từ CSV');
    }

    public function test_it_imports_an_uploaded_csv_file(): void
    {
        Storage::fake('public');
        Http::fake([
            'example.com/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $csv = file_get_contents(base_path('tests/Fixtures/sample-products.csv'));
        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.product-imports.store'), ['file' => $file])
            ->assertRedirect(route('admin.product-imports.index'));

        $this->assertDatabaseCount('products', 6);
        $this->assertDatabaseHas('import_runs', [
            'source' => 'csv_import',
            'status' => 'success_with_errors',
            'processed_count' => 6,
        ]);

        // The CSV's "Images" column points at an external URL for every
        // row — after the admin upload finishes, those should already be
        // cached locally rather than left hotlinking example.com.
        $this->assertStringStartsWith('/storage/products/', Product::query()->first()->image_url);
    }

    public function test_the_import_console_command_reports_a_summary(): void
    {
        Storage::fake('public');
        Http::fake([
            'example.com/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->artisan('products:import', ['path' => base_path('tests/Fixtures/sample-products.csv')])
            ->assertSuccessful();

        $this->assertDatabaseCount('products', 6);
        $this->assertStringStartsWith('/storage/products/', Product::query()->first()->image_url);
    }

    public function test_the_import_console_command_fails_for_a_missing_file(): void
    {
        $this->artisan('products:import', ['path' => '/tmp/does-not-exist.csv'])
            ->assertFailed();
    }
}
