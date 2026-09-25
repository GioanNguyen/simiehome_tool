<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Công thức tính giá theo nhóm sản phẩm
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow">
                <p class="text-sm text-gray-600">
                    Mặc định mọi sản phẩm dùng <strong>giá theo catalog</strong> (đúng giá đã import từ CSV, không tính công thức).
                    Chỉ thêm rule bên dưới cho nhóm sản phẩm nào cần công thức khác, hoặc override riêng cho một SKU.
                    Chọn <strong>"Tất cả nhóm sản phẩm"</strong> để dùng một công thức chung cho mọi nhóm.
                    Thứ tự ưu tiên: rule riêng 1 sản phẩm &rarr; rule theo nhóm &rarr; rule tất cả nhóm &rarr; giá catalog.
                </p>

                <p class="mt-2 text-sm text-gray-600">
                    Chọn <strong>công thức tuỳ chỉnh</strong> để tự viết công thức tính giá theo kích thước tuỳ chỉnh
                    (vd khi khách chọn kích thước chưa có sẵn trong catalog).
                </p>

                @error('expression')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror

                @if (session('status'))
                    <p class="mt-2 text-sm text-green-700">{{ session('status') }}</p>
                @endif

                @error('category')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <form method="POST" action="{{ route('admin.pricing-rules.store') }}"
                      x-data="{ formulaType: '{{ old('formula_type', 'catalog_price') }}', useProductExtraFee: {{ old('use_product_extra_fee') ? 'true' : 'false' }} }"
                      class="mt-6 space-y-4 border-b border-gray-100 pb-6">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-500">Nhóm sản phẩm (category)</label>
                            <select name="category" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <option value="">— Áp dụng theo SKU (chọn sản phẩm) —</option>
                                <option value="{{ \App\Http\Controllers\Admin\PricingRuleController::ALL_CATEGORIES }}" @selected(old('category') === \App\Http\Controllers\Admin\PricingRuleController::ALL_CATEGORIES)>— Tất cả nhóm sản phẩm —</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}">{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500">Hoặc chỉ 1 sản phẩm (product_id)</label>
                            <input type="number" name="product_id" placeholder="ID sản phẩm (tuỳ chọn)" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500">Công thức</label>
                            <select name="formula_type" x-model="formulaType" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                @foreach ($formulaTypes as $formulaType)
                                    <option value="{{ $formulaType }}">{{ $formulaLabels[$formulaType] ?? $formulaType }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div :class="{ 'opacity-50': formulaType === 'custom_formula' && useProductExtraFee }">
                            <label class="block text-xs font-medium text-gray-500">Phụ phí cố định</label>
                            <input type="number" name="surcharge_amount" min="0" value="0" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <p class="mt-1 text-xs text-gray-400" x-show="formulaType === 'custom_formula' && useProductExtraFee" x-cloak>
                                Đang bỏ qua — phụ phí sẽ lấy theo cấu hình bên dưới thay vì mức cố định này.
                            </p>
                        </div>
                    </div>

                    @foreach ($formulaDescriptions as $type => $description)
                        <div class="rounded-md bg-gray-50 p-3 text-xs text-gray-600" x-show="formulaType === '{{ $type }}'" x-cloak>
                            <span class="font-medium text-gray-700">Công thức này tính giá như sau (chỉ xem, không sửa được):</span>
                            {{ $description }}
                        </div>
                    @endforeach

                    <div x-show="formulaType === 'custom_formula'" x-cloak>
                        @include('admin.pricing-rules._custom-formula-help')

                        <label class="mt-3 block text-xs font-medium text-gray-500">Công thức (dùng tên biến, vd: base_price + length_cm * 5000)</label>
                        <input type="text" name="expression" value="{{ old('expression') }}" placeholder="base_price + length_cm * 5000" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm">

                        <label class="mt-3 flex items-start gap-2 text-xs text-gray-600">
                            <input type="checkbox" name="use_product_extra_fee" value="1" x-model="useProductExtraFee" class="mt-0.5">
                            <span>
                                Bật để cộng thêm <code class="rounded bg-gray-100 px-1 py-0.5">extra_fee</code> của sản phẩm vào giá
                                (thay vì mức "Phụ phí cố định" ở trên), với điều kiện
                                <strong>chiều dài khách chọn ≤ minimum_length</strong> của sản phẩm đó — xem giải thích 2 cột này ở trên.
                            </span>
                        </label>
                    </div>

                    <div>
                        <button type="submit" class="inline-flex rounded-md bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">
                            Thêm rule
                        </button>
                    </div>
                </form>

                <div class="mt-6 space-y-6">
                    @forelse ($pricingRules as $pricingRule)
                        <form method="POST" action="{{ route('admin.pricing-rules.update', $pricingRule) }}"
                              x-data="{ formulaType: '{{ $pricingRule->formula_type }}', useProductExtraFee: {{ ($pricingRule->formula_config['use_product_extra_fee'] ?? false) ? 'true' : 'false' }}, showExtraFeeHelp: false }"
                              class="space-y-4 border-b border-gray-100 pb-6">
                            @csrf
                            @method('PATCH')

                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    {{ $pricingRule->category ?? ($pricingRule->product?->name ?? 'Tất cả nhóm sản phẩm') }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    @if ($pricingRule->product)
                                        Override riêng cho SKU {{ $pricingRule->product->sku ?? $pricingRule->product_id }}
                                    @elseif ($pricingRule->category === null && $pricingRule->product_id === null)
                                        Áp dụng cho mọi nhóm chưa có rule riêng
                                    @else
                                        Áp dụng cho cả nhóm
                                    @endif
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500">Công thức</label>
                                    <select name="formula_type" x-model="formulaType" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                        @foreach ($formulaTypes as $formulaType)
                                            <option value="{{ $formulaType }}" @selected($pricingRule->formula_type === $formulaType)>{{ $formulaLabels[$formulaType] ?? $formulaType }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div :class="{ 'opacity-50': formulaType === 'custom_formula' && useProductExtraFee }">
                                    <label class="block text-xs font-medium text-gray-500">Phụ phí</label>
                                    <input type="number" name="surcharge_amount" min="0" value="{{ (float) $pricingRule->surcharge_amount }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    <p class="mt-1 text-xs text-gray-400" x-show="formulaType === 'custom_formula' && useProductExtraFee" x-cloak>
                                        Đang bỏ qua — phụ phí sẽ lấy theo cấu hình bên dưới thay vì mức cố định này.
                                    </p>
                                </div>

                                <label class="flex items-center gap-2 pt-6 text-sm text-gray-700">
                                    <input type="checkbox" name="active" value="1" @checked($pricingRule->active)>
                                    Active
                                </label>

                                <div class="flex items-end gap-3">
                                    <button type="submit" class="inline-flex rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500">
                                        Lưu
                                    </button>
                                    {{-- Submits the separate delete form below (forms can't be nested). --}}
                                    <button type="submit" form="delete-rule-{{ $pricingRule->id }}" class="inline-flex rounded-md border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 shadow-sm hover:bg-red-50">
                                        Xoá
                                    </button>
                                </div>
                            </div>

                            @foreach ($formulaDescriptions as $type => $description)
                                <div class="rounded-md bg-gray-50 p-3 text-xs text-gray-600" x-show="formulaType === '{{ $type }}'" x-cloak>
                                    <span class="font-medium text-gray-700">Công thức này tính giá như sau (chỉ xem, không sửa được):</span>
                                    {{ $description }}
                                </div>
                            @endforeach

                            <div x-show="formulaType === 'custom_formula'" x-cloak>
                                <label class="block text-xs font-medium text-gray-500">Công thức (dùng tên biến, vd: base_price + length_cm * 5000)</label>
                                <input type="text" name="expression" value="{{ $pricingRule->formula_config['expression'] ?? '' }}" placeholder="base_price + length_cm * 5000" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm">

                                <div class="mt-3 flex items-start gap-2 text-xs text-gray-600">
                                    <label class="flex items-start gap-2">
                                        <input type="checkbox" name="use_product_extra_fee" value="1" x-model="useProductExtraFee" class="mt-0.5">
                                        <span>
                                            Bật để cộng thêm <code class="rounded bg-gray-100 px-1 py-0.5">extra_fee</code> của sản phẩm vào giá
                                            (thay vì mức "Phụ phí" ở trên), với điều kiện
                                            <strong>chiều dài khách chọn ≤ minimum_length</strong> của sản phẩm đó —
                                        </span>
                                    </label>
                                    <button type="button" @click="showExtraFeeHelp = true" class="whitespace-nowrap font-medium text-brand-600 underline hover:text-brand-500">
                                        xem giải thích
                                    </button>
                                </div>

                                <div x-show="showExtraFeeHelp" x-cloak
                                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                                     @click.self="showExtraFeeHelp = false">
                                    <div class="max-w-md rounded-lg bg-white p-5 shadow-xl">
                                        @include('admin.pricing-rules._custom-formula-help')

                                        <div class="mt-4 text-right">
                                            <button type="button" @click="showExtraFeeHelp = false" class="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200">
                                                Đóng
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <form id="delete-rule-{{ $pricingRule->id }}" method="POST" action="{{ route('admin.pricing-rules.destroy', $pricingRule) }}"
                              onsubmit="return confirm({{ Js::from('Xoá công thức „'.($pricingRule->category ?? ($pricingRule->product?->name ?? 'Tất cả nhóm sản phẩm')).'“? Không thể hoàn tác.') }});"
                              class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    @empty
                        <p class="text-sm text-gray-500">Chưa có rule nào — mọi sản phẩm đang dùng giá theo catalog mặc định.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
