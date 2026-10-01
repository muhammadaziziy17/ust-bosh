<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    private function makeCashier(Shop $shop): User
    {
        return User::factory()->create([
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
        ]);
    }

    public function test_pos_page_loads_for_cashier(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $this->actingAs($cashier);

        $this->get(route('pos.index'))
            ->assertOk()
            ->assertSee(__('pos.title'));
    }

    public function test_catalog_search_finds_by_name_sku_and_barcode(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $product = Product::factory()->for($shop)->create(['name' => 'Ko\'ylak', 'sku' => 'KOY-1']);
        ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'L',
            'barcode' => '4812345678901',
            'stock_on_hand' => 7,
        ]);

        $this->actingAs($cashier);

        $this->getJson(route('pos.catalog', ['q' => 'ko\'ylak']))
            ->assertOk()
            ->assertJsonPath('products.0.name', 'Ko\'ylak');

        $this->getJson(route('pos.catalog', ['q' => 'KOY-1']))
            ->assertOk()
            ->assertJsonCount(1, 'products');

        $this->getJson(route('pos.catalog', ['q' => '4812345678901']))
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.variants.0.size', 'L');
    }

    public function test_catalog_does_not_include_other_shops_products(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        Product::factory()->for($shop)->create(['name' => 'O\'zimniki']);
        Product::factory()->for($otherShop)->create(['name' => 'Boshqa do\'kon']);

        $this->actingAs($cashier);

        $response = $this->getJson(route('pos.catalog', ['q' => '']));

        $response->assertOk();
        $names = collect($response->json('products'))->pluck('name');
        $this->assertTrue($names->contains('O\'zimniki'));
        $this->assertFalse($names->contains('Boshqa do\'kon'));
    }

    public function test_checkout_decrements_stock_and_creates_sale(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $product = Product::factory()->for($shop)->create(['sale_price' => 120000]);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'M',
            'color' => 'Qora',
            'stock_on_hand' => 10,
        ]);

        $this->actingAs($cashier);

        $response = $this->postJson(route('pos.sales.store'), [
            'payment_method' => Sale::PAYMENT_CASH,
            'idempotency_key' => 'key-123',
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 3],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('sale.total', 360000);

        $this->assertSame(7, $variant->fresh()->stock_on_hand);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseHas('sale_items', [
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $product->name,
            'size_snapshot' => 'M',
            'color_snapshot' => 'Qora',
            'quantity' => 3,
            'line_total' => 360000,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovement::TYPE_SALE,
            'quantity_delta' => -3,
            'stock_after' => 7,
        ]);
    }

    public function test_checkout_rejects_insufficient_stock_without_partial_sale(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $product = Product::factory()->for($shop)->create(['sale_price' => 100000]);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create(['stock_on_hand' => 2]);

        $this->actingAs($cashier);

        $response = $this->postJson(route('pos.sales.store'), [
            'payment_method' => Sale::PAYMENT_CARD,
            'idempotency_key' => 'key-insufficient',
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(409);
        $this->assertSame(2, $variant->fresh()->stock_on_hand);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
    }

    public function test_checkout_with_same_idempotency_key_returns_existing_sale(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $product = Product::factory()->for($shop)->create(['sale_price' => 100000]);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create(['stock_on_hand' => 10]);

        $this->actingAs($cashier);

        $payload = [
            'payment_method' => Sale::PAYMENT_CASH,
            'idempotency_key' => 'key-same',
            'items' => [['variant_id' => $variant->id, 'quantity' => 2]],
        ];

        $first = $this->postJson(route('pos.sales.store'), $payload);
        $first->assertOk();
        $saleNo = $first->json('sale.sale_no');

        // Ikkinchi so'rov — xuddi shu kalit bilan qayta ishlamaydi
        $second = $this->postJson(route('pos.sales.store'), $payload);
        $second->assertOk();
        $second->assertJsonPath('sale.sale_no', $saleNo);

        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(8, $variant->fresh()->stock_on_hand);
    }

    public function test_checkout_validates_payment_method(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $variant = ProductVariant::factory()->for($shop)->create(['stock_on_hand' => 5]);

        $this->actingAs($cashier);

        $this->postJson(route('pos.sales.store'), [
            'payment_method' => 'bitcoin',
            'idempotency_key' => 'key-pay',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['payment_method']);
    }

    public function test_checkout_rejects_cross_shop_variant(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $foreignVariant = ProductVariant::factory()->for($otherShop)->create(['stock_on_hand' => 5]);

        $this->actingAs($cashier);

        $this->postJson(route('pos.sales.store'), [
            'payment_method' => Sale::PAYMENT_CASH,
            'idempotency_key' => 'key-cross',
            'items' => [['variant_id' => $foreignVariant->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['items']);

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_checkout_rejects_inactive_variant(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $product = Product::factory()->for($shop)->create(['sale_price' => 50000]);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create([
            'stock_on_hand' => 5,
            'is_active' => false,
        ]);

        $this->actingAs($cashier);

        $this->postJson(route('pos.sales.store'), [
            'payment_method' => Sale::PAYMENT_CASH,
            'idempotency_key' => 'key-inactive',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(5, $variant->fresh()->stock_on_hand);
    }

    public function test_owner_cannot_access_pos(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'shop_id' => $shop->id]);

        $this->actingAs($owner);

        $this->get(route('pos.index'))->assertStatus(403);
    }
}
