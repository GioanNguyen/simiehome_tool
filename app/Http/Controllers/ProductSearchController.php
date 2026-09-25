<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->string('q'));

        $products = Product::query()
            ->quotable()
            ->where('status', 'active')
            ->when($query !== '', function ($builder) use ($query) {
                $builder
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'sku', 'name', 'category', 'regular_price', 'sale_price', 'base_price', 'image_url', 'attributes_json']);

        $data = $products->map(fn (Product $product) => [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'category' => $product->category,
            'price' => $product->effectivePrice(),
            'image_url' => $product->image_url,
            'attributes' => $product->attributes_json ?? [],
        ]);

        return response()->json([
            'data' => $data,
        ]);
    }
}
