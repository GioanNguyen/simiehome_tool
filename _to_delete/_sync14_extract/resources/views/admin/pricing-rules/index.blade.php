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
                    Mặc định mọi sản phẩm dùng <strong>catalog_price</strong> (đúng giá đã import từ CSV, không tính công thức).
                    Chỉ thêm rule bên dưới cho nhóm sản phẩm nào cần công thức khác, hoặc override riêng cho một SKU.
                </p>

                <p class="mt-2 text-sm text-gray-600">
                    Chọn <strong>custom_formula</strong> để tự viết công thức tính giá theo kích thước tuỳ chỉnh
                    (vd khi khách chọn kích thước chưa có sẵn trong catalog). Công thức chỉ được dùng các phép
                    +, -, *, /, dấu ngoặc, số, và các biến sau (lấy từ cột trong database):
                    @foreach ($formulaVariables as $variable)
                        <code class="rounded bg-gray-100 px-1 py-0.5 text-xs">{{ $variable }}</code>@if (! $loop->last), @endif
                    @endforeach
                    . Ví dụ: <code class="rounded bg-gray-100 px-1 py-0.5 text-xs">base_price + length_cm * 5000</code>.
                </p>

                @error('expression')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <form method="POST" action="{{ route('admin.pricing-rules.store') }}"
                      x-data="{ formulaType: '{{ old('formula_type', 'catalog_price') }}' }"
                      class="mt-6 space-y-4 border-b border-gray-100 pb-6">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-500">Nhóm sản phẩm (category)</label>
                            <select name="category" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <option value="">— Áp dụng theo SKU (chọn sản phẩm) —</option>
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

                        <div>
                            <label class="block text-xs font-medium text-gray-500">Phụ phí cố định</label>
                            <input type="number" name="surcharge_amount" min="0" value="0" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
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
                        <input type="text" name="expression" value="{{ old('expression') }}" placeholder="base_price + length_cm * 5000" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm">
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
                              x-data="{ formulaType: '{{ $pricingRule->formula_type }}' }"
                              class="space-y-4 border-b border-gray-100 pb-6">
                            @csrf
                            @method('PATCH')

                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    {{ $pricingRule->category ?? ($pricingRule->product?->name ?? 'Rule mặc định') }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    @if ($pricingRule->product)
                                        Override riêng cho SKU {{ $pricingRule->product->sku ?? $pricingRule->product_id }}
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

                                <div>
                                    <label class="block text-xs font-medium text-gray-500">Phụ phí</label>
                                    <input type="number" name="surcharge_amount" min="0" value="{{ (float) $pricingRule->surcharge_amount }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                </div>

                                <label class="flex items-center gap-2 pt-6 text-sm text-gray-700">
                                    <input type="checkbox" name="active" value="1" @checked($pricingRule->active)>
                                    Active
                                </label>

                                <div class="flex items-end">
                                    <button type="submit" class="inline-flex rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500">
                                        Lưu
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
                            </div>
                        </form>
                    @empty
                        <p class="text-sm text-gray-500">Chưa có rule nào — mọi sản phẩm đang dùng catalog_price mặc định.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
