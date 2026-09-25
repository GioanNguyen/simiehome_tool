<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Product/variation types that carry an actual sellable price and can be
     * picked as a quote line item. "variable" rows are parent/grouping rows
     * only (no price of their own) and are excluded from search.
     */
    public const SELLABLE_TYPES = ['simple', 'variation'];

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
     * selling price in the catalog), otherwise the regular price.
     */
    public function effectivePrice(): float
    {
        $sale = (float) ($this->sale_price ?? 0);
        $regular = (float) ($this->regular_price ?? 0);

        return $sale > 0 ? $sale : $regular;
    }

    public function isSellable(): bool
    {
        return in_array($this->type, self::SELLABLE_TYPES, true);
    }
}
