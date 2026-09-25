<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1_000_000, 10_000_000);
        $feeTotal = fake()->numberBetween(0, 500_000);

        return [
            'quote_code' => 'QT-'.Str::upper(Str::random(8)),
            'user_id' => User::factory(),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('09########'),
            'customer_email' => null,
            'subtotal' => $subtotal,
            'fee_total' => $feeTotal,
            'grand_total' => $subtotal + $feeTotal,
            'notes' => null,
        ];
    }
}
