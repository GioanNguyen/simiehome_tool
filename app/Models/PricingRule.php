<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    protected $fillable = [
        'category',
        'product_id',
        'formula_type',
        'surcharge_amount',
        'formula_config',
        'active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'surcharge_amount' => 'decimal:2',
            'formula_config' => 'array',
            'active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
