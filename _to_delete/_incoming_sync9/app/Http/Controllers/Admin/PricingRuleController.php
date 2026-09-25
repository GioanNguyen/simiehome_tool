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
    public const FORMULA_TYPES = ['catalog_price', 'length_ratio', 'fixed', 'quantity', 'custom_formula'];

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
            'formulaVariables' => CustomFormulaCalculator::AVAILABLE_VARIABLES,
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
            'notes' => ['nullable', 'string'],
        ]);

        if ($validated['formula_type'] === 'custom_formula') {
            $expressionError = $this->validateExpression($validated['expression']);

            if ($expressionError !== null) {
                return back()->withErrors(['expression' => $expressionError])->withInput();
            }
        }

        PricingRule::query()->create([
            'category' => ($validated['product_id'] ?? null) ? null : ($validated['category'] ?? null),
            'product_id' => $validated['product_id'] ?? null,
            'formula_type' => $validated['formula_type'],
            'surcharge_amount' => $validated['surcharge_amount'],
            'formula_config' => $validated['formula_type'] === 'custom_formula'
                ? ['expression' => trim($validated['expression'])]
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
            $updates['formula_config'] = ['expression' => trim($validated['expression'])];
        } elseif ($pricingRule->formula_type === 'custom_formula') {
            $updates['formula_config'] = null;
        }

        $pricingRule->update($updates);

        return redirect()->route('admin.pricing-rules.index');
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
