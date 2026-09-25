<?php

namespace App\Models;

use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    protected $fillable = [
        'quote_code',
        'user_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'subtotal',
        'fee_total',
        'discount_type',
        'discount_value',
        'discount_total',
        'grand_total',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'fee_total' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position');
    }

    public function mainItem(): HasOne
    {
        return $this->hasOne(QuoteItem::class)->where('role', 'main');
    }

    public function addonItems(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->where('role', 'addon')->orderBy('position');
    }

    public function feeItems(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->where('role', 'fee')->orderBy('position');
    }
}
