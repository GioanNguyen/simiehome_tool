<?php

namespace App\Services\Import;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
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

    // How many images downloadMany() fetches over the network at once.
    // A CSV import can have 1000+ products each needing their own image,
    // and fetching them one at a time (~200ms-1s of network latency each,
    // more on a slow/blocked source) made a full import take many
    // minutes even though each individual request is small and fast on
    // the server side. Firing a batch of these concurrently instead
    // turns "N sequential round trips" into "N / CONCURRENCY round
    // trips" without changing what gets downloaded or how it's cached.
    // Kept modest (rather than e.g. 50+) so this doesn't look like a
    // burst attack to the source site's own protections.
    private const CONCURRENCY = 8;

    private const KNOWN_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const LOCAL_PREFIX = '/storage/';

    private ?string $lastFailureReason = null;

    /**
     * Per-URL failure reasons from the most recent downloadMany() call
     * (mirrors lastFailureReason() for the single-URL download() method).
     * Only URLs that actually failed are present as keys.
     *
     * @var array<string, string>
     */
    private array $lastFailureReasonsByUrl = [];

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
     * The per-URL failure reasons from the most recent downloadMany()
     * call. Only meaningful right after a downloadMany() call — read it
     * before calling downloadMany() again.
     *
     * @return array<string, string>
     */
    public function lastFailureReasons(): array
    {
        return $this->lastFailureReasonsByUrl;
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

        $cached = $this->existingCachedUrl($url);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->get($url);
        } catch (Throwable $exception) {
            $this->lastFailureReason = 'Lỗi kết nối: '.$exception->getMessage();

            return $url;
        }

        $result = $this->processResponse($url, $response);
        $this->lastFailureReason = $result['failureReason'];

        return $result['path'];
    }

    /**
     * Fetches every given http(s) URL concurrently (in batches of
     * self::CONCURRENCY) and caches each one exactly like download()
     * would — same extension detection, compression, and local-storage
     * layout — just without paying for N sequential round trips.
     *
     * Non-http(s) URLs (already-local paths) are also accepted for
     * convenience and are recompressed synchronously via
     * recompressIfNeeded(), same as download(), since there's no network
     * call to batch for those anyway.
     *
     * @param  array<int, string|null>  $urls
     * @return array<string, string> map of original URL => cached
     *                               "/storage/..." path, or the original
     *                               URL unchanged on failure (same
     *                               contract as download()). Empty/blank
     *                               entries in $urls are skipped.
     */
    public function downloadMany(array $urls): array
    {
        $this->lastFailureReasonsByUrl = [];

        $results = [];
        $toFetch = [];

        foreach (array_unique(array_map(static fn ($url) => trim((string) $url), $urls)) as $url) {
            if ($url === '') {
                continue;
            }

            if (! Str::startsWith($url, ['http://', 'https://'])) {
                $results[$url] = $this->recompressIfNeeded($url);

                if ($this->lastFailureReason !== null) {
                    $this->lastFailureReasonsByUrl[$url] = $this->lastFailureReason;
                }

                continue;
            }

            $cached = $this->existingCachedUrl($url);

            if ($cached !== null) {
                $results[$url] = $cached;

                continue;
            }

            $toFetch[] = $url;
        }

        foreach (array_chunk($toFetch, self::CONCURRENCY) as $batch) {
            $results = array_merge($results, $this->fetchBatch($batch));
        }

        return $results;
    }

    /**
     * @param  array<int, string>  $batch
     * @return array<string, string>
     */
    private function fetchBatch(array $batch): array
    {
        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (string $url) => $pool->as($url)->timeout(self::TIMEOUT_SECONDS)->get($url),
            $batch,
        ));

        $results = [];

        foreach ($batch as $url) {
            $response = $responses[$url] ?? null;

            if ($response instanceof Throwable) {
                $results[$url] = $url;
                $this->lastFailureReasonsByUrl[$url] = 'Lỗi kết nối: '.$response->getMessage();

                continue;
            }

            if (! $response instanceof Response) {
                $results[$url] = $url;
                $this->lastFailureReasonsByUrl[$url] = 'Không nhận được phản hồi từ server';

                continue;
            }

            $result = $this->processResponse($url, $response);
            $results[$url] = $result['path'];

            if ($result['failureReason'] !== null) {
                $this->lastFailureReasonsByUrl[$url] = $result['failureReason'];
            }
        }

        return $results;
    }

    /**
     * Shared by download() and downloadMany(): validates, compresses and
     * stores an already-fetched HTTP response, or explains why it
     * couldn't.
     *
     * @return array{path: string, failureReason: ?string}
     */
    private function processResponse(string $url, Response $response): array
    {
        if (! $response->successful()) {
            return ['path' => $url, 'failureReason' => 'Server trả về HTTP '.$response->status()];
        }

        $body = $response->body();

        if ($body === '') {
            return ['path' => $url, 'failureReason' => 'Nội dung trả về rỗng'];
        }

        if (strlen($body) > self::MAX_BYTES) {
            $sizeMb = round(strlen($body) / 1024 / 1024, 1);

            return [
                'path' => $url,
                'failureReason' => "File nặng {$sizeMb}MB, vượt giới hạn ".(self::MAX_BYTES / 1024 / 1024).'MB',
            ];
        }

        $mime = Str::before($response->header('Content-Type') ?: '', ';');
        $extension = $this->extensionFromMime($mime) ?? $this->extensionFromUrl($url);

        if ($extension === null) {
            // Neither the response's content-type nor the URL itself look
            // like an image — leave the original URL alone rather than
            // caching something that isn't actually a picture.
            return ['path' => $url, 'failureReason' => "Không nhận dạng được là ảnh (content-type: \"{$mime}\")"];
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

        $disk = Storage::disk(self::DISK);
        $path = self::DIRECTORY.'/'.sha1($url).'.'.$extension;
        $disk->put($path, $body);

        return ['path' => '/storage/'.$path, 'failureReason' => null];
    }

    /**
     * The cached "/storage/..." URL for a URL already downloaded in a
     * previous run, or null when nothing is cached yet.
     */
    private function existingCachedUrl(string $url): ?string
    {
        $disk = Storage::disk(self::DISK);
        $stem = self::DIRECTORY.'/'.sha1($url);
        $cached = $this->existingCachedPath($disk, $stem);

        return $cached !== null ? '/storage/'.$cached : null;
    }

    private function recompressIfNeeded(string $url): string
    {
        $this->lastFailureReason = null;

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
