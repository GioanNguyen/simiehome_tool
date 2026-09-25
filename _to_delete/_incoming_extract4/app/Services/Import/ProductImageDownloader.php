<?php

namespace App\Services\Import;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Downloads a product image from its external source (the simiehome.com
 * WooCommerce export URLs) and caches it on the local "public" disk.
 *
 * Why: some source sites block cross-site <img> requests (hotlink /
 * Referer protection). The URL opens fine when pasted directly into the
 * browser's address bar, but silently fails to load when embedded as an
 * <img> on this app's own pages, because the browser sends a Referer
 * header for the app's domain. Caching the bytes locally and serving them
 * from this app's own origin sidesteps that entirely.
 *
 * The cached filename is a hash of the source URL, so re-running this
 * (via a fresh CSV import, or the `products:cache-images` command) never
 * re-downloads an image already on disk.
 *
 * The downloaded bytes are resized/re-encoded via ProductImageCompressor
 * before being written to disk, so a multi-megapixel WooCommerce original
 * doesn't get stored (and re-served) at full size for a small thumbnail.
 */
final class ProductImageDownloader
{
    private const DISK = 'public';

    private const DIRECTORY = 'products';

    private const MAX_BYTES = 10 * 1024 * 1024; // safety cap on the download itself

    private const TIMEOUT_SECONDS = 15;

    private const KNOWN_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const LOCAL_PREFIX = '/storage/';

    private ?string $lastFailureReason = null;

    public function __construct(
        private readonly ProductImageCompressor $compressor = new ProductImageCompressor,
    ) {}

    /**
     * The reason the most recent download() call fell back to the
     * original URL, or null when it wasn't a failure (empty input,
     * already local, or a successful download). Only meaningful right
     * after a download() call — read it before calling download() again.
     */
    public function lastFailureReason(): ?string
    {
        return $this->lastFailureReason;
    }

    /**
     * @return string|null The local "/storage/..." URL to store on the
     *                     product, or null when there is nothing usable
     *                     (empty input). On a transient failure (network
     *                     error, timeout, non-2xx, not actually an image)
     *                     the original URL is returned unchanged so a
     *                     later retry can still pick it up, instead of
     *                     losing the reference entirely — see
     *                     lastFailureReason() for why.
     */
    public function download(?string $url): ?string
    {
        $this->lastFailureReason = null;

        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // Already a local path from a previous download. Usually nothing
        // to do — except a file that's still a raw .png/.webp/.gif is one
        // that failed to compress on a previous run (e.g. transient
        // memory pressure partway through a big batch) and fell back to
        // the uncompressed original; retry compressing it now instead of
        // leaving it oversized on disk forever.
        if (! Str::startsWith($url, ['http://', 'https://'])) {
            return $this->recompressIfNeeded($url);
        }

        $disk = Storage::disk(self::DISK);
        $stem = self::DIRECTORY.'/'.sha1($url);

        $cached = $this->existingCachedPath($disk, $stem);

        if ($cached !== null) {
            return '/storage/'.$cached;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->get($url);
        } catch (Throwable $exception) {
            $this->lastFailureReason = 'Lỗi kết nối: '.$exception->getMessage();

            return $url;
        }

        if (! $response->successful()) {
            $this->lastFailureReason = 'Server trả về HTTP '.$response->status();

            return $url;
        }

        $body = $response->body();

        if ($body === '') {
            $this->lastFailureReason = 'Nội dung trả về rỗng';

            return $url;
        }

        if (strlen($body) > self::MAX_BYTES) {
            $sizeMb = round(strlen($body) / 1024 / 1024, 1);
            $this->lastFailureReason = "File nặng {$sizeMb}MB, vượt giới hạn ".(self::MAX_BYTES / 1024 / 1024).'MB';

            return $url;
        }

        $mime = Str::before($response->header('Content-Type') ?: '', ';');
        $extension = $this->extensionFromMime($mime) ?? $this->extensionFromUrl($url);

        if ($extension === null) {
            // Neither the response's content-type nor the URL itself look
            // like an image — leave the original URL alone rather than
            // caching something that isn't actually a picture.
            $this->lastFailureReason = "Không nhận dạng được là ảnh (content-type: \"{$mime}\")";

            return $url;
        }

        // Free the response/Guzzle objects before doing anything else —
        // downloading thousands of images in one long-running process
        // (the CSV import / `products:cache-images` backfill) otherwise
        // accumulates un-collected cycles from the HTTP client fast
        // enough to exhaust PHP's memory limit well before the run
        // finishes, even though each individual response is small.
        unset($response);

        $compressed = $this->compressor->compress($body);

        if ($compressed !== null) {
            $body = $compressed['body'];
            $extension = $compressed['extension'];
        }

        $path = $stem.'.'.$extension;
        $disk->put($path, $body);

        return '/storage/'.$path;
    }

    private function recompressIfNeeded(string $url): string
    {
        if (! Str::startsWith($url, self::LOCAL_PREFIX) || $this->hasJpegExtension($url)) {
            return $url;
        }

        $disk = Storage::disk(self::DISK);
        $relativePath = Str::after($url, self::LOCAL_PREFIX);

        if (! $disk->exists($relativePath)) {
            $this->lastFailureReason = 'File local không còn tồn tại trên đĩa';

            return $url;
        }

        try {
            $body = $disk->get($relativePath);
        } catch (Throwable $exception) {
            $this->lastFailureReason = 'Không đọc được file local: '.$exception->getMessage();

            return $url;
        }

        $compressed = $this->compressor->compress($body);

        if ($compressed === null) {
            $this->lastFailureReason = 'Không nén lại được ảnh này (định dạng không hỗ trợ hoặc thiếu bộ nhớ)';

            return $url;
        }

        $newPath = Str::beforeLast($relativePath, '.').'.'.$compressed['extension'];
        $disk->put($newPath, $compressed['body']);

        if ($newPath !== $relativePath) {
            $disk->delete($relativePath);
        }

        return '/storage/'.$newPath;
    }

    private function hasJpegExtension(string $url): bool
    {
        return in_array(strtolower(pathinfo($url, PATHINFO_EXTENSION)), ['jpg', 'jpeg'], true);
    }

    private function existingCachedPath(Filesystem $disk, string $stem): ?string
    {
        foreach (self::KNOWN_EXTENSIONS as $extension) {
            $candidate = $stem.'.'.$extension;

            if ($disk->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function extensionFromMime(string $mime): ?string
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => null,
        };
    }

    private function extensionFromUrl(string $url): ?string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        return in_array($extension, self::KNOWN_EXTENSIONS, true) ? $extension : null;
    }
}
