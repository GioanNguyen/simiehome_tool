<?php

namespace App\Http\Controllers;

use App\Domain\Pricing\LineCalculationResult;
use App\Domain\Pricing\QuoteCalculator;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Services\Pdf\RemoteImageEmbedder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class QuoteController extends Controller
{
    /**
     * Session key holding the raw (validated) calculator input between the
     * "preview" step and the "confirm" step. Storing the raw input rather
     * than the computed result means confirm()/store() always recompute
     * through the same Pricing Engine, so nothing shown on the confirmation
     * screen can ever drift from what actually gets saved.
     */
    private const SESSION_DRAFT_KEY = 'quote.draft_input';

    public function __construct(
        private readonly QuoteCalculator $calculator,
    ) {}

    /**
     * Searchable, paginated quote history.
     */
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $canViewAll = $request->user()->canViewAllQuotes();

        // Sales only ever sees their own quotes — enforced server-side
        // regardless of what a "user_id" query param claims, since it's the
        // list itself that's restricted, not just which filter UI is shown.
        $userId = $canViewAll ? ($validated['user_id'] ?? null) : $request->user()->id;

        $quotes = Quote::query()
            ->with('user')
            ->when($userId, function ($query, $userId) {
                $query->where('user_id', $userId);
            })
            ->when($validated['date'] ?? null, function ($query, $date) {
                $query->whereDate('created_at', $date);
            })
            ->when($validated['customer'] ?? null, function ($query, $customer) {
                $query->where(function ($query) use ($customer) {
                    $query->where('customer_name', 'like', "%{$customer}%")
                        ->orWhere('customer_phone', 'like', "%{$customer}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('quotes.index', [
            'quotes' => $quotes,
            'users' => $canViewAll ? User::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'canViewAll' => $canViewAll,
            'filters' => [
                'date' => $validated['date'] ?? '',
                'user_id' => $userId ?? '',
                'customer' => $validated['customer'] ?? '',
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * The calculator form. When the sales person comes back here from the
     * confirmation screen ("Quay lại") the previously entered main/add-on/
     * fee selections are restored from the session draft so nothing is
     * lost.
     */
    public function create(Request $request): View
    {
        if ($request->boolean('new')) {
            $request->session()->forget(self::SESSION_DRAFT_KEY);
        }

        return view('quotes.create', [
            'draft' => $this->buildFormDraft($request),
        ]);
    }

    /**
     * Validates + calculates the quote, stashes the raw input in the
     * session, and moves on to the confirmation screen. Nothing is
     * persisted yet.
     */
    public function preview(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->calculatorRules());

        if (($validated['discount']['type'] ?? null) === 'percent' && (float) ($validated['discount']['value'] ?? 0) > 100) {
            return redirect()
                ->route('quotes.create')
                ->withErrors(['discount' => 'Giảm giá theo % không được vượt quá 100%.'])
                ->withInput();
        }

        // Fail fast with a friendly message instead of a blank confirmation
        // screen if nothing sellable was actually entered.
        if (empty($validated['main']['product_id'] ?? null)
            && collect($validated['addons'] ?? [])->every(fn ($addon) => empty($addon['product_id'] ?? null))
            && collect($validated['fees'] ?? [])->every(fn ($fee) => empty($fee['amount'] ?? null))) {
            return redirect()
                ->route('quotes.create')
                ->withErrors(['main' => 'Vui lòng chọn ít nhất một sản phẩm hoặc phụ phí trước khi tiếp tục.'])
                ->withInput();
        }

        $request->session()->put(self::SESSION_DRAFT_KEY, $validated);

        return redirect()->route('quotes.confirm');
    }

    /**
     * Confirmation screen: read-only recap of the computed line items and
     * totals, plus the customer fields the wireframe calls out as missing
     * from the calculator screen itself.
     */
    public function confirm(Request $request): View|RedirectResponse
    {
        $input = $request->session()->get(self::SESSION_DRAFT_KEY);

        if ($input === null) {
            return redirect()
                ->route('quotes.create')
                ->withErrors(['main' => 'Chưa có thông tin báo giá để xác nhận, vui lòng nhập lại.']);
        }

        return view('quotes.confirm', [
            'result' => $this->calculator->calculate($input),
            'notes' => $input['notes'] ?? null,
        ]);
    }

    /**
     * Finalizes the quote: re-validates only the customer fields collected
     * on the confirmation screen, recomputes the line items from the
     * session draft (never trusting anything the confirmation form itself
     * might submit for pricing), and persists everything.
     */
    public function store(Request $request): RedirectResponse
    {
        $input = $request->session()->get(self::SESSION_DRAFT_KEY);

        if ($input === null) {
            return redirect()
                ->route('quotes.create')
                ->withErrors(['main' => 'Chưa có thông tin báo giá để lưu, vui lòng nhập lại.']);
        }

        $customer = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:500'],
        ], [
            'customer_name.required' => 'Vui lòng nhập tên khách hàng.',
            'customer_phone.required' => 'Vui lòng nhập số điện thoại khách hàng.',
        ]);

        $result = $this->calculator->calculate($input);

        $quote = DB::transaction(function () use ($request, $input, $customer, $result) {
            $quote = Quote::query()->create([
                'quote_code' => 'QT-'.Str::upper(Str::random(8)),
                'user_id' => $request->user()->id,
                'customer_name' => $customer['customer_name'] ?? null,
                'customer_phone' => $customer['customer_phone'] ?? null,
                'customer_email' => $customer['customer_email'] ?? null,
                'customer_address' => $customer['customer_address'] ?? null,
                'subtotal' => $result->subtotal,
                'fee_total' => $result->feeTotal,
                'discount_type' => $result->discountType,
                'discount_value' => $result->discountValue,
                'discount_total' => $result->discountTotal,
                'grand_total' => $result->grandTotal,
                'notes' => $input['notes'] ?? null,
            ]);

            $position = 0;

            if ($result->main !== null) {
                $this->createItem($quote, 'main', $result->main, $position++);
            }

            foreach ($result->addons as $addon) {
                $this->createItem($quote, 'addon', $addon, $position++);
            }

            foreach ($result->fees as $fee) {
                $quote->items()->create([
                    'role' => 'fee',
                    'product_name' => $fee['label'],
                    'unit_price' => $fee['amount'],
                    'quantity' => 1,
                    'line_total' => $fee['amount'],
                    'position' => $position++,
                ]);
            }

            return $quote;
        });

        $request->session()->forget(self::SESSION_DRAFT_KEY);

        return redirect()->route('quotes.show', $quote)->with('status', 'Đã lưu báo giá thành công.');
    }

    public function show(Request $request, Quote $quote): View
    {
        abort_unless($request->user()->can('view-quote', $quote), 403);

        $quote->load(['items', 'user']);

        return view('quotes.show', [
            'quote' => $quote,
        ]);
    }

    /**
     * Renders the quote as a PDF from the saved snapshot in quote_items —
     * never recalculated against the live catalog/pricing rules, per the
     * planning doc.
     */
    public function pdf(Request $request, Quote $quote): HttpResponse
    {
        abort_unless($request->user()->can('view-quote', $quote), 403);

        $quote->load(['items', 'user']);

        $pdf = Pdf::loadView('quotes.pdf', [
            'quote' => $quote,
            'imageEmbedder' => new RemoteImageEmbedder,
        ])->setPaper('a4');

        return $pdf->stream($quote->quote_code.'.pdf');
    }

    /**
     * Permanently deletes a quote (and its snapshotted line items, via the
     * cascadeOnDelete foreign key). Admin only — Manager can see every
     * quote but is explicitly read-only, and Sales never gets destructive
     * access even to a quote they created themselves.
     */
    public function destroy(Request $request, Quote $quote): RedirectResponse
    {
        abort_unless($request->user()->can('delete-quotes'), 403);

        $quoteCode = $quote->quote_code;
        $quote->delete();

        return redirect()->route('quotes.index')->with('status', "Đã xoá báo giá {$quoteCode}.");
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function calculatorRules(): array
    {
        return [
            'main.product_id' => ['nullable', 'exists:products,id'],
            'main.quantity' => ['nullable', 'integer', 'min:1'],
            'main.length_cm' => ['nullable', 'integer', 'min:1'],
            'main.unit_price_override' => ['nullable', 'numeric', 'min:0'],

            'addons' => ['array'],
            'addons.*.product_id' => ['nullable', 'exists:products,id'],
            'addons.*.quantity' => ['nullable', 'integer', 'min:1'],
            'addons.*.length_cm' => ['nullable', 'integer', 'min:1'],

            'fees' => ['array'],
            'fees.*.label' => ['nullable', 'string', 'max:255'],
            'fees.*.amount' => ['nullable', 'numeric', 'min:0'],

            'discount.type' => ['nullable', 'in:amount,percent'],
            'discount.value' => ['nullable', 'numeric', 'min:0'],

            'notes' => ['nullable', 'string'],
        ];
    }

    private function createItem(Quote $quote, string $role, LineCalculationResult $line, int $position): void
    {
        $quote->items()->create([
            'role' => $role,
            'product_id' => $line->productId,
            'product_name' => $line->productName,
            'sku' => $line->sku,
            'category' => $line->category,
            'image_url' => $line->imageUrl,
            'unit_price' => $line->unitPrice,
            'quantity' => $line->quantity,
            'length_cm' => $line->lengthCm,
            // Total fee contained in this line's line_total (snapshot, for
            // display only): the line-level surcharge plus any per-piece fee
            // already folded into unit_price.
            'surcharge' => $line->surcharge + $line->unitSurcharge * $line->quantity,
            'formula_type' => $line->formulaType,
            'line_total' => $line->lineTotal,
            'position' => $position,
        ]);
    }

    /**
     * Builds the Alpine prefill payload for the create-form from whatever
     * session draft exists (if any), including the human-readable product
     * name/SKU that the raw draft alone doesn't carry.
     *
     * @return array<string, mixed>|null
     */
    private function buildFormDraft(Request $request): ?array
    {
        $input = $request->session()->get(self::SESSION_DRAFT_KEY);

        if ($input === null) {
            return null;
        }

        $result = $this->calculator->calculate($input);

        return [
            'main' => $result->main ? $this->lineToDraft($result->main) : null,
            'addons' => array_map(fn (LineCalculationResult $addon) => $this->lineToDraft($addon), $result->addons),
            'fees' => $result->fees,
            'discount' => [
                'type' => $input['discount']['type'] ?? 'amount',
                'value' => $input['discount']['value'] ?? '',
            ],
            'notes' => $input['notes'] ?? '',
        ];
    }

    private function lineToDraft(LineCalculationResult $line): array
    {
        // The catalog price shown for "Dùng lại giá gốc" always reflects
        // the product's real catalog price, even when the line itself was
        // manually overridden — so restoring after "Quay lại" still works.
        $catalogPrice = $line->formulaType === 'manual_override' && $line->productId !== null
            ? (Product::query()->find($line->productId)?->effectivePrice() ?? $line->unitPrice)
            : $line->unitPrice;

        return [
            'productId' => $line->productId,
            'name' => $line->productName,
            'sku' => $line->sku,
            'category' => $line->category,
            'imageUrl' => $line->imageUrl,
            'price' => $line->unitPrice,
            'catalogPrice' => $catalogPrice,
            'priceEdited' => $line->formulaType === 'manual_override',
            'quantity' => $line->quantity,
            'lengthCm' => $line->lengthCm,
            'surcharge' => $line->formulaType === 'manual_override' ? 0 : $line->surcharge,
            'unitSurcharge' => $line->formulaType === 'manual_override' ? 0 : $line->unitSurcharge,
            'formulaWarning' => $line->warning,
        ];
    }
}
