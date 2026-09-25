async function searchProducts(query) {
    if (query.trim().length === 0) {
        return [];
    }

    const response = await fetch(`/products/search?q=${encodeURIComponent(query)}`);

    if (!response.ok) {
        return [];
    }

    const payload = await response.json();

    return payload.data ?? [];
}

/**
 * Asks the server to run the *real* Pricing Engine for one product/quantity/
 * length_cm combination — the same engine that runs again when the quote is
 * actually saved. The product search endpoint only ever returns the raw
 * catalog price, so without this a category rule (length_ratio, fixed,
 * quantity, custom_formula) never showed up in the "Đơn giá" preview until
 * the sales person reached the confirmation screen.
 */
async function fetchPricePreview({ productId, quantity, lengthCm }) {
    const params = new URLSearchParams({ product_id: productId });

    if (quantity) {
        params.set('quantity', quantity);
    }

    if (lengthCm) {
        params.set('length_cm', lengthCm);
    }

    const response = await fetch(`/products/price-preview?${params.toString()}`);

    if (!response.ok) {
        return null;
    }

    return response.json();
}

// Debounce timers keyed by the row object itself (main or one addon row).
// Kept outside the Alpine component on purpose: the timer id is bookkeeping,
// not UI state, so it doesn't need to go through Alpine's reactive proxy.
const searchTimers = new WeakMap();

function debouncedSearch(row, run, delay = 200) {
    clearTimeout(searchTimers.get(row));
    searchTimers.set(row, setTimeout(run, delay));
}

// Separate debounce timer for the main product's live price preview — kept
// apart from searchTimers (above) so typing a length_cm digit right after
// picking a product doesn't cancel/collide with the still-pending product
// search debounce, since both would otherwise share the same `main` key.
let pricePreviewTimer = null;

function debouncedPricePreview(run, delay = 300) {
    clearTimeout(pricePreviewTimer);
    pricePreviewTimer = setTimeout(run, delay);
}

// Incremented on every previewMainPrice() call. Two preview requests can be
// in flight at once (e.g. one for "length_cm=150" still pending when the
// field gets cleared and a second, quicker request for "no length_cm" goes
// out right after) and the network gives no guarantee they resolve in the
// order they were sent — a slower earlier response arriving after a faster
// later one would otherwise overwrite the correct price with a stale value
// (this is exactly what made clearing the length_cm field not "undo" back
// to the catalog price: the stale length_cm=150 response landed last).
// Each request captures its own id and only applies its result if that id
// still matches the latest one by the time it resolves.
let pricePreviewRequestId = 0;

/**
 * Single Alpine component backing the whole quote form: one main product,
 * an open-ended list of add-on products, and an open-ended list of fees.
 * The subtotal/total shown here is only a client-side preview — the server
 * always recalculates through the same Pricing Engine when the quote is
 * saved, so this preview never has to be perfectly exact.
 *
 * @param {object|null} initial Prefill payload built server-side from the
 *   session draft (see QuoteController::buildFormDraft) when the user came
 *   back here from the confirmation screen via "Quay lại". Null for a
 *   brand-new quote.
 */
function quoteForm(initial = null) {
    const mainDraft = initial?.main ?? null;

    return {
        main: {
            query: mainDraft ? (mainDraft.sku ? `${mainDraft.name} - ${mainDraft.sku}` : mainDraft.name) : '',
            results: [],
            open: false,
            productId: mainDraft?.productId ?? null,
            sku: mainDraft?.sku ?? '',
            imageUrl: mainDraft?.imageUrl ?? null,
            price: mainDraft?.price ?? 0,
            // Snapshot of the catalog price at the moment a product was picked,
            // so "Dùng lại giá gốc" can restore it after a manual edit.
            catalogPrice: mainDraft?.catalogPrice ?? 0,
            priceEdited: mainDraft?.priceEdited ?? false,
            quantity: mainDraft?.quantity ?? 1,
            lengthCm: mainDraft?.lengthCm ?? null,
            // Set when the Pricing Engine's custom-formula calculator
            // evaluated to 0đ and fell back to the sale/list price instead
            // — see previewMainPrice() and ProductPricePreviewController.
            formulaWarning: mainDraft?.formulaWarning ?? null,
        },
        addons: (initial?.addons ?? []).map((addon) => ({
            key: `${Date.now()}-${Math.random()}`,
            query: addon.sku ? `${addon.name} - ${addon.sku}` : addon.name,
            results: [],
            open: false,
            productId: addon.productId,
            sku: addon.sku ?? '',
            imageUrl: addon.imageUrl ?? null,
            price: addon.price ?? 0,
            quantity: addon.quantity ?? 1,
        })),
        fees: (initial?.fees ?? []).map((fee) => ({
            key: `${Date.now()}-${Math.random()}`,
            label: fee.label ?? '',
            amount: fee.amount ?? 0,
        })),
        notes: initial?.notes ?? '',

        // IMPORTANT: any row (main or an addon) passed into these methods
        // must be the *reactive* object Alpine handed you (e.g. the loop
        // variable from x-for, or `this.main`) — never a plain object you
        // built yourself before it entered Alpine's state. Mutating a plain
        // object doesn't trigger a re-render even if you push it into a
        // reactive array afterwards, because Alpine only tracks assignments
        // made through its reactive proxy.

        searchMain() {
            debouncedSearch(this.main, async () => {
                this.main.results = await searchProducts(this.main.query);
                this.main.open = this.main.results.length > 0;
            });
        },

        selectMain(product) {
            this.main.productId = product.id;
            this.main.sku = product.sku;
            this.main.imageUrl = product.image_url ?? null;
            // The raw catalog price from the search result. This is what
            // gets shown whenever no custom length is entered, and never
            // gets overwritten by a formula result — see previewMainPrice().
            this.main.price = product.price;
            this.main.catalogPrice = product.price;
            this.main.priceEdited = false;
            this.main.query = product.sku ? `${product.name} - ${product.sku}` : product.name;
            this.main.open = false;
            // Selecting a product never applies a pricing formula by itself
            // — only typing a length does (previewMainPrice() below). Clear
            // out any length left over from a previously selected product
            // too, so switching products can't silently reuse a size that
            // has nothing to do with the new one and trigger the formula
            // right away.
            this.main.lengthCm = null;
            this.main.formulaWarning = null;
        },

        /**
         * Keeps main.price in sync with what "Kích thước tuỳ chỉnh" is set
         * to. With no custom length entered, the plain catalog price is
         * always shown (many rules — length_ratio, and any custom_formula
         * that multiplies by length_cm — resolve to 0 without a real
         * length, which would be a misleading price to display for a
         * product nobody is customizing). Only once a length is entered
         * does this ask the server to run the real Pricing Engine for it.
         * Debounced and a no-op once the sales person has manually edited
         * the price (their override always wins, same as on save).
         */
        previewMainPrice() {
            if (!this.main.productId || this.main.priceEdited) {
                return;
            }

            if (!this.main.lengthCm) {
                this.main.price = this.main.catalogPrice;
                this.main.formulaWarning = null;

                return;
            }

            const productId = this.main.productId;
            const requestId = ++pricePreviewRequestId;

            debouncedPricePreview(async () => {
                const preview = await fetchPricePreview({
                    productId,
                    quantity: this.main.quantity,
                    lengthCm: this.main.lengthCm,
                });

                // Bail out if: the request failed; a newer previewMainPrice()
                // call has since fired (this response arrived late/out of
                // order — see the comment on pricePreviewRequestId above);
                // the selection changed/was cleared; the length was cleared
                // again while this request was in flight; or the price got
                // manually edited while this request was in flight.
                if (
                    preview === null
                    || requestId !== pricePreviewRequestId
                    || this.main.productId !== productId
                    || this.main.priceEdited
                    || !this.main.lengthCm
                ) {
                    return;
                }

                this.main.price = preview.unit_price;
                this.main.formulaWarning = preview.warning ?? null;
            });
        },

        clearMain() {
            this.main.productId = null;
            this.main.sku = '';
            this.main.imageUrl = null;
            this.main.price = 0;
            this.main.catalogPrice = 0;
            this.main.priceEdited = false;
            this.main.query = '';
            this.main.results = [];
            this.main.formulaWarning = null;
        },

        markMainPriceEdited() {
            this.main.priceEdited = true;
            // A manual override replaces the formula's result entirely, so
            // the "formula evaluated to 0" warning no longer applies.
            this.main.formulaWarning = null;
        },

        resetMainPrice() {
            this.main.price = this.main.catalogPrice;
            this.main.priceEdited = false;
        },

        addAddon() {
            this.addons.push({
                key: `${Date.now()}-${Math.random()}`,
                query: '',
                results: [],
                open: false,
                productId: null,
                sku: '',
                imageUrl: null,
                price: 0,
                quantity: 1,
            });
        },

        searchAddon(addon) {
            debouncedSearch(addon, async () => {
                addon.results = await searchProducts(addon.query);
                addon.open = addon.results.length > 0;
            });
        },

        selectAddon(addon, product) {
            addon.productId = product.id;
            addon.sku = product.sku;
            addon.imageUrl = product.image_url ?? null;
            addon.price = product.price;
            addon.query = product.sku ? `${product.name} - ${product.sku}` : product.name;
            addon.open = false;
        },

        removeAddon(index) {
            this.addons.splice(index, 1);
        },

        addFee() {
            this.fees.push({ key: `${Date.now()}-${Math.random()}`, label: '', amount: 0 });
        },

        removeFee(index) {
            this.fees.splice(index, 1);
        },

        get subtotalPreview() {
            const mainTotal = this.main.productId
                ? Number(this.main.price) * Number(this.main.quantity || 1)
                : 0;

            const addonsTotal = this.addons.reduce((sum, addon) => {
                return sum + (addon.productId ? Number(addon.price) * Number(addon.quantity || 1) : 0);
            }, 0);

            return mainTotal + addonsTotal;
        },

        get feeTotalPreview() {
            return this.fees.reduce((sum, fee) => sum + Number(fee.amount || 0), 0);
        },

        get grandTotalPreview() {
            return this.subtotalPreview + this.feeTotalPreview;
        },

        formatCurrency(value) {
            return new Intl.NumberFormat('vi-VN').format(Math.round(value || 0)) + ' đ';
        },
    };
}

window.quoteForm = quoteForm;
