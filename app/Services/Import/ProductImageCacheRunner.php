<?php

namespace App\Services\Import;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sweeps every product that still needs image work — either `image_url`
 * points at an external source (http/https) and needs downloading, or it's
 * already a local file that isn't a .jpg (i.e. a previous run's download
 * succeeded but compressing it failed, e.g. transient memory pressure, and
 * fell back to caching the raw original) — and hands them to
 * {@see ProductImageDownloader} in batches, which downloads (concurrently)
 * and/or (re)compresses as needed.
 *
 * Deliberately decoupled from {@see ProductCsvImporter}: the CSV
 * parse/upsert stays a single fast DB transaction with no network calls in
 * it, and this runs as a separate pass afterwards (called by both the CSV
 * import command and the admin upload controller). It's also safe to run
 * standalone via `php artisan products:cache-images` — e.g. once, right
 * after deploying this feature, to backfill every product that was
 * already imported before local caching existed.
 *
 * Batched rather than one-product-at-a-time: a catalog of 1000+ products
 * each needing an image used to mean 1000+ sequential HTTP round trips,
 * which made a full CSV import (run synchronously inside the admin
 * upload request) take many minutes. Grouping pending products into
 * chunks and handing each chunk to
 * {@see ProductImageDownloader::downloadMany()} (which fetches a chunk's
 * URLs concurrently) cuts that down to roughly
 * total_products / ProductImageDownloader::CONCURRENCY round trips
 * instead, without changing what gets downloaded, compressed, or how
 * failures are reported.
 */
final class ProductImageCacheRunner
{
    // How many pending products are grouped into one downloadMany() call.
    // Larger than the downloader's own network concurrency (it further
    // splits each chunk into smaller concurrent batches internally) —
    // this just controls how many DB rows are buffered/updated per
    // round, and how often gc_collect_cycles() below gets a chance to
    // run between chunks.
    private const CHUNK_SIZE = 40;

    public function __construct(
        private readonly ProductImageDownloader $downloader,
    ) {}

    public function countPending(): int
    {
        return $this->pendingQuery()->count();
    }

    /**
     * @param  (callable(Product): void)|null  $onEach  invoked once per
     *                                                  processed product, e.g. to drive a console progress bar.
     */
    public function run(?callable $onEach = null): ProductImageCacheResult
    {
        $total = 0;
        $downloaded = 0;
        $unchanged = 0;
        $failures = [];

        $this->pendingQuery()
            ->select(['id', 'name', 'image_url'])
            ->cursor()
            ->chunk(self::CHUNK_SIZE)
            ->each(function ($chunk) use (&$total, &$downloaded, &$unchanged, &$failures, $onEach) {
                $products = $chunk->values();

                if ($products->isEmpty()) {
                    return;
                }

                $cachedByUrl = $this->downloader->downloadMany($products->pluck('image_url')->all());
                $failureReasons = $this->downloader->lastFailureReasons();

                foreach ($products as $product) {
                    $total++;

                    $cached = $cachedByUrl[$product->image_url] ?? $product->image_url;

                    if ($cached !== $product->image_url) {
                        $product->forceFill(['image_url' => $cached])->save();
                        $downloaded++;
                    } else {
                        $unchanged++;
                        $failures[] = [
                            'name' => $product->name,
                            'url' => $product->image_url,
                            'reason' => $failureReasons[$product->image_url] ?? 'Không rõ lý do',
                        ];
                    }

                    if ($onEach !== null) {
                        $onEach($product);
                    }
                }

                // Guzzle's handler stack leaves circular references behind
                // that plain refcounting can't free immediately; across a
                // catalog of 1000+ products in one long-running process,
                // those otherwise pile up faster than the collector's
                // default trigger and can exhaust the memory limit well
                // before the run finishes. Once per chunk (rather than
                // once per product, now that products within a chunk are
                // downloaded concurrently instead of one at a time) is
                // still frequent enough, and gc_collect_cycles() is cheap
                // when there's little to collect.
                gc_collect_cycles();
            });

        return new ProductImageCacheResult($total, $downloaded, $unchanged, $failures);
    }

    private function pendingQuery(): Builder
    {
        return Product::query()
            ->where('image_url', 'like', 'http://%')
            ->orWhere('image_url', 'like', 'https://%')
            ->orWhere(function (Builder $query) {
                $query->where('image_url', 'like', '/storage/products/%')
                    ->where('image_url', 'not like', '%.jpg')
                    ->where('image_url', 'not like', '%.jpeg');
            });
    }
}
