<?php

namespace Tests\Unit\Services\Pdf;

use App\Services\Pdf\RemoteImageEmbedder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RemoteImageEmbedderTest extends TestCase
{
    public function test_it_returns_null_for_a_null_or_empty_url(): void
    {
        $embedder = new RemoteImageEmbedder;

        $this->assertNull($embedder->toDataUri(null));
        $this->assertNull($embedder->toDataUri(''));
    }

    public function test_it_returns_null_for_a_non_http_url_without_making_a_request(): void
    {
        Http::fake();

        $embedder = new RemoteImageEmbedder;

        $this->assertNull($embedder->toDataUri('/local/relative/path.jpg'));
        $this->assertNull($embedder->toDataUri('javascript:alert(1)'));

        Http::assertNothingSent();
    }

    public function test_it_embeds_a_successful_image_response_as_a_base64_data_uri(): void
    {
        Http::fake([
            'example.com/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $embedder = new RemoteImageEmbedder;
        $dataUri = $embedder->toDataUri('https://example.com/photo.jpg');

        $this->assertSame('data:image/jpeg;base64,'.base64_encode('fake-image-bytes'), $dataUri);
    }

    public function test_it_returns_null_when_the_response_is_not_an_image(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $embedder = new RemoteImageEmbedder;

        $this->assertNull($embedder->toDataUri('https://example.com/not-an-image'));
    }

    public function test_it_returns_null_when_the_request_fails(): void
    {
        Http::fake([
            'example.com/*' => Http::response('server error', 500),
        ]);

        $embedder = new RemoteImageEmbedder;

        $this->assertNull($embedder->toDataUri('https://example.com/broken.jpg'));
    }

    public function test_it_only_fetches_the_same_url_once(): void
    {
        Http::fake([
            'example.com/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/png']),
        ]);

        $embedder = new RemoteImageEmbedder;
        $embedder->toDataUri('https://example.com/photo.png');
        $embedder->toDataUri('https://example.com/photo.png');

        Http::assertSentCount(1);
    }

    public function test_it_reads_a_locally_cached_product_image_without_any_http_call(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/abc.png', 'local-image-bytes');
        Http::fake();

        $embedder = new RemoteImageEmbedder;
        $dataUri = $embedder->toDataUri('/storage/products/abc.png');

        $this->assertSame('data:image/png;base64,'.base64_encode('local-image-bytes'), $dataUri);
        Http::assertNothingSent();
    }

    public function test_it_returns_null_when_the_locally_cached_file_is_missing(): void
    {
        Storage::fake('public');

        $embedder = new RemoteImageEmbedder;

        $this->assertNull($embedder->toDataUri('/storage/products/missing.png'));
    }

    public function test_it_returns_null_for_a_local_path_with_an_unrecognised_extension(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/abc.bin', 'not-really-an-image');

        $embedder = new RemoteImageEmbedder;

        $this->assertNull($embedder->toDataUri('/storage/products/abc.bin'));
    }
}
