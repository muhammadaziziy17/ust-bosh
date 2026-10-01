<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'sale_no' => 'S-'.strtoupper(Str::random(10)),
            'status' => Sale::STATUS_COMPLETED,
            'cashier_id' => User::factory(),
            'sold_at' => now(),
            'payment_method' => fake()->randomElement([Sale::PAYMENT_CASH, Sale::PAYMENT_CARD]),
            'subtotal' => 0,
            'discount_amount' => 0,
            'total' => 0,
            'idempotency_key' => Str::uuid()->toString(),
        ];
    }
}
