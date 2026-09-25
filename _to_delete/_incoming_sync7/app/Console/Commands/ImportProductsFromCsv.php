<?php

namespace App\Console\Commands;

use App\Models\ImportRun;
use App\Services\Import\ProductCsvImporter;
use App\Services\Import\ProductImageCacheRunner;
use App\Services\Import\ProductImageGarbageCollector;
use Illuminate\Console\Command;
use Throwable;

class ImportProductsFromCsv extends Command
{
    protected $signature = 'products:import {path : Đường dẫn tới file CSV export sản phẩm}';

    protected $description = 'Import sản phẩm từ file CSV export (không gọi WooCommerce API)';

    public function handle(ProductCsvImporter $importer, ProductImageCacheRunner $imageCacheRunner, ProductImageGarbageCollector $imageGarbageCollector): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("Không tìm thấy file: {$path}");

            return self::FAILURE;
        }

        $importRun = ImportRun::query()->create([
            'source' => 'csv_import',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $summary = $importer->import($path);

            $this->info('Đang tải ảnh sản phẩm về lưu local...');
            $imageResult = $imageCacheRunner->run();
            $this->info("Ảnh đã tải: {$imageResult->downloaded}, đã có sẵn/không tải được: {$imageResult->unchanged}");

            $gcResult = $imageGarbageCollector->sweep();

            if ($gcResult->deletedCount > 0) {
                $freedMb = round($gcResult->freedBytes / 1024 / 1024, 1);
                $this->info("Đã xoá {$gcResult->deletedCount} ảnh cũ không còn dùng (giải phóng {$freedMb}MB), giữ lại {$gcResult->keptCount} ảnh đang dùng.");
            }

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

            $this->error("Import thất bại: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Tổng số dòng: {$summary->totalRows}");
        $this->info("Tạo mới: {$summary->created}");
        $this->info("Cập nhật: {$summary->updated}");
        $this->info("Bỏ qua: {$summary->skipped}");

        if ($summary->errors !== []) {
            $this->warn('Các dòng lỗi:');

            foreach ($summary->errors as $error) {
                $this->line("  - {$error}");
            }
        }

        return self::SUCCESS;
    }
}
