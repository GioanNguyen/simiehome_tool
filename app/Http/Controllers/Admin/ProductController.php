<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Searchable, paginated list of every product currently in the local
     * catalog — lets an admin fix a handful of prices by hand (e.g. after
     * noticing one is wrong) without re-running a full CSV import just for
     * that.
     */
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50'],
        ]);

        $search = $validated['q'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 20);

        $products = Product::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'filters' => [
                'q' => $search ?? '',
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Deliberately narrow: only the price fields are editable here. Every
     * other column (name, SKU, category, image, ...) comes from the CSV
     * catalog and is expected to be corrected there, at the source, so it
     * doesn't drift out of sync and get silently overwritten again on the
     * next import.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'regular_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'extra_fee' => ['nullable', 'numeric', 'min:0'],
            'minimum_length' => ['nullable', 'numeric', 'min:0'],
        ]);

        $product->update([
            'regular_price' => $validated['regular_price'] ?? null,
            'sale_price' => $validated['sale_price'] ?? null,
            'base_price' => $validated['base_price'] ?? null,
            'extra_fee' => $validated['extra_fee'] ?? null,
            'minimum_length' => $validated['minimum_length'] ?? null,
        ]);

        return back()->with('status', "Đã cập nhật giá cho \"{$product->name}\".");
    }
}
