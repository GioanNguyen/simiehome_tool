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
 */
final class ProductImageDownloader
{
    private const DISK = 'public';

    private const DIRECTORY = 'products';

    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB safety cap

    private const TIMEOUT_SECONDS = 8;

    private const KNOWN_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * @return string|null The local "/storage/..." URL to store on the
     *                     product, or null when there is nothing usable
     *                     (empty input). On a transient failure (network
     *                     error, timeout, non-2xx, not actually an image)
     *                     the original URL is returned unchanged so a
     *                     later retry can still pick it up, instead of
     *                     losing the reference entirely.
     */
    public function download(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // Already a local path from a previous download — nothing to do.
        if (! Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        $disk = Storage::disk(self::DISK);
        $stem = self::DIRECTORY.'/'.sha1($url);

        $cached = $this->existingCachedPath($disk, $stem);

        if ($cached !== null) {
            return '/storage/'.$cached;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->get($url);
        } catch (Throwable) {
            return $url;
        }

        if (! $response->successful()) {
            return $url;
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            return $url;
        }

        $mime = Str::before($response->header('Content-Type') ?: '', ';');
        $extension = $this->extensionFromMime($mime) ?? $this->extensionFromUrl($url);

        if ($extension === null) {
            // Neither the response's content-type nor the URL itself look
            // like an image — leave the original URL alone rather than
            // caching something that isn't actually a picture.
            return $url;
        }

        $path = $stem.'.'.$extension;
        $disk->put($path, $body);

        return '/storage/'.$path;
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
