<?php

namespace App\Services\Import;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sweeps every product whose `image_url` still points at an external
 * source (http/https) and caches it locally via {@see ProductImageDownloader}.
 *
 * Deliberately decoupled from {@see ProductCsvImporter}: the CSV
 * parse/upsert stays a single fast DB transaction with no network calls in
 * it, and this runs as a separate pass afterwards (called by both the CSV
 * import command and the admin upload controller). It's also safe to run
 * standalone via `php artisan products:cache-images` — e.g. once, right
 * after deploying this feature, to backfill every product that was
 * already imported before local caching existed.
 */
final class ProductImageCacheRunner
{
    // How often to force PHP's cycle collector while downloading. Guzzle's
    // handler stack leaves circular references behind that plain
    // refcounting can't free immediately; across a catalog of 1000+
    // products in one long-running process, those otherwise pile up
    // faster than the collector's default trigger and can exhaust the
    // memory limit well before the run finishes.
    private const GC_EVERY = 15;

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

        $this->pendingQuery()
            ->select(['id', 'image_url'])
            ->cursor()
            ->each(function (Product $product) use (&$total, &$downloaded, &$unchanged, $onEach) {
                $total++;

                $cached = $this->downloader->download($product->image_url);

                if ($cached !== $product->image_url) {
                    $product->forceFill(['image_url' => $cached])->save();
                    $downloaded++;
                } else {
                    $unchanged++;
                }

                if ($total % self::GC_EVERY === 0) {
                    gc_collect_cycles();
                }

                if ($onEach !== null) {
                    $onEach($product);
                }
            });

        return new ProductImageCacheResult($total, $downloaded, $unchanged);
    }

    private function pendingQuery(): Builder
    {
        return Product::query()
            ->where('image_url', 'like', 'http://%')
            ->orWhere('image_url', 'like', 'https://%');
    }
}
