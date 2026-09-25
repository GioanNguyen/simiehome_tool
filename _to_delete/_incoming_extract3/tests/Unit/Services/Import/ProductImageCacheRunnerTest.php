<?php

namespace Tests\Unit\Services\Import;

use App\Models\Product;
use App\Services\Import\ProductImageCacheRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageCacheRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_downloads_every_product_with_a_remote_image_url_and_leaves_the_rest_alone(): void
    {
        Storage::fake('public');
        Http::fake([
            'simiehome.com/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $remote = Product::factory()->create(['image_url' => 'https://simiehome.com/a.jpg']);
        $alreadyLocal = Product::factory()->create(['image_url' => '/storage/products/already.jpg']);
        $noImage = Product::factory()->create(['image_url' => null]);

        $runner = app(ProductImageCacheRunner::class);

        $this->assertSame(1, $runner->countPending());

        $result = $runner->run();

        $this->assertSame(1, $result->total);
        $this->assertSame(1, $result->downloaded);
        $this->assertSame(0, $result->unchanged);

        $remote->refresh();
        $this->assertStringStartsWith('/storage/products/', $remote->image_url);
        $this->assertStringEndsWith('.jpg', $remote->image_url);

        $alreadyLocal->refresh();
        $this->assertSame('/storage/products/already.jpg', $alreadyLocal->image_url);

        $noImage->refresh();
        $this->assertNull($noImage->image_url);

        $this->assertSame(0, $runner->countPending());
    }

    public function test_it_keeps_a_product_with_a_failed_download_pending_for_a_future_run(): void
    {
        Storage::fake('public');
        Http::fake([
            'simiehome.com/*' => Http::response('server error', 500),
        ]);

        Product::factory()->create(['name' => 'Ghế Broken', 'image_url' => 'https://simiehome.com/broken.jpg']);

        $runner = app(ProductImageCacheRunner::class);
        $result = $runner->run();

        $this->assertSame(1, $result->total);
        $this->assertSame(0, $result->downloaded);
        $this->assertSame(1, $result->unchanged);
        $this->assertSame(1, $runner->countPending());

        $this->assertCount(1, $result->failures);
        $this->assertSame('Ghế Broken', $result->failures[0]['name']);
        $this->assertSame('https://simiehome.com/broken.jpg', $result->failures[0]['url']);
        $this->assertStringContainsString('HTTP 500', $result->failures[0]['reason']);
    }

    public function test_it_invokes_the_progress_callback_once_per_processed_product(): void
    {
        Storage::fake('public');
        Http::fake([
            'simiehome.com/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        Product::factory()->count(3)->create(['image_url' => 'https://simiehome.com/x.jpg']);

        $seen = 0;
        app(ProductImageCacheRunner::class)->run(function () use (&$seen) {
            $seen++;
        });

        $this->assertSame(3, $seen);
    }
}
