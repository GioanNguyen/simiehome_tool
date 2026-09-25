<?php

namespace App\Services\Import;

use App\Models\Product;
use App\Models\QuoteItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Deletes cached product image files that nothing references any more.
 *
 * A file under storage/app/public/products can become orphaned when a
 * product's source photo changes between two CSV imports: the product row
 * gets updated to point at a newly-downloaded file, but the previous
 * file — cached under a hash of the *old* URL — is left behind on disk
 * forever. Re-importing the same catalog repeatedly (which this app
 * expects — see README) would otherwise grow storage without bound.
 *
 * "Referenced" means the exact local path is still the current
 * `products.image_url` for some product, OR still the *snapshotted*
 * `quote_items.image_url` for some saved quote — a quote's PDF/detail
 * screen must keep showing the photo as it was when the quote was made,
 * even after the catalog photo has since changed (this is the same
 * snapshot guarantee already documented for product_name/sku/category).
 * Only a file matching neither is deleted.
 */
final class ProductImageGarbageCollector
{
    private const DISK = 'public';

    private const DIRECTORY = 'products';

    public function sweep(): ProductImageGarbageCollectionResult
    {
        $disk = Storage::disk(self::DISK);
        $referenced = $this->referencedPaths();

        $deletedCount = 0;
        $freedBytes = 0;
        $keptCount = 0;

        foreach ($disk->files(self::DIRECTORY) as $file) {
            if (in_array($file, $referenced, true)) {
                $keptCount++;

                continue;
            }

            $freedBytes += $disk->size($file);
            $disk->delete($file);
            $deletedCount++;
        }

        return new ProductImageGarbageCollectionResult($deletedCount, $freedBytes, $keptCount);
    }

    /**
     * @return array<int, string> relative paths (disk-relative, e.g.
     *                            "products/abcd1234.jpg") still in use.
     */
    private function referencedPaths(): array
    {
        return Product::query()
            ->where('image_url', 'like', '/storage/'.self::DIRECTORY.'/%')
            ->pluck('image_url')
            ->merge(
                QuoteItem::query()
                    ->where('image_url', 'like', '/storage/'.self::DIRECTORY.'/%')
                    ->pluck('image_url')
            )
            ->map(fn (string $url) => Str::after($url, '/storage/'))
            ->unique()
            ->values()
            ->all();
    }
}
