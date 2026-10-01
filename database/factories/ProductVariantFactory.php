<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'product_id' => Product::factory(),
            'size' => fake()->randomElement(ProductVariant::SIZE_OPTIONS),
            'color' => fake()->optional(0.7)->colorName(),
            'barcode' => fake()->unique()->optional(0.5)->numerify('4#############'),
            'stock_on_hand' => 0,
            'reorder_level' => fake()->randomElement([0, 2, 5, 10]),
            'is_active' => true,
        ];
    }
}
