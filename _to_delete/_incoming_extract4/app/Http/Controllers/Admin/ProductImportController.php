<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use App\Services\Import\ProductCsvImporter;
use App\Services\Import\ProductImageCacheRunner;
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
        ]);
    }

    public function store(Request $request, ProductCsvImporter $importer, ProductImageCacheRunner $imageCacheRunner): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $path = $validated['file']->getRealPath();

        $importRun = ImportRun::query()->create([
            'source' => 'csv_import',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $summary = $importer->import($path);

            // Downloading (and resizing) product images can take a while
            // and needs more headroom than the default limits — this
            // request is allowed to run past the default execution time
            // and memory limits rather than risk being killed mid-import
            // with the DB already updated but images left un-cached.
            set_time_limit(0);

            if (ini_get('memory_limit') !== '-1') {
                ini_set('memory_limit', '512M');
            }

            $imageCacheRunner->run();

            $importRun->update([
                'status' => $summary->isSuccess() ? 'success' : 'success_with_errors',
                'finished_at' => now(),
                'processed_count' => $summary->processedCount(),
                'success_count' => $summary->processedCount(),
                'failed_count' => $summary->skipped,
                'message' => $summary->errors !== [] ? implode("\n", $summary->errors) : null,
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
