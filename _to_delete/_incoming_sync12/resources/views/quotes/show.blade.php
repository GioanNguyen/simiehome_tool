<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Báo giá {{ $quote->quote_code }}
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('quotes.index') }}" class="text-sm text-gray-500 hover:underline">&larr; Danh sách báo giá</a>
                <a href="{{ route('quotes.pdf', $quote) }}" target="_blank" class="inline-flex rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700">
                    Xuất PDF
                </a>
                @can('delete-quotes')
                    <form method="POST" action="{{ route('quotes.destroy', $quote) }}" onsubmit="return confirm('Xoá báo giá {{ $quote->quote_code }}? Không thể hoàn tác.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500">
                            Xoá báo giá
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow">
                <dl class="mb-6 grid gap-4 border-b border-gray-100 pb-4 md:grid-cols-4">
                    <div>
                        <dt class="text-sm text-gray-500">Khách hàng</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ $quote->customer_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Số điện thoại</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ $quote->customer_phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Email</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ $quote->customer_email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Người báo giá</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ $quote->user?->name ?? '—' }} · {{ $quote->created_at->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>

                <div class="overflow-x-auto">
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
                            @forelse ($quote->items as $item)
                                <tr>
                                    <td class="py-2 pr-4 text-gray-500">
                                        @if ($item->role === 'main')
                                            Sản phẩm chính
                                        @elseif ($item->role === 'addon')
                                            Sản phẩm phụ
                                        @else
                                            Phụ phí
                                        @endif
                                    </td>
                                    <td class="py-2 pr-4 font-medium text-gray-900">
                                        <div class="flex items-center gap-3">
                                            @if ($item->image_url)
                                                <img src="{{ $item->image_url }}" alt="" class="h-10 w-10 shrink-0 rounded object-cover border border-gray-100" onerror="this.style.display='none'">
                                            @endif
                                            <span>
                                                {{ $item->product_name ?? '—' }}
                                                @if ($item->length_cm)
                                                    <span class="text-xs text-gray-500">({{ $item->length_cm }} cm)</span>
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $item->sku ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $item->quantity }}</td>
                                    <td class="py-2 pr-4">{{ number_format((float) $item->unit_price, 0, ',', '.') }} đ</td>
                                    <td class="py-2 pr-4 font-medium">{{ number_format((float) $item->line_total, 0, ',', '.') }} đ</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-4 text-gray-500">Báo giá chưa có sản phẩm nào.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <dl class="mt-6 grid gap-4 border-t border-gray-100 pt-4 md:grid-cols-3">
                    <div>
                        <dt class="text-sm text-gray-500">Tạm tính</dt>
                        <dd class="text-base font-medium text-gray-900">{{ number_format((float) $quote->subtotal, 0, ',', '.') }} đ</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Phụ phí</dt>
                        <dd class="text-base font-medium text-gray-900">{{ number_format((float) $quote->fee_total, 0, ',', '.') }} đ</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Tổng giá bộ</dt>
                        <dd class="text-lg font-semibold text-brand-600">{{ number_format((float) $quote->grand_total, 0, ',', '.') }} đ</dd>
                    </div>
                </dl>

                @if ($quote->notes)
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <dt class="text-sm text-gray-500">Ghi chú</dt>
                        <dd class="text-sm text-gray-900">{{ $quote->notes }}</dd>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
