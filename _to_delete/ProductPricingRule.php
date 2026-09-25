<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPricingRule extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'applies_to',
        'surcharge_amount',
        'active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'surcharge_amount' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
