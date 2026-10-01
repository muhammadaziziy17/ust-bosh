<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'sale_id' => Sale::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'product_name_snapshot' => fake()->words(2, true),
            'sku_snapshot' => strtoupper(fake()->bothify('SKU-####')),
            'size_snapshot' => fake()->randomElement(['S', 'M', 'L', 'XL', 'XXL']),
            'color_snapshot' => fake()->optional(0.7)->colorName(),
            'unit_price' => fake()->randomFloat(0, 20000, 2000000),
            'quantity' => 1,
            'line_total' => 0,
        ];
    }
}
