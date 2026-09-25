<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Dashboard
            </h2>
            <a href="{{ route('quotes.create', ['new' => 1]) }}" class="inline-flex rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500">
                + Tạo báo giá mới
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ([
                    'today' => 'Hôm nay',
                    'month' => 'Tháng này',
                    'year' => 'Năm nay',
                ] as $period => $label)
                    <div class="rounded-lg bg-white p-6 shadow">
                        <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $stats[$period]['count'] }}</p>
                        <p class="text-sm text-gray-500">báo giá</p>
                        <p class="mt-3 text-lg font-medium text-brand-700">
                            {{ number_format($stats[$period]['total'], 0, ',', '.') }} đ
                        </p>
                        <p class="text-xs text-gray-400">tổng giá trị</p>
                    </div>
                @endforeach
            </div>

            @if ($canViewAll)
                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-base font-semibold text-gray-900">Hoạt động nhân viên sale (tháng này)</h3>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-2 pr-4">Nhân viên</th>
                                    <th class="py-2 pr-4">Báo giá hôm nay</th>
                                    <th class="py-2 pr-4">Báo giá tháng này</th>
                                    <th class="py-2 pr-4">Tổng giá trị tháng này</th>
                                    <th class="py-2 pr-4">Báo giá gần nhất</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($salesActivity as $sales)
                                    <tr>
                                        <td class="py-2 pr-4 font-medium text-gray-900">{{ $sales->name }}</td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $sales->quotes_today_count }}</td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $sales->quotes_month_count }}</td>
                                        <td class="py-2 pr-4 text-gray-500">
                                            {{ number_format((float) ($sales->quotes_month_total ?? 0), 0, ',', '.') }} đ
                                        </td>
                                        <td class="py-2 pr-4 text-gray-500">
                                            {{ $sales->last_quote_at ? \Illuminate\Support\Carbon::parse($sales->last_quote_at)->format('d/m/Y H:i') : '—' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-4 text-gray-500">Chưa có nhân viên sale nào trong hệ thống.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
