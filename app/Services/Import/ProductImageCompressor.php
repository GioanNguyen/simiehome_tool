<?php

namespace App\Services\Import;

use Throwable;

/**
 * Resizes and re-encodes a downloaded product image as a JPEG, so the
 * copy cached on disk (and the memory used to decode a full-resolution
 * WooCommerce original) stays small — some source photos are several
 * megapixels, which is unnecessary for a small quote-screen thumbnail
 * and adds up fast across a catalog of a thousand-plus products.
 *
 * Uses the GD extension (bundled with PHP almost everywhere, no extra
 * Composer package needed). Anything GD can't decode — corrupt bytes,
 * an exotic format — is left alone; the caller falls back to storing the
 * original downloaded bytes as-is rather than losing the image entirely.
 */
final class ProductImageCompressor
{
    private const MAX_DIMENSION = 900; // px, longest side

    private const JPEG_QUALITY = 78;

    // A GD truecolor decode costs roughly width * height * 4 bytes — an
    // image already this large on either side is skipped rather than
    // risking a decode-time memory spike of its own.
    private const MAX_INPUT_DIMENSION = 6000;

    /**
     * @return array{body: string, extension: string}|null null when the
     *                                                     input can't be
     *                                                     processed and
     *                                                     the caller
     *                                                     should keep
     *                                                     the original
     *                                                     bytes instead.
     */
    public function compress(string $body): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $size = @getimagesizefromstring($body);

        if ($size === false) {
            return null;
        }

        [$width, $height] = $size;

        if ($width <= 0 || $height <= 0 || $width > self::MAX_INPUT_DIMENSION || $height > self::MAX_INPUT_DIMENSION) {
            return null;
        }

        try {
            $source = @imagecreatefromstring($body);

            if ($source === false) {
                return null;
            }

            [$targetWidth, $targetHeight] = $this->targetDimensions($width, $height);

            $canvas = @imagecreatetruecolor($targetWidth, $targetHeight);

            if ($canvas === false) {
                // GD couldn't allocate the canvas — most likely transient
                // memory pressure a long batch run (thousands of these in
                // a row). Explicitly bailing out here, rather than letting
                // the next GD call fail on a bad handle, keeps this a
                // clean "skip and keep the original" instead of depending
                // on a TypeError happening to get caught below.
                imagedestroy($source);

                return null;
            }

            // Flatten onto a white background — most product photos are
            // already opaque, and JPEG has no alpha channel anyway, so a
            // transparent PNG/WebP source would otherwise turn black.
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            imagealphablending($canvas, true);

            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            imagedestroy($source);

            ob_start();
            imagejpeg($canvas, null, self::JPEG_QUALITY);
            $jpeg = ob_get_clean();
            imagedestroy($canvas);

            if ($jpeg === false || $jpeg === '') {
                return null;
            }

            return ['body' => $jpeg, 'extension' => 'jpg'];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function targetDimensions(int $width, int $height): array
    {
        $longestSide = max($width, $height);

        if ($longestSide <= self::MAX_DIMENSION) {
            return [$width, $height];
        }

        $scale = self::MAX_DIMENSION / $longestSide;

        return [
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        ];
    }
}
