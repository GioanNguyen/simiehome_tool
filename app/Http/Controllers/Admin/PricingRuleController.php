<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Pricing\Calculators\CustomFormulaCalculator;
use App\Domain\Pricing\Formula\FormulaEvaluator;
use App\Domain\Pricing\Formula\InvalidFormulaException;
use App\Http\Controllers\Controller;
use App\Models\PricingRule;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PricingRuleController extends Controller
{
    /** Select value for a rule that applies to every category. */
    public const ALL_CATEGORIES = '__all__';

    public const FORMULA_TYPES = ['catalog_price', 'length_ratio', 'fixed', 'quantity', 'custom_formula'];

    /**
     * Vietnamese label shown in the formula_type <select> dropdown, keyed by
     * the internal formula_type string, so admins don't have to read raw
     * codes like "length_ratio".
     *
     * @var array<string, string>
     */
    public const FORMULA_LABELS = [
        'catalog_price' => 'Giá theo catalog (mặc định)',
        'length_ratio' => 'Theo tỷ lệ chiều dài',
        'fixed' => 'Giá cố định',
        'quantity' => 'Theo số lượng',
        'custom_formula' => 'Công thức tuỳ chỉnh',
    ];

    /**
     * Vietnamese, read-only explanation of exactly what each built-in
     * formula_type computes — shown to the admin so they can double-check
     * the selected formula matches what they intend, without being able to
     * edit it (only custom_formula stays freely editable).
     *
     * @var array<string, string>
     */
    public const FORMULA_DESCRIPTIONS = [
        'catalog_price' => 'Đơn giá = giá đã import từ catalog (lấy giá khuyến mãi nếu có, không thì lấy giá niêm yết). Thành tiền = đơn giá × số lượng + phụ phí. Không cần cấu hình thêm.',
        'length_ratio' => 'Đơn giá = Giá catalog × Chiều dài khách chọn (cm) ÷ Chiều dài chuẩn (mặc định 100cm nếu chưa cấu hình riêng). Thành tiền = đơn giá × số lượng + phụ phí.',
        'fixed' => 'Đơn giá = một mức giá cố định do admin cấu hình sẵn cho rule này (nếu chưa cấu hình thì tạm dùng giá catalog). Thành tiền = đơn giá × số lượng + phụ phí.',
        'quantity' => 'Đơn giá = một mức giá cố định theo số lượng do admin cấu hình sẵn cho rule này (nếu chưa cấu hình thì tạm dùng giá catalog). Thành tiền = đơn giá × số lượng + phụ phí.',
    ];

    /**
     * Vietnamese label for each column the "Công thức" expression is
     * allowed to reference — shown in the note above the expression input
     * so an admin doesn't have to guess what a raw column name like
     * "regular_price" actually means. Keyed by the same variable names
     * {@see \App\Domain\Pricing\Calculators\CustomFormulaCalculator} feeds
     * into the expression.
     *
     * @var array<string, string>
     */
    public const FORMULA_VARIABLE_LABELS = [
        'base_price' => 'Giá gốc tuỳ chỉnh (cột base_price trong dữ liệu sản phẩm — nhập khi import hoặc sửa sản phẩm)',
        'regular_price' => 'Giá niêm yết (cột regular_price — giá gốc đã import từ catalog)',
        'sale_price' => 'Giá khuyến mãi (cột sale_price — giá đã import từ catalog, nếu có)',
        'length_cm' => 'Chiều dài khách chọn (cm) — khách/nhân viên bán hàng nhập khi lập báo giá, không phải cột trong sản phẩm',
    ];

    /**
     * The 2 columns that power the "use_product_extra_fee" checkbox
     * instead of the expression — listed separately (in the same note) so
     * an admin doesn't try typing "extra_fee" into the "Công thức" field.
     *
     * @var array<string, string>
     */
    public const EXTRA_FEE_VARIABLE_LABELS = [
        'extra_fee' => 'Phụ phí cố định của sản phẩm (cột extra_fee — nhập khi import hoặc sửa sản phẩm)',
        'minimum_length' => 'Chiều dài tối thiểu (cm) của sản phẩm (cột minimum_length — nhập khi import hoặc sửa sản phẩm)',
    ];

    public function index(): View
    {
        return view('admin.pricing-rules.index', [
            'pricingRules' => PricingRule::query()
                ->with('product')
                ->orderByRaw('category is null')
                ->orderBy('category')
                ->orderBy('id')
                ->get(),
            'categories' => Product::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'formulaTypes' => self::FORMULA_TYPES,
            'formulaLabels' => self::FORMULA_LABELS,
            'formulaDescriptions' => self::FORMULA_DESCRIPTIONS,
            'formulaVariables' => CustomFormulaCalculator::AVAILABLE_VARIABLES,
            'formulaVariableLabels' => self::FORMULA_VARIABLE_LABELS,
            'extraFeeVariableLabels' => self::EXTRA_FEE_VARIABLE_LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string'],
            'product_id' => ['nullable', 'exists:products,id'],
            'formula_type' => ['required', 'in:'.implode(',', self::FORMULA_TYPES)],
            'surcharge_amount' => ['required', 'numeric', 'min:0'],
            'expression' => ['required_if:formula_type,custom_formula', 'nullable', 'string', 'max:500'],
            // Only meaningful for custom_formula — see formula_config below.
            'use_product_extra_fee' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $productId = $validated['product_id'] ?? null;
        $category = $validated['category'] ?? null;

        // A rule must target something: a product, a category, or — via the
        // explicit "Tất cả nhóm sản phẩm" option — every category (stored as
        // category = null, product_id = null; see PricingEngine::resolveRule).
        if ($productId === null && ($category === null || $category === '')) {
            return back()
                ->withErrors(['category' => 'Vui lòng chọn nhóm sản phẩm, "Tất cả nhóm sản phẩm" hoặc nhập ID sản phẩm.'])
                ->withInput();
        }

        if ($category === self::ALL_CATEGORIES) {
            $category = null;
        }

        if ($validated['formula_type'] === 'custom_formula') {
            $expressionError = $this->validateExpression($validated['expression']);

            if ($expressionError !== null) {
                return back()->withErrors(['expression' => $expressionError])->withInput();
            }
        }

        PricingRule::query()->create([
            'category' => $productId ? null : $category,
            'product_id' => $validated['product_id'] ?? null,
            'formula_type' => $validated['formula_type'],
            'surcharge_amount' => $validated['surcharge_amount'],
            'formula_config' => $validated['formula_type'] === 'custom_formula'
                ? [
                    'expression' => trim($validated['expression']),
                    // When true, this rule's surcharge_amount above is
                    // ignored for actual pricing (kept in the DB purely as
                    // a leftover value) and the surcharge instead comes
                    // from the product's own extra_fee column, applied
                    // only when the quoted length_cm is at or below the
                    // product's minimum_length — see
                    // CustomFormulaCalculator::productExtraFeeSurcharge().
                    'use_product_extra_fee' => $request->boolean('use_product_extra_fee'),
                ]
                : null,
            'notes' => $validated['notes'] ?? null,
            'active' => true,
        ]);

        return redirect()->route('admin.pricing-rules.index');
    }

    public function update(Request $request, PricingRule $pricingRule): RedirectResponse
    {
        $validated = $request->validate([
            'formula_type' => ['required', 'in:'.implode(',', self::FORMULA_TYPES)],
            'surcharge_amount' => ['required', 'numeric', 'min:0'],
            'expression' => ['required_if:formula_type,custom_formula', 'nullable', 'string', 'max:500'],
            // Only meaningful for custom_formula — see formula_config below.
            'use_product_extra_fee' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);

        if ($validated['formula_type'] === 'custom_formula') {
            $expressionError = $this->validateExpression($validated['expression']);

            if ($expressionError !== null) {
                return back()->withErrors(['expression' => $expressionError])->withInput();
            }
        }

        $updates = [
            'formula_type' => $validated['formula_type'],
            'surcharge_amount' => $validated['surcharge_amount'],
            'active' => (bool) ($validated['active'] ?? false),
        ];

        // formula_config also holds parameters for the other formula types
        // (fixed_price, unit_price, unit_cm) that this form doesn't expose —
        // only touch it when entering or leaving custom_formula, so an
        // unrelated edit (e.g. just toggling "active") never wipes those out.
        if ($validated['formula_type'] === 'custom_formula') {
            $updates['formula_config'] = [
                'expression' => trim($validated['expression']),
                'use_product_extra_fee' => $request->boolean('use_product_extra_fee'),
            ];
        } elseif ($pricingRule->formula_type === 'custom_formula') {
            $updates['formula_config'] = null;
        }

        $pricingRule->update($updates);

        return redirect()->route('admin.pricing-rules.index');
    }

    public function destroy(PricingRule $pricingRule): RedirectResponse
    {
        $pricingRule->delete();

        return redirect()
            ->route('admin.pricing-rules.index')
            ->with('status', 'Đã xoá công thức.');
    }

    /**
     * @return string|null a Vietnamese error message if the expression is
     *                     invalid, or null when it's fine.
     */
    private function validateExpression(?string $expression): ?string
    {
        // Distinct, non-trivial sample values — not all 1.0 or 0 — so a
        // formula involving subtraction/division between two of these
        // variables doesn't coincidentally divide by zero here and fail
        // validation for a formula that would work fine on real data.
        $sampleValues = [
            'base_price' => 1_000_000.0,
            'regular_price' => 900_000.0,
            'sale_price' => 850_000.0,
            'length_cm' => 140.0,
        ];

        try {
            (new FormulaEvaluator)->evaluate((string) $expression, $sampleValues);

            return null;
        } catch (InvalidFormulaException $exception) {
            return $exception->getMessage();
        }
    }
}
