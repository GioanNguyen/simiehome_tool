<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Tính giá bộ nội thất
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow" x-data="quoteForm(@js($draft))">
                <p class="text-sm text-gray-600">
                    Chọn sản phẩm chính, thêm bao nhiêu sản phẩm phụ và phụ phí tuỳ ý, hệ thống sẽ tính tổng ngay.
                    Giá xem trước ở đây chỉ mang tính tham khảo — giá cuối cùng luôn được máy chủ tính lại khi lưu.
                    Sau khi tiếp tục, bạn sẽ nhập thông tin khách hàng ở màn hình xác nhận trước khi lưu báo giá.
                </p>

                <form method="POST" action="{{ route('quotes.preview') }}" class="mt-6 space-y-8">
                    @csrf

                    {{-- Main product --}}
                    <section>
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Sản phẩm chính</h3>

                        <div class="mt-3 grid gap-4 md:grid-cols-4">
                            <div class="relative md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Tìm sản phẩm</label>
                                <input
                                    type="text"
                                    x-model="main.query"
                                    @input="searchMain()"
                                    @focus="main.open = main.results.length > 0"
                                    @click.outside="main.open = false"
                                    autocomplete="off"
                                    placeholder="Nhập tên hoặc SKU..."
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                >
                                <input type="hidden" name="main[product_id]" :value="main.productId">

                                <ul
                                    x-cloak
                                    x-show="main.open"
                                    class="absolute z-10 mt-1 max-h-64 w-full overflow-auto rounded-md border border-gray-200 bg-white shadow-lg"
                                >
                                    <template x-for="product in main.results" :key="product.id">
                                        <li
                                            @click="selectMain(product)"
                                            class="flex items-center gap-3 cursor-pointer px-3 py-2 text-sm hover:bg-brand-50"
                                        >
                                            <img
                                                x-show="product.image_url"
                                                :src="product.image_url"
                                                x-on:error="$el.style.display = 'none'"
                                                class="h-10 w-10 shrink-0 rounded object-cover border border-gray-100"
                                            >
                                            <div>
                                                <p class="font-medium text-gray-900" x-text="product.name"></p>
                                                <p class="text-xs text-gray-500">
                                                    <span x-text="product.category ?? ''"></span>
                                                    <span x-show="product.sku"> · SKU <span x-text="product.sku"></span></span>
                                                    · <span x-text="formatCurrency(product.price)"></span>
                                                </p>
                                            </div>
                                        </li>
                                    </template>
                                    <li x-show="main.results.length === 0" class="px-3 py-2 text-sm text-gray-400">
                                        Không tìm thấy sản phẩm.
                                    </li>
                                </ul>

                                <div class="mt-2 flex items-center gap-2" x-show="main.productId" x-cloak>
                                    <img
                                        x-show="main.imageUrl"
                                        :src="main.imageUrl"
                                        x-on:error="$el.style.display = 'none'"
                                        class="h-12 w-12 shrink-0 rounded object-cover border border-gray-100"
                                    >
                                    <button type="button" class="text-xs text-brand-600 hover:underline" @click="clearMain()">Bỏ chọn</button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Số lượng</label>
                                <input type="number" name="main[quantity]" x-model="main.quantity" @input="previewMainPrice()" min="1" value="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Kích thước tuỳ chỉnh (cm)</label>
                                <input type="number" name="main[length_cm]" x-model="main.lengthCm" @input="previewMainPrice()" min="1" placeholder="Chỉ nhập nếu đặt theo số đo riêng" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <p class="mt-1 text-xs text-gray-400">Bỏ trống nếu dùng đúng giá đã niêm yết theo SKU.</p>
                            </div>

                            <div class="md:col-span-2" x-show="main.productId" x-cloak>
                                <label class="block text-sm font-medium text-gray-700">Đơn giá</label>
                                <input
                                    type="number"
                                    x-model.number="main.price"
                                    @input="markMainPriceEdited()"
                                    min="0"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                >
                                {{-- Only sent to the server when the sales person actually edits the
                                     price — otherwise the field stays empty (→ null) and the server
                                     keeps using the normal Pricing Engine (category formula/surcharge). --}}
                                <input type="hidden" name="main[unit_price_override]" :value="main.priceEdited ? main.price : ''">
                                <p class="mt-1 text-xs text-gray-500">
                                    <span x-show="!main.priceEdited">Giá tự động tính theo công thức của nhóm sản phẩm (cập nhật khi đổi số lượng/kích thước). Có thể sửa tay nếu cần báo giá khác.</span>
                                    <span x-show="main.priceEdited" class="text-brand-600">
                                        Đã sửa tay — bỏ qua công thức/phụ phí theo nhóm sản phẩm.
                                        <button type="button" class="underline" @click="resetMainPrice()">Dùng lại giá gốc</button>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </section>

                    {{-- Add-on products --}}
                    <section>
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Sản phẩm phụ</h3>
                            <button type="button" @click="addAddon()" class="text-sm font-medium text-brand-600 hover:underline">
                                + Thêm sản phẩm phụ
                            </button>
                        </div>

                        <div class="mt-3 space-y-3">
                            <template x-for="(addon, index) in addons" :key="addon.key">
                                <div class="grid gap-4 rounded-md border border-gray-100 p-3 md:grid-cols-8">
                                    <div class="relative md:col-span-3">
                                        <label class="block text-xs font-medium text-gray-500">Sản phẩm</label>
                                        <input
                                            type="text"
                                            x-model="addon.query"
                                            @input="searchAddon(addon)"
                                            @focus="addon.open = addon.results.length > 0"
                                            @click.outside="addon.open = false"
                                            autocomplete="off"
                                            placeholder="Nhập tên hoặc SKU..."
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                        >
                                        <input type="hidden" :name="'addons[' + index + '][product_id]'" :value="addon.productId">

                                        <ul
                                            x-cloak
                                            x-show="addon.open"
                                            class="absolute z-10 mt-1 max-h-64 w-full overflow-auto rounded-md border border-gray-200 bg-white shadow-lg"
                                        >
                                            <template x-for="product in addon.results" :key="product.id">
                                                <li
                                                    @click="selectAddon(addon, product)"
                                                    class="flex items-center gap-3 cursor-pointer px-3 py-2 text-sm hover:bg-brand-50"
                                                >
                                                    <img
                                                        x-show="product.image_url"
                                                        :src="product.image_url"
                                                        x-on:error="$el.style.display = 'none'"
                                                        class="h-10 w-10 shrink-0 rounded object-cover border border-gray-100"
                                                    >
                                                    <div>
                                                        <p class="font-medium text-gray-900" x-text="product.name"></p>
                                                        <p class="text-xs text-gray-500">
                                                            <span x-text="product.category ?? ''"></span>
                                                            <span x-show="product.sku"> · SKU <span x-text="product.sku"></span></span>
                                                            · <span x-text="formatCurrency(product.price)"></span>
                                                        </p>
                                                    </div>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>

                                    <div class="md:col-span-1 flex items-end" x-show="addon.imageUrl" x-cloak>
                                        <img
                                            :src="addon.imageUrl"
                                            x-on:error="$el.style.display = 'none'"
                                            class="h-10 w-10 shrink-0 rounded object-cover border border-gray-100"
                                        >
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium text-gray-500">Số lượng</label>
                                        <input type="number" :name="'addons[' + index + '][quantity]'" x-model="addon.quantity" min="1" value="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    </div>

                                    <div class="md:col-span-1 flex items-end text-sm text-gray-600" x-show="addon.productId">
                                        <span x-text="formatCurrency(addon.price)"></span>
                                    </div>

                                    <div class="md:col-span-1 flex items-end">
                                        <button type="button" @click="removeAddon(index)" class="text-sm text-red-600 hover:underline">Xoá</button>
                                    </div>
                                </div>
                            </template>

                            <p x-show="addons.length === 0" class="text-sm text-gray-400">Chưa có sản phẩm phụ nào.</p>
                        </div>
                    </section>

                    {{-- Fees --}}
                    <section>
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Phụ phí</h3>
                            <button type="button" @click="addFee()" class="text-sm font-medium text-brand-600 hover:underline">
                                + Thêm phụ phí
                            </button>
                        </div>

                        <div class="mt-3 space-y-3">
                            <template x-for="(fee, index) in fees" :key="fee.key">
                                <div class="grid gap-4 md:grid-cols-8">
                                    <div class="md:col-span-5">
                                        <label class="block text-xs font-medium text-gray-500">Tên phí (VD: Phí ship, Phí lắp đặt)</label>
                                        <input type="text" :name="'fees[' + index + '][label]'" x-model="fee.label" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium text-gray-500">Số tiền</label>
                                        <input type="number" :name="'fees[' + index + '][amount]'" x-model="fee.amount" min="0" value="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    </div>

                                    <div class="md:col-span-1 flex items-end">
                                        <button type="button" @click="removeFee(index)" class="text-sm text-red-600 hover:underline">Xoá</button>
                                    </div>
                                </div>
                            </template>

                            <p x-show="fees.length === 0" class="text-sm text-gray-400">Chưa có phụ phí nào.</p>
                        </div>
                    </section>

                    <section>
                        <label class="block text-sm font-medium text-gray-700">Ghi chú</label>
                        <textarea name="notes" rows="3" x-model="notes" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                    </section>

                    {{-- Preview --}}
                    <section class="rounded-md bg-gray-50 p-4">
                        <dl class="grid gap-2 text-sm md:grid-cols-3">
                            <div class="flex justify-between md:block">
                                <dt class="text-gray-500">Tạm tính (sản phẩm)</dt>
                                <dd class="font-medium text-gray-900" x-text="formatCurrency(subtotalPreview)"></dd>
                            </div>
                            <div class="flex justify-between md:block">
                                <dt class="text-gray-500">Tổng phụ phí</dt>
                                <dd class="font-medium text-gray-900" x-text="formatCurrency(feeTotalPreview)"></dd>
                            </div>
                            <div class="flex justify-between md:block">
                                <dt class="text-gray-500">Tổng giá bộ (tạm tính)</dt>
                                <dd class="text-lg font-semibold text-brand-600" x-text="formatCurrency(grandTotalPreview)"></dd>
                            </div>
                        </dl>
                    </section>

                    <div>
                        <button type="submit" class="inline-flex rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500">
                            Tiếp tục &rarr; Xác nhận thông tin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
