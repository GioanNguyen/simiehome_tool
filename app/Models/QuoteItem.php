<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    public const ROLE_MAIN = 'main';

    public const ROLE_ADDON = 'addon';

    public const ROLE_FEE = 'fee';

    protected $fillable = [
        'quote_id',
        'role',
        'product_id',
        'product_name',
        'sku',
        'category',
        'image_url',
        'unit_price',
        'quantity',
        'length_cm',
        'surcharge',
        'formula_type',
        'line_total',
        'position',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'surcharge' => 'decimal:2',
            'line_total' => 'decimal:2',
            'meta_json' => 'array',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
