<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Import sản phẩm từ CSV
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow">
                <p class="text-sm text-gray-600">
                    Tải lên file CSV export sản phẩm (định dạng export chuẩn của WooCommerce). Sản phẩm được ghi/khớp theo
                    cột <strong>ID</strong> trong file — SKU chỉ dùng để hiển thị, không dùng để mapping vì nhiều dòng
                    không có SKU.
                </p>

                <form method="POST" action="{{ route('admin.product-imports.store') }}" enctype="multipart/form-data" class="mt-4 space-y-6">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700">File CSV</label>
                        <input type="file" name="file" accept=".csv,text/csv" required class="mt-1 block w-full max-w-md text-sm">
                        @error('file')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="rounded-md border border-gray-200 p-4">
                        <p class="text-sm font-medium text-gray-700">Điều kiện lọc sản phẩm được thêm vào (tuỳ chọn)</p>
                        <p class="mt-1 text-xs text-gray-500">
                            Không chọn gì thì import tất cả (như hiện tại). Chỉ những dòng khớp <strong>đủ</strong> các điều kiện đã chọn
                            bên dưới mới được thêm/cập nhật vào catalog.
                        </p>

                        <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <span class="block text-xs font-medium text-gray-500">Trạng thái</span>
                                <div class="mt-2 space-y-2">
                                    @foreach (['active' => 'Đang bán (active)', 'draft' => 'Bản nháp (draft)'] as $value => $label)
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" name="statuses[]" value="{{ $value }}" @checked(in_array($value, old('statuses', []), true))>
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('statuses')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <span class="block text-xs font-medium text-gray-500">Loại sản phẩm</span>
                                <div class="mt-2 space-y-2">
                                    @foreach ([
                                        'simple' => 'Sản phẩm đơn giản (simple)',
                                        'variable' => 'Sản phẩm cha có biến thể (variable)',
                                        'variation' => 'Biến thể (variation)',
                                    ] as $value => $label)
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" name="types[]" value="{{ $value }}" @checked(in_array($value, old('types', []), true))>
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('types')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-500">Nhóm sản phẩm (category)</label>
                                <input type="text" name="categories" list="existing-categories" value="{{ old('categories') }}"
                                       placeholder="Vd: Bàn - Tables, Ghế - Chairs (cách nhau bởi dấu phẩy, để trống = tất cả nhóm)"
                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <datalist id="existing-categories">
                                    @foreach ($existingCategories as $existingCategory)
                                        <option value="{{ $existingCategory }}"></option>
                                    @endforeach
                                </datalist>
                                @error('categories')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="inline-flex rounded-md bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">
                            Import
                        </button>
                    </div>
                </form>
            </div>

            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Lịch sử import</h3>

                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-2 pr-4">Thời gian</th>
                                <th class="py-2 pr-4">Trạng thái</th>
                                <th class="py-2 pr-4">Đã xử lý</th>
                                <th class="py-2 pr-4">Bỏ qua</th>
                                <th class="py-2 pr-4">Ghi chú</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($importRuns as $importRun)
                                <tr>
                                    <td class="py-2 pr-4">{{ $importRun->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="py-2 pr-4">{{ $importRun->status }}</td>
                                    <td class="py-2 pr-4">{{ $importRun->processed_count }}</td>
                                    <td class="py-2 pr-4">{{ $importRun->failed_count }}</td>
                                    <td class="py-2 pr-4 max-w-md truncate" title="{{ $importRun->message }}">{{ $importRun->message }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-gray-500">Chưa có lần import nào.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
