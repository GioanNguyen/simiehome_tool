<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use App\Models\Product;
use App\Services\Import\ProductCsvImporter;
use App\Services\Import\ProductImageCacheRunner;
use App\Services\Import\ProductImageGarbageCollector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ProductImportController extends Controller
{
    public function index(): View
    {
        return view('admin.product-imports.index', [
            'importRuns' => ImportRun::query()
                ->where('source', 'csv_import')
                ->latest()
                ->limit(20)
                ->get(),
            // Feeds the category filter's <datalist> so the admin can pick
            // from categories already known to the catalog instead of
            // having to retype them exactly as they appear in the CSV.
            'existingCategories' => Product::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
        ]);
    }

    public function store(Request $request, ProductCsvImporter $importer, ProductImageCacheRunner $imageCacheRunner, ProductImageGarbageCollector $imageGarbageCollector): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
            // Admin-configurable import filters — on top of the always-on
            // "Visibility in catalog = visible" rule the importer already
            // enforces. Leaving all three empty imports everything, exactly
            // like before this feature existed.
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', 'in:active,draft'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'in:simple,variable,variation'],
            'categories' => ['nullable', 'string', 'max:2000'],
            // Tải ảnh sản phẩm là bước tốn thời gian nhất của import (mỗi
            // ảnh mới là một request mạng ra nguồn export) — cho phép admin
            // bỏ qua bước này khi chỉ cần cập nhật nhanh dữ liệu (giá, tên,
            // nhóm, ...) và ảnh không đổi. Mặc định vẫn bật (giữ nguyên
            // hành vi trước khi có tuỳ chọn này) nếu admin không gửi field.
            'update_images' => ['nullable', 'boolean'],
        ]);

        $path = $validated['file']->getRealPath();
        $updateImages = $request->boolean('update_images', true);

        $filters = [
            'statuses' => $validated['statuses'] ?? [],
            'types' => $validated['types'] ?? [],
            'categories' => isset($validated['categories']) && trim($validated['categories']) !== ''
                ? array_values(array_filter(array_map('trim', explode(',', $validated['categories']))))
                : [],
        ];

        $importRun = ImportRun::query()->create([
            'source' => 'csv_import',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $summary = $importer->import($path, $filters);

            $messages = $summary->errors;

            if ($updateImages) {
                // Downloading (and resizing) product images can take a
                // while and needs more headroom than the default limits —
                // this request is allowed to run past the default
                // execution time and memory limits rather than risk being
                // killed mid-import with the DB already updated but
                // images left un-cached.
                set_time_limit(0);

                if (ini_get('memory_limit') !== '-1') {
                    ini_set('memory_limit', '512M');
                }

                $imageCacheRunner->run();

                // Prune any cached image no longer referenced by the
                // catalog (or a saved quote's snapshot) now that this
                // import has updated every product's image_url —
                // otherwise re-importing the same CSV repeatedly, with
                // photos changing between exports, would grow storage
                // without bound. Skipped along with the download step
                // above: with no new images fetched this run, there's
                // nothing new to clean up, and it keeps "chỉ cập nhật dữ
                // liệu" from touching the filesystem at all.
                $gcResult = $imageGarbageCollector->sweep();

                if ($gcResult->deletedCount > 0) {
                    $freedMb = round($gcResult->freedBytes / 1024 / 1024, 1);
                    $messages[] = "Đã xoá {$gcResult->deletedCount} ảnh cũ không còn dùng (giải phóng {$freedMb}MB).";
                }
            } else {
                $messages[] = 'Đã bỏ qua bước tải/cập nhật ảnh sản phẩm theo lựa chọn — chỉ cập nhật dữ liệu.';
            }

            $importRun->update([
                'status' => $summary->isSuccess() ? 'success' : 'success_with_errors',
                'finished_at' => now(),
                'processed_count' => $summary->processedCount(),
                'success_count' => $summary->processedCount(),
                'failed_count' => $summary->skipped,
                'message' => $messages !== [] ? implode("\n", $messages) : null,
            ]);
        } catch (Throwable $exception) {
            $importRun->update([
                'status' => 'failed',
                'finished_at' => now(),
                'message' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('admin.product-imports.index');
    }
}
