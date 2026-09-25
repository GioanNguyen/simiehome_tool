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

    public function test_it_recompresses_a_local_file_that_was_left_uncompressed_by_a_previous_run(): void
    {
        Storage::fake('public');
        $original = $this->noisyPngBytes();
        Storage::disk('public')->put('products/stuck.png', $original);

        $product = Product::factory()->create(['image_url' => '/storage/products/stuck.png']);

        $runner = app(ProductImageCacheRunner::class);
        $this->assertSame(1, $runner->countPending());

        $result = $runner->run();

        $this->assertSame(1, $result->downloaded);
        $product->refresh();
        $this->assertSame('/storage/products/stuck.jpg', $product->image_url);
        Storage::disk('public')->assertMissing('products/stuck.png');
        $this->assertSame(0, $runner->countPending());
    }

    private function noisyPngBytes(): string
    {
        $image = imagecreatetruecolor(1600, 1600);

        for ($x = 0; $x < 1600; $x += 4) {
            for ($y = 0; $y < 1600; $y += 4) {
                $color = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
                imagefilledrectangle($image, $x, $y, $x + 3, $y + 3, $color);
            }
        }

        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
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
