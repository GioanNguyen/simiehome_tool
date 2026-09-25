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
            $this->warn("Không tải được, vẫn giữ URL cũ để thử lại lần sau: {$result->unchanged}");
            $this->printFailureReasons($result->failures);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{name: string, url: string, reason: string}>  $failures
     */
    private function printFailureReasons(array $failures): void
    {
        $this->newLine();
        $this->line('Chi tiết lý do (gộp theo loại lỗi):');

        $grouped = [];

        foreach ($failures as $failure) {
            $grouped[$failure['reason']][] = $failure;
        }

        foreach ($grouped as $reason => $items) {
            $this->line('  • '.$reason.' — '.count($items).' sản phẩm');

            foreach (array_slice($items, 0, 3) as $item) {
                $this->line("      - {$item['name']}: {$item['url']}");
            }

            if (count($items) > 3) {
                $remaining = count($items) - 3;
                $this->line("      ... và {$remaining} sản phẩm khác cùng lý do");
            }
        }
    }
}
