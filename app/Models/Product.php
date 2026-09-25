<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Product/variation types that carry an actual catalog price (regular
     * or sale price) and can always be picked as a quote line item.
     */
    public const SELLABLE_TYPES = ['simple', 'variation'];

    /**
     * Parent/grouping row type (e.g. a WooCommerce variable product with no
     * price of its own — each concrete size/color lives in its own
     * "variation" row instead). Normally excluded from quoting, but see
     * scopeQuotable(): a "variable" row that does carry a base_price is
     * still quotable, since that's exactly what base_price is for — letting
     * sales quote a custom, not-yet-listed size for the whole product line
     * via a custom_formula pricing rule.
     */
    private const VARIABLE_TYPE = 'variable';

    protected $fillable = [
        'external_id',
        'parent_external_id',
        'type',
        'sku',
        'name',
        'category',
        'regular_price',
        'sale_price',
        'base_price',
        'extra_fee',
        'minimum_length',
        'image_url',
        'attributes_json',
        'status',
        'source',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'regular_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'base_price' => 'decimal:2',
            'extra_fee' => 'decimal:2',
            'minimum_length' => 'decimal:2',
            'attributes_json' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class);
    }

    /**
     * The price to use for calculations: sale price when set (the real
     * selling price in the catalog), otherwise the regular price, otherwise
     * (for a "variable" parent row with no catalog price of its own) the
     * base_price — so a quote line for one of these still shows a sane
     * starting price instead of 0đ before any custom_formula rule applies.
     */
    public function effectivePrice(): float
    {
        $sale = (float) ($this->sale_price ?? 0);

        if ($sale > 0) {
            return $sale;
        }

        $regular = (float) ($this->regular_price ?? 0);

        if ($regular > 0) {
            return $regular;
        }

        return (float) ($this->base_price ?? 0);
    }

    public function isSellable(): bool
    {
        return in_array($this->type, self::SELLABLE_TYPES, true);
    }

    /**
     * Products that can be picked as a quote line item: simple/variation
     * rows with their own catalog price, plus "variable" parent rows that
     * carry a base_price (needed so sales can quote a custom, not-yet-listed
     * size for the whole product line via a custom_formula pricing rule).
     * A "variable" row with no base_price has no usable price at all and
     * stays excluded — it exists purely to group its variations.
     */
    public function scopeQuotable(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query->whereIn('type', self::SELLABLE_TYPES)
                ->orWhere(function (Builder $query) {
                    $query->where('type', self::VARIABLE_TYPE)->whereNotNull('base_price');
                });
        });
    }
}
