<?php

namespace Tests\Unit\Services\Import;

use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Services\Import\ProductImageGarbageCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageGarbageCollectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_a_file_referenced_by_nothing(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/orphan.jpg', 'orphan-bytes');

        $result = (new ProductImageGarbageCollector)->sweep();

        $this->assertSame(1, $result->deletedCount);
        $this->assertSame(0, $result->keptCount);
        $this->assertSame(strlen('orphan-bytes'), $result->freedBytes);
        Storage::disk('public')->assertMissing('products/orphan.jpg');
    }

    public function test_it_keeps_a_file_still_referenced_by_a_products_current_image_url(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/in-use.jpg', 'in-use-bytes');
        Product::factory()->create(['image_url' => '/storage/products/in-use.jpg']);

        $result = (new ProductImageGarbageCollector)->sweep();

        $this->assertSame(0, $result->deletedCount);
        $this->assertSame(1, $result->keptCount);
        Storage::disk('public')->assertExists('products/in-use.jpg');
    }

    public function test_it_keeps_a_file_still_referenced_only_by_a_saved_quotes_snapshot(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/old-photo.jpg', 'old-photo-bytes');

        // The catalog photo has since changed — the product no longer
        // points at this file — but a previously-saved quote's snapshot
        // still does, and that quote must keep rendering the photo it
        // had at the time it was made.
        $product = Product::factory()->create(['image_url' => '/storage/products/new-photo.jpg']);
        $quote = Quote::factory()->create();
        QuoteItem::query()->create([
            'quote_id' => $quote->id,
            'role' => QuoteItem::ROLE_MAIN,
            'product_id' => $product->id,
            'image_url' => '/storage/products/old-photo.jpg',
        ]);

        $result = (new ProductImageGarbageCollector)->sweep();

        $this->assertSame(0, $result->deletedCount);
        Storage::disk('public')->assertExists('products/old-photo.jpg');
    }

    public function test_it_deletes_only_the_unreferenced_files_among_several(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/keep.jpg', 'keep-bytes');
        Storage::disk('public')->put('products/gone1.jpg', 'gone-bytes-1');
        Storage::disk('public')->put('products/gone2.png', 'gone-bytes-2-longer');
        Product::factory()->create(['image_url' => '/storage/products/keep.jpg']);

        $result = (new ProductImageGarbageCollector)->sweep();

        $this->assertSame(2, $result->deletedCount);
        $this->assertSame(1, $result->keptCount);
        $this->assertSame(strlen('gone-bytes-1') + strlen('gone-bytes-2-longer'), $result->freedBytes);
        Storage::disk('public')->assertExists('products/keep.jpg');
        Storage::disk('public')->assertMissing('products/gone1.jpg');
        Storage::disk('public')->assertMissing('products/gone2.png');
    }

    public function test_it_does_nothing_when_the_products_directory_is_empty(): void
    {
        Storage::fake('public');

        $result = (new ProductImageGarbageCollector)->sweep();

        $this->assertSame(0, $result->deletedCount);
        $this->assertSame(0, $result->keptCount);
        $this->assertSame(0, $result->freedBytes);
    }
}
