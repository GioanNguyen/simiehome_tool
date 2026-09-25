<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Import sản phẩm từ CSV
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow" x-data="{ showMapping: false }">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <p class="text-sm text-gray-600">
                        Tải lên file CSV export sản phẩm (định dạng export chuẩn của WooCommerce). Sản phẩm được ghi/khớp theo
                        cột <strong>ID</strong> trong file — SKU chỉ dùng để hiển thị, không dùng để mapping vì nhiều dòng
                        không có SKU.
                    </p>

                    <button type="button" @click="showMapping = !showMapping"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <span x-text="showMapping ? 'Ẩn bảng mapping cột' : 'Xem cột CSV ứng với trường nào'"></span>
                    </button>
                </div>

                <div x-show="showMapping" x-cloak x-transition class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-4">
                    <p class="text-xs text-gray-500">
                        Đây là các cột trong file CSV mà hệ thống đọc và lưu vào cơ sở dữ liệu khi import. Cột nào không có
                        trong danh sách này sẽ bị bỏ qua; cột nào có nhưng bị bỏ trống trên một dòng sẽ được lưu là rỗng/null.
                    </p>

                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-2 pr-4">Cột trong file CSV</th>
                                    <th class="py-2 pr-4">Trường trong database</th>
                                    <th class="py-2 pr-4">Ghi chú</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ([
                                    ['ID', 'external_id', 'Bắt buộc. Khoá để khớp/cập nhật sản phẩm giữa các lần import.'],
                                    ['Type', 'type', 'Bắt buộc. simple | variable | variation.'],
                                    ['Name', 'name', 'Bắt buộc.'],
                                    ['SKU', 'sku', 'Chỉ để hiển thị, không dùng để khớp sản phẩm.'],
                                    ['Categories', 'category', 'Chỉ lấy nhóm cấp 1 đầu tiên; biến thể tự kế thừa từ sản phẩm cha.'],
                                    ['Regular price', 'regular_price', 'Giá niêm yết.'],
                                    ['Sale price', 'sale_price', 'Giá khuyến mãi.'],
                                    ['Base price', 'base_price', 'Không phải cột chuẩn của WooCommerce — dùng cho công thức tính giá theo kích thước tuỳ chỉnh.'],
                                    ['Extra fee', 'extra_fee', 'Không phải cột chuẩn của WooCommerce — phụ phí cố định cho sản phẩm.'],
                                    ['Minimum length', 'minimum_length', 'Không phải cột chuẩn của WooCommerce — chiều dài tối thiểu (cm) cho công thức tính giá tuỳ chỉnh.'],
                                    ['Images', 'image_url', 'Chỉ lấy ảnh đầu tiên trong danh sách.'],
                                    ['Parent', 'parent_external_id', 'Xác định sản phẩm cha của một biến thể (theo ID hoặc SKU).'],
                                    ['Published', 'status', '"-1" → draft, còn lại → active.'],
                                    ['Visibility in catalog', '— (không lưu trực tiếp)', 'Chỉ dòng có giá trị "visible" (hoặc kế thừa từ cha) mới được import.'],
                                    ['Attribute 1/2/3 name + value(s)', 'attributes_json', 'Gộp thành object thuộc tính, vd {"Size": "140x60x75"}.'],
                                ] as [$csvColumn, $dbField, $note])
                                    <tr>
                                        <td class="py-2 pr-4 font-medium text-gray-900">{{ $csvColumn }}</td>
                                        <td class="py-2 pr-4"><code class="rounded bg-gray-100 px-1.5 py-0.5">{{ $dbField }}</code></td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $note }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

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

                    <div class="rounded-md border border-gray-200 p-4">
                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            {{-- Hidden "0" before the checkbox is the standard Laravel trick so an
                                 unchecked box still submits a value (checkboxes send nothing when
                                 unchecked) — this makes old('update_images') and $request->boolean()
                                 behave correctly in both states, not just when checked. --}}
                            <input type="hidden" name="update_images" value="0">
                            <input type="checkbox" name="update_images" value="1" class="mt-0.5" @checked(old('update_images', '0') === '1')>
                            <span>
                                <span class="font-medium text-gray-900">Tải & cập nhật ảnh sản phẩm</span>
                                <span class="block text-xs text-gray-500">
                                    Bỏ chọn nếu chỉ cần cập nhật nhanh dữ liệu (giá, tên, nhóm, thuộc tính, ...) và ảnh
                                    không thay đổi — import sẽ nhanh hơn nhiều vì bỏ qua bước tải ảnh từ nguồn export.
                                </span>
                            </span>
                        </label>
                        @error('update_images')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
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
