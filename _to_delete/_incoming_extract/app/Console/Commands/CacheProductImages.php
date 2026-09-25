<?php

namespace App\Console\Commands;

use App\Services\Import\ProductImageCacheRunner;
use Illuminate\Console\Command;

class CacheProductImages extends Command
{
    protected $signature = 'products:cache-images';

    protected $description = 'Tải về máy và lưu local ảnh sản phẩm đang trỏ thẳng ra nguồn bên ngoài (vd: simiehome.com) — chạy 1 lần để backfill sau khi cập nhật tính năng này, hoặc chạy lại bất cứ lúc nào cũng an toàn (ảnh đã tải rồi sẽ được bỏ qua)';

    public function handle(ProductImageCacheRunner $runner): int
    {
        $total = $runner->countPending();

        if ($total === 0) {
            $this->info('Không có ảnh sản phẩm nào cần tải — tất cả đã ở local hoặc chưa có ảnh.');

            return self::SUCCESS;
        }

        $this->info("Tìm thấy {$total} sản phẩm còn ảnh trỏ ra nguồn bên ngoài, bắt đầu tải...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $result = $runner->run(function () use ($bar) {
            $bar->advance();
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Đã tải và lưu local: {$result->downloaded}");

        if ($result->unchanged > 0) {
            $this->warn("Không tải được (mạng lỗi, ảnh không tồn tại...), vẫn giữ URL cũ để thử lại lần sau: {$result->unchanged}");
        }

        return self::SUCCESS;
    }
}
