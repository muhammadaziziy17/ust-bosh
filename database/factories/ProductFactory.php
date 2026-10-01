<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->words(2, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'category' => fake()->optional()->word(),
            'sale_price' => fake()->randomFloat(0, 20000, 2000000),
            'is_active' => true,
        ];
    }
}
