<?php

namespace Tests\Unit\Services\Import;

use App\Services\Import\ProductImageDownloader;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductImageDownloaderTest extends TestCase
{
    public function test_it_returns_null_for_an_empty_url(): void
    {
        $downloader = new ProductImageDownloader;

        $this->assertNull($downloader->download(null));
        $this->assertNull($downloader->download(''));
    }

    public function test_it_leaves_an_already_local_path_untouched(): void
    {
        Http::fake();

        $downloader = new ProductImageDownloader;

        $this->assertSame('/storage/products/abc.jpg', $downloader->download('/storage/products/abc.jpg'));

        Http::assertNothingSent();
    }

    public function test_it_downloads_and_caches_a_remote_image_returning_a_local_storage_path(): void
    {
        Storage::fake('public');
        Http::fake([
            'simiehome.com/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/png']),
        ]);

        $downloader = new ProductImageDownloader;
        $path = $downloader->download('https://simiehome.com/wp-content/uploads/2023/06/11.png');

        $expected = '/storage/products/'.sha1('https://simiehome.com/wp-content/uploads/2023/06/11.png').'.png';

        $this->assertSame($expected, $path);
        Storage::disk('public')->assertExists(ltrim(Str::after($expected, '/storage/'), '/'));
        $this->assertSame(
            'fake-image-bytes',
            Storage::disk('public')->get(Str::after($expected, '/storage/')),
        );
    }

    public function test_it_reuses_an_already_cached_file_without_downloading_again(): void
    {
        Storage::fake('public');
        $url = 'https://simiehome.com/wp-content/uploads/2023/06/11.png';
        $stem = 'products/'.sha1($url);
        Storage::disk('public')->put($stem.'.jpg', 'already-there');

        Http::fake();

        $downloader = new ProductImageDownloader;
        $path = $downloader->download($url);

        $this->assertSame('/storage/'.$stem.'.jpg', $path);
        Http::assertNothingSent();
    }

    public function test_it_keeps_the_original_url_when_the_request_fails(): void
    {
        Storage::fake('public');
        Http::fake([
            'simiehome.com/*' => Http::response('server error', 500),
        ]);

        $downloader = new ProductImageDownloader;
        $url = 'https://simiehome.com/broken.jpg';

        $this->assertSame($url, $downloader->download($url));
    }

    public function test_it_keeps_the_original_url_when_the_request_throws(): void
    {
        Storage::fake('public');
        Http::fake(function (Request $request) {
            throw new ConnectionException('timed out');
        });

        $downloader = new ProductImageDownloader;
        $url = 'https://simiehome.com/timeout.jpg';

        $this->assertSame($url, $downloader->download($url));
    }

    public function test_it_keeps_the_original_url_when_the_response_is_not_recognisable_as_an_image(): void
    {
        Storage::fake('public');
        Http::fake([
            'simiehome.com/*' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $downloader = new ProductImageDownloader;
        $url = 'https://simiehome.com/not-an-image';

        $this->assertSame($url, $downloader->download($url));
    }

    public function test_it_falls_back_to_the_url_extension_when_content_type_is_missing(): void
    {
        Storage::fake('public');
        Http::fake([
            'simiehome.com/*' => Http::response('fake-image-bytes', 200, []),
        ]);

        $downloader = new ProductImageDownloader;
        $path = $downloader->download('https://simiehome.com/wp-content/uploads/2023/06/12.webp');

        $this->assertStringEndsWith('.webp', (string) $path);
    }

    public function test_it_compresses_a_real_image_down_to_a_smaller_jpeg(): void
    {
        Storage::fake('public');

        $original = $this->largePngBytes();

        Http::fake([
            'simiehome.com/*' => Http::response($original, 200, ['Content-Type' => 'image/png']),
        ]);

        $downloader = new ProductImageDownloader;
        $path = $downloader->download('https://simiehome.com/big.png');

        // Compression always re-encodes as JPEG, regardless of the
        // source format, and the cached bytes are smaller.
        $this->assertStringEndsWith('.jpg', (string) $path);
        $stored = Storage::disk('public')->get(Str::after($path, '/storage/'));
        $this->assertLessThan(strlen($original), strlen($stored));
    }

    private function largePngBytes(): string
    {
        // A flat fill compresses to almost nothing under PNG regardless
        // of size, which would make "the recompressed copy is smaller"
        // a meaningless assertion — a real product photo has texture, so
        // this fills the canvas with per-pixel noise to approximate that.
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
}
