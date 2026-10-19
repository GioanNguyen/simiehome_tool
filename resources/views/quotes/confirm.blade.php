<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Xác nhận báo giá
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Tóm tắt báo giá</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Kiểm tra lại thông tin bên dưới trước khi lưu. Nếu cần sửa sản phẩm/số lượng, bấm "Quay lại".
                </p>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-2 pr-4">Loại</th>
                                <th class="py-2 pr-4">Sản phẩm</th>
                                <th class="py-2 pr-4">SKU</th>
                                <th class="py-2 pr-4">SL</th>
                                <th class="py-2 pr-4">Đơn giá</th>
                                <th class="py-2 pr-4">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @if ($result->main)
                                <tr>
                                    <td class="py-2 pr-4 text-gray-500">Sản phẩm chính</td>
                                    <td class="py-2 pr-4 font-medium text-gray-900">
                                        <div class="flex items-center gap-3">
                                            @if ($result->main->imageUrl)
                                                <img src="{{ $result->main->imageUrl }}" alt="" class="h-10 w-10 shrink-0 rounded object-cover border border-gray-100" onerror="this.style.display='none'">
                                            @endif
                                            <span>
                                                {{ $result->main->productName }}
                                                @if ($result->main->lengthCm)
                                                    <span class="whitespace-nowrap text-xs text-gray-500">({{ $result->main->lengthCm }} cm)</span>
                                                @endif
                                                @if ($result->main->formulaType === 'manual_override')
                                                    <span class="ml-1 rounded bg-brand-50 px-1.5 py-0.5 text-xs text-brand-600">Giá sửa tay</span>
                                                @endif
                                            </span>
                                        </div>
                                        @if ($result->main->unitSurcharge > 0)
                                            <p class="mt-1 text-xs text-gray-500">
                                                Đơn giá đã gồm phụ phí {{ number_format($result->main->unitSurcharge, 0, ',', '.') }} đ/sản phẩm
                                                @if ($result->main->quantity > 1)
                                                    × {{ $result->main->quantity }} = {{ number_format($result->main->unitSurcharge * $result->main->quantity, 0, ',', '.') }} đ
                                                @endif
                                            </p>
                                        @endif
                                        @if ($result->main->surcharge > 0)
                                            <p class="mt-1 text-xs text-gray-500">+ Phụ phí {{ number_format($result->main->surcharge, 0, ',', '.') }} đ (đã gồm trong thành tiền)</p>
                                        @endif
                                        @if ($result->main->warning)
                                            <p class="mt-1 text-xs font-medium text-amber-600">{{ $result->main->warning }}</p>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $result->main->sku ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $result->main->quantity }}</td>
                                    <td class="whitespace-nowrap py-2 pr-4">{{ number_format($result->main->unitPrice, 0, ',', '.') }} đ</td>
                                    <td class="whitespace-nowrap py-2 pr-4 font-medium">{{ number_format($result->main->lineTotal, 0, ',', '.') }} đ</td>
                                </tr>
                            @endif

                            @foreach ($result->addons as $addon)
                                <tr>
                                    <td class="py-2 pr-4 text-gray-500">Sản phẩm phụ</td>
                                    <td class="py-2 pr-4 font-medium text-gray-900">
                                        <div class="flex items-center gap-3">
                                            @if ($addon->imageUrl)
                                                <img src="{{ $addon->imageUrl }}" alt="" class="h-10 w-10 shrink-0 rounded object-cover border border-gray-100" onerror="this.style.display='none'">
                                            @endif
                                            <span>
                                                {{ $addon->productName }}
                                                @if ($addon->lengthCm)
                                                    <span class="whitespace-nowrap text-xs text-gray-500">({{ $addon->lengthCm }} cm)</span>
                                                @endif
                                            </span>
                                        </div>
                                        @if ($addon->unitSurcharge > 0)
                                            <p class="mt-1 text-xs text-gray-500">
                                                Đơn giá đã gồm phụ phí {{ number_format($addon->unitSurcharge, 0, ',', '.') }} đ/sản phẩm
                                                @if ($addon->quantity > 1)
                                                    × {{ $addon->quantity }} = {{ number_format($addon->unitSurcharge * $addon->quantity, 0, ',', '.') }} đ
                                                @endif
                                            </p>
                                        @endif
                                        @if ($addon->surcharge > 0)
                                            <p class="mt-1 text-xs text-gray-500">+ Phụ phí {{ number_format($addon->surcharge, 0, ',', '.') }} đ (đã gồm trong thành tiền)</p>
                                        @endif
                                        @if ($addon->warning)
                                            <p class="mt-1 text-xs font-medium text-amber-600">{{ $addon->warning }}</p>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $addon->sku ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $addon->quantity }}</td>
                                    <td class="whitespace-nowrap py-2 pr-4">{{ number_format($addon->unitPrice, 0, ',', '.') }} đ</td>
                                    <td class="whitespace-nowrap py-2 pr-4 font-medium">{{ number_format($addon->lineTotal, 0, ',', '.') }} đ</td>
                                </tr>
                            @endforeach

                            @foreach ($result->fees as $fee)
                                <tr>
                                    <td class="py-2 pr-4 text-gray-500">Phụ phí</td>
                                    <td class="py-2 pr-4 font-medium text-gray-900">{{ $fee['label'] }}</td>
                                    <td class="py-2 pr-4 text-gray-500">—</td>
                                    <td class="py-2 pr-4">1</td>
                                    <td class="whitespace-nowrap py-2 pr-4">{{ number_format($fee['amount'], 0, ',', '.') }} đ</td>
                                    <td class="whitespace-nowrap py-2 pr-4 font-medium">{{ number_format($fee['amount'], 0, ',', '.') }} đ</td>
                                </tr>
                            @endforeach

                            @if (! $result->main && empty($result->addons) && empty($result->fees))
                                <tr>
                                    <td colspan="6" class="py-4 text-gray-500">Chưa có sản phẩm/phụ phí nào.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <dl class="mt-6 grid gap-4 border-t border-gray-100 pt-4 {{ $result->discountTotal > 0 ? 'md:grid-cols-4' : 'md:grid-cols-3' }}">
                    <div>
                        <dt class="text-sm text-gray-500">Tạm tính</dt>
                        <dd class="text-base font-medium text-gray-900">{{ number_format($result->subtotal, 0, ',', '.') }} đ</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Phụ phí</dt>
                        <dd class="text-base font-medium text-gray-900">{{ number_format($result->feeTotal, 0, ',', '.') }} đ</dd>
                    </div>
                    @if ($result->discountTotal > 0)
                        <div>
                            <dt class="text-sm text-gray-500">
                                Giảm giá
                                @if ($result->discountType === 'percent')
                                    ({{ rtrim(rtrim(number_format($result->discountValue, 2, ',', '.'), '0'), ',') }}%)
                                @endif
                            </dt>
                            <dd class="text-base font-medium text-red-600">- {{ number_format($result->discountTotal, 0, ',', '.') }} đ</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm text-gray-500">Tổng giá bộ</dt>
                        <dd class="text-lg font-semibold text-brand-600">{{ number_format($result->grandTotal, 0, ',', '.') }} đ</dd>
                    </div>
                </dl>

                @if ($notes)
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <dt class="text-sm text-gray-500">Ghi chú</dt>
                        <dd class="text-sm text-gray-900">{{ $notes }}</dd>
                    </div>
                @endif
            </div>

            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Thông tin khách hàng</h3>
                <p class="mt-1 text-sm text-gray-600">Thông tin này sẽ hiển thị trên báo giá và file PDF.</p>

                <form method="POST" action="{{ route('quotes.store') }}" class="mt-4 space-y-6">
                    @csrf

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tên khách hàng <span class="text-red-600">*</span></label>
                            <input type="text" name="customer_name" value="{{ old('customer_name') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Số điện thoại <span class="text-red-600">*</span></label>
                            <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Email (không bắt buộc)</label>
                            <input type="email" name="customer_email" value="{{ old('customer_email') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Địa chỉ (không bắt buộc)</label>
                            <input type="text" name="customer_address" value="{{ old('customer_address') }}" maxlength="500" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <a href="{{ route('quotes.create') }}" class="inline-flex rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                            &larr; Quay lại sửa
                        </a>
                        <button type="submit" class="inline-flex rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500">
                            Xác nhận &amp; Lưu báo giá
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
