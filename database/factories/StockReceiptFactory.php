<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\StockReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockReceipt>
 */
class StockReceiptFactory extends Factory
{
    protected $model = StockReceipt::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'receipt_no' => 'K-'.strtoupper(fake()->unique()->bothify('####??')),
            'status' => StockReceipt::STATUS_POSTED,
            'created_by' => User::factory(),
            'posted_by' => User::factory(),
            'posted_at' => now(),
            'received_at' => now(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
