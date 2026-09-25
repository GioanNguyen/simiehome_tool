<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Danh sách báo giá
            </h2>
            <a href="{{ route('quotes.create', ['new' => 1]) }}" class="inline-flex rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500">
                + Tạo báo giá mới
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow">
                <form method="GET" action="{{ route('quotes.index') }}" class="grid gap-4 md:grid-cols-5">
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Ngày báo giá</label>
                        <input type="date" name="date" value="{{ $filters['date'] }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>

                    @if ($canViewAll)
                        <div>
                            <label class="block text-xs font-medium text-gray-500">Người báo giá</label>
                            <select name="user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">Tất cả</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected((string) $filters['user_id'] === (string) $user->id)>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-500">Tên hoặc SĐT khách hàng</label>
                        <input type="text" name="customer" value="{{ $filters['customer'] }}" placeholder="VD: Nguyễn Văn A hoặc 090..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500">Số dòng / trang</label>
                        <select name="per_page" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach ([10, 20, 50] as $option)
                                <option value="{{ $option }}" @selected((int) $filters['per_page'] === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-5 flex items-center gap-3">
                        <button type="submit" class="inline-flex rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700">
                            Tìm kiếm
                        </button>
                        <a href="{{ route('quotes.index') }}" class="text-sm text-gray-500 hover:underline">Xoá bộ lọc</a>
                    </div>
                </form>
            </div>

            <div class="rounded-lg bg-white p-6 shadow">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-2 pr-4">Mã báo giá</th>
                                <th class="py-2 pr-4">Ngày</th>
                                <th class="py-2 pr-4">Người báo giá</th>
                                <th class="py-2 pr-4">Khách hàng</th>
                                <th class="py-2 pr-4">SĐT</th>
                                <th class="py-2 pr-4">Tổng giá</th>
                                <th class="py-2 pr-4"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($quotes as $quote)
                                <tr>
                                    <td class="py-2 pr-4 font-medium text-gray-900">{{ $quote->quote_code }}</td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $quote->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $quote->user?->name ?? '—' }}</td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $quote->customer_name ?? '—' }}</td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $quote->customer_phone ?? '—' }}</td>
                                    <td class="whitespace-nowrap py-2 pr-4 font-medium">{{ number_format((float) $quote->grand_total, 0, ',', '.') }} đ</td>
                                    <td class="py-2 pr-4 text-right whitespace-nowrap">
                                        <a href="{{ route('quotes.show', $quote) }}" class="font-medium text-brand-600 hover:underline">Xem</a>
                                        @can('delete-quotes')
                                            <form method="POST" action="{{ route('quotes.destroy', $quote) }}" class="inline" onsubmit="return confirm('Xoá báo giá {{ $quote->quote_code }}? Không thể hoàn tác.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="ml-3 font-medium text-red-600 hover:underline">Xoá</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-4 text-gray-500">Không tìm thấy báo giá nào phù hợp.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $quotes->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
