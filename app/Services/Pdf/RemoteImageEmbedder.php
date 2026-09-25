<?php

namespace App\Services\Pdf;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a product image reference into a base64 data: URI so dompdf can
 * render it without needing its own "remote enabled" fetching turned on —
 * dompdf fetching arbitrary URLs itself is an SSRF-style risk we'd rather
 * not carry, and a data URI also survives even if an image host is slow or
 * briefly unreachable at print time (the quote just prints without that
 * one picture instead of failing the whole PDF).
 *
 * Product images are cached locally on the "public" disk at import time
 * (see ProductImageDownloader) and referenced as a "/storage/..." path, so
 * the common case here is just reading a local file — no network call at
 * all. A plain http(s) URL is still supported and fetched remotely, for
 * any older saved quote whose snapshot predates local caching.
 *
 * One instance is meant to live for the lifetime of a single PDF render —
 * it memoizes by URL so the same product photo (e.g. reused as both main
 * item and an addon) is only resolved once per request.
 */
class RemoteImageEmbedder
{
    private const MAX_BYTES = 2 * 1024 * 1024; // 2 MB safety cap

    private const TIMEOUT_SECONDS = 4;

    private const LOCAL_PREFIX = '/storage/';

    /** @var array<string, string|null> */
    private array $cache = [];

    public function toDataUri(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (array_key_exists($url, $this->cache)) {
            return $this->cache[$url];
        }

        return $this->cache[$url] = Str::startsWith($url, ['http://', 'https://'])
            ? $this->fetchRemote($url)
            : $this->readLocal($url);
    }

    private function readLocal(string $url): ?string
    {
        // Only the "/storage/..." paths this app generates itself (see
        // ProductImageDownloader) are expected here — anything else is
        // unrecognised data, not a path to read off disk.
        if (! Str::startsWith($url, self::LOCAL_PREFIX)) {
            return null;
        }

        $relativePath = Str::after($url, self::LOCAL_PREFIX);
        $disk = Storage::disk('public');

        if (! $disk->exists($relativePath)) {
            return null;
        }

        try {
            $body = $disk->get($relativePath);
        } catch (Throwable) {
            return null;
        }

        if ($body === null || $body === '' || strlen($body) > self::MAX_BYTES) {
            return null;
        }

        $mime = $this->mimeFromExtension($relativePath);

        if ($mime === null) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($body);
    }

    private function mimeFromExtension(string $path): ?string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => null,
        };
    }

    private function fetchRemote(string $url): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            return null;
        }

        $mime = $response->header('Content-Type') ?: 'image/jpeg';
        $mime = Str::before($mime, ';'); // strip a trailing "; charset=..." if present

        if (! Str::startsWith($mime, 'image/')) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($body);
    }
}
