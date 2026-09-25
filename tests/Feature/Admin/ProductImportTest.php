<?php

namespace Tests\Feature\Admin;

use App\Models\ImportRun;
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

    public function test_it_skips_downloading_images_when_update_images_is_unchecked(): void
    {
        Storage::fake('public');
        Http::fake([
            'example.com/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $csv = file_get_contents(base_path('tests/Fixtures/sample-products.csv'));
        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.product-imports.store'), [
                'file' => $file,
                'update_images' => '0',
            ])
            ->assertRedirect(route('admin.product-imports.index'));

        $this->assertDatabaseCount('products', 6);

        // Data (name/price/...) is still imported, but the image URL is
        // left exactly as the CSV had it — never fetched/cached.
        $this->assertStringStartsWith('https://example.com/', Product::query()->first()->image_url);
        Http::assertNothingSent();

        $this->assertDatabaseHas('import_runs', [
            'source' => 'csv_import',
        ]);
        $this->assertStringContainsString(
            'chỉ cập nhật dữ liệu',
            ImportRun::query()->latest()->first()->message,
        );
    }

    public function test_it_applies_the_submitted_filters_to_the_import(): void
    {
        Storage::fake('public');
        Http::fake([
            'example.com/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $csv = file_get_contents(base_path('tests/Fixtures/sample-products.csv'));
        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.product-imports.store'), [
                'file' => $file,
                'types' => ['simple'],
            ])
            ->assertRedirect(route('admin.product-imports.index'));

        // Only the 2 otherwise-visible "simple" rows should have been kept.
        $this->assertDatabaseCount('products', 2);
        $this->assertSame(['simple', 'simple'], Product::query()->pluck('type')->all());
    }

    public function test_it_rejects_an_invalid_status_filter_value(): void
    {
        $csv = file_get_contents(base_path('tests/Fixtures/sample-products.csv'));
        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.product-imports.store'), [
                'file' => $file,
                'statuses' => ['not-a-real-status'],
            ])
            ->assertSessionHasErrors('statuses.0');
    }
}
