<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Danh sách sản phẩm
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow">
                <p class="text-sm text-gray-600">
                    Chỉ sửa được <strong>giá</strong> ở đây. Các thông tin khác (tên, SKU, nhóm sản phẩm, hình ảnh...)
                    lấy từ file CSV — sửa ở nguồn CSV rồi import lại để tránh bị ghi đè mất ở lần import kế tiếp.
                </p>

                <form method="GET" action="{{ route('admin.products.index') }}" class="mt-6 grid gap-4 md:grid-cols-5">
                    <div class="md:col-span-3">
                        <label class="block text-xs font-medium text-gray-500">Tìm theo tên hoặc SKU</label>
                        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="VD: Bàn Basic hoặc GUONG-A" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500">Số dòng / trang</label>
                        <select name="per_page" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach ([10, 20, 50] as $option)
                                <option value="{{ $option }}" @selected((int) $filters['per_page'] === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end gap-3">
                        <button type="submit" class="inline-flex rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700">
                            Tìm kiếm
                        </button>
                        <a href="{{ route('admin.products.index') }}" class="text-sm text-gray-500 hover:underline">Xoá bộ lọc</a>
                    </div>
                </form>
            </div>

            <div class="rounded-lg bg-white p-6 shadow">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-2 pr-4"></th>
                                <th class="py-2 pr-4">Tên</th>
                                <th class="py-2 pr-4">SKU</th>
                                <th class="py-2 pr-4">Nhóm</th>
                                <th class="py-2 pr-4">Loại</th>
                                <th class="py-2 pr-4">Trạng thái</th>
                                <th class="py-2 pr-4">Giá niêm yết</th>
                                <th class="py-2 pr-4">Giá khuyến mãi</th>
                                <th class="py-2 pr-4">Giá gốc (base_price)</th>
                                <th class="py-2 pr-4">Phụ phí (extra_fee)</th>
                                <th class="py-2 pr-4">Chiều dài tối thiểu (cm)</th>
                                <th class="py-2 pr-4"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($products as $product)
                                @php $formId = "product-edit-{$product->id}"; @endphp
                                <tr>
                                    <td class="py-2 pr-4">
                                        @if ($product->image_url)
                                            <img src="{{ $product->image_url }}" alt="" class="h-10 w-10 shrink-0 rounded object-cover border border-gray-100" onerror="this.style.display='none'">
                                        @endif
                                    </td>
                                    <td class="py-2 pr-4 font-medium text-gray-900">{{ $product->name }}</td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $product->sku ?? '—' }}</td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $product->category ?? '—' }}</td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $product->type }}</td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $product->status }}</td>
                                    <td class="py-2 pr-4">
                                        <input form="{{ $formId }}" type="number" step="0.01" min="0" name="regular_price" value="{{ old('regular_price', $product->regular_price) }}" class="block w-36 rounded-md border-gray-300 shadow-sm text-sm">
                                    </td>
                                    <td class="py-2 pr-4">
                                        <input form="{{ $formId }}" type="number" step="0.01" min="0" name="sale_price" value="{{ old('sale_price', $product->sale_price) }}" class="block w-36 rounded-md border-gray-300 shadow-sm text-sm">
                                    </td>
                                    <td class="py-2 pr-4">
                                        <input form="{{ $formId }}" type="number" step="0.01" min="0" name="base_price" value="{{ old('base_price', $product->base_price) }}" class="block w-36 rounded-md border-gray-300 shadow-sm text-sm">
                                    </td>
                                    <td class="py-2 pr-4">
                                        <input form="{{ $formId }}" type="number" step="0.01" min="0" name="extra_fee" value="{{ old('extra_fee', $product->extra_fee) }}" class="block w-36 rounded-md border-gray-300 shadow-sm text-sm">
                                    </td>
                                    <td class="py-2 pr-4">
                                        <input form="{{ $formId }}" type="number" step="0.01" min="0" name="minimum_length" value="{{ old('minimum_length', $product->minimum_length) }}" class="block w-36 rounded-md border-gray-300 shadow-sm text-sm">
                                    </td>
                                    <td class="py-2 pr-4 text-right">
                                        {{-- The <form> itself lives here rather than wrapping the <tr> —
                                             a <form> can't be a direct child of <tbody>/<tr> without
                                             browsers hoisting it out and breaking the table layout.
                                             Every input above points at this form by id instead. --}}
                                        <form id="{{ $formId }}" method="POST" action="{{ route('admin.products.update', $product) }}">
                                            @csrf
                                            @method('PATCH')
                                        </form>
                                        <button form="{{ $formId }}" type="submit" class="inline-flex rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500">
                                            Lưu
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="py-4 text-gray-500">Không tìm thấy sản phẩm nào phù hợp.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $products->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
