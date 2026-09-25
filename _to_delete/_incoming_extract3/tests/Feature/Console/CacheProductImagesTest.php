<?php

namespace Tests\Feature\Console;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CacheProductImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_when_there_is_nothing_to_do(): void
    {
        $this->artisan('products:cache-images')
            ->expectsOutputToContain('Không có ảnh sản phẩm nào cần tải')
            ->assertExitCode(0);
    }

    public function test_it_downloads_pending_product_images_and_reports_a_summary(): void
    {
        Storage::fake('public');
        Http::fake([
            'simiehome.com/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        Product::factory()->create(['image_url' => 'https://simiehome.com/a.jpg']);

        $this->artisan('products:cache-images')
            ->expectsOutputToContain('Đã tải và lưu local: 1')
            ->assertExitCode(0);

        $product = Product::query()->first();
        $this->assertStringStartsWith('/storage/products/', $product->image_url);
    }

    public function test_it_prints_the_reason_for_each_failed_download_grouped_by_reason(): void
    {
        Storage::fake('public');
        Http::fake([
            'simiehome.com/*' => Http::response('server error', 404),
        ]);

        Product::factory()->create(['name' => 'Ghế Không Có', 'image_url' => 'https://simiehome.com/missing.jpg']);

        $this->artisan('products:cache-images')
            ->expectsOutputToContain('Không tải được, vẫn giữ URL cũ để thử lại lần sau: 1')
            ->expectsOutputToContain('HTTP 404')
            ->expectsOutputToContain('Ghế Không Có')
            ->assertExitCode(0);
    }
}
