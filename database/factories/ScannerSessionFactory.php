<?php

namespace Database\Factories;

use App\Models\ScannerSession;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScannerSession> */
class ScannerSessionFactory extends Factory
{
    protected $model = ScannerSession::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'cashier_id' => User::factory(),
            'token_hash' => hash('sha256', bin2hex(random_bytes(32))),
            'expires_at' => now()->addMinutes(15),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'closed_at' => now(),
        ]);
    }
}
