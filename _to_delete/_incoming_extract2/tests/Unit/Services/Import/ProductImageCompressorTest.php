<?php

namespace Tests\Unit\Services\Import;

use App\Services\Import\ProductImageCompressor;
use PHPUnit\Framework\TestCase;

class ProductImageCompressorTest extends TestCase
{
    public function test_it_re_encodes_a_large_image_as_a_smaller_jpeg(): void
    {
        $original = $this->pngBytes(2000, 1200);

        $result = (new ProductImageCompressor)->compress($original);

        $this->assertNotNull($result);
        $this->assertSame('jpg', $result['extension']);
        $this->assertLessThan(strlen($original), strlen($result['body']));

        $decoded = imagecreatefromstring($result['body']);
        $this->assertNotFalse($decoded);
        // Longest side capped, aspect ratio preserved (2000x1200 -> 900x540).
        $this->assertSame(900, imagesx($decoded));
        $this->assertSame(540, imagesy($decoded));
        imagedestroy($decoded);
    }

    public function test_it_leaves_a_small_image_at_its_original_size(): void
    {
        $original = $this->pngBytes(200, 150);

        $result = (new ProductImageCompressor)->compress($original);

        $this->assertNotNull($result);
        $decoded = imagecreatefromstring($result['body']);
        $this->assertSame(200, imagesx($decoded));
        $this->assertSame(150, imagesy($decoded));
        imagedestroy($decoded);
    }

    public function test_it_flattens_transparency_onto_white_instead_of_turning_black(): void
    {
        $canvas = imagecreatetruecolor(100, 100);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        ob_start();
        imagepng($canvas);
        $original = ob_get_clean();
        imagedestroy($canvas);

        $result = (new ProductImageCompressor)->compress($original);

        $this->assertNotNull($result);
        $decoded = imagecreatefromstring($result['body']);
        $pixel = imagecolorat($decoded, 50, 50);
        $rgb = imagecolorsforindex($decoded, $pixel);
        // Should be flattened to white, not black.
        $this->assertGreaterThan(200, $rgb['red']);
        $this->assertGreaterThan(200, $rgb['green']);
        $this->assertGreaterThan(200, $rgb['blue']);
        imagedestroy($decoded);
    }

    public function test_it_returns_null_for_bytes_that_are_not_a_decodable_image(): void
    {
        $result = (new ProductImageCompressor)->compress('this is definitely not an image');

        $this->assertNull($result);
    }

    public function test_it_returns_null_for_an_empty_string(): void
    {
        $result = (new ProductImageCompressor)->compress('');

        $this->assertNull($result);
    }

    private function pngBytes(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 100, 140, 90);
        imagefill($image, 0, 0, $color);

        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
