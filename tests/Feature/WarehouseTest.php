<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\StockReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    private function makeWarehouse(Shop $shop): User
    {
        return User::factory()->create([
            'role' => User::ROLE_WAREHOUSE,
            'shop_id' => $shop->id,
        ]);
    }

    public function test_warehouse_dashboard_loads_with_stats(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $product = Product::factory()->for($shop)->create(['name' => 'Ko\'ylak']);
        ProductVariant::factory()->for($shop)->for($product)->create(['stock_on_hand' => 5]);

        $this->actingAs($warehouse);

        $this->get(route('warehouse.dashboard'))
            ->assertOk()
            ->assertSee('Ko\'ylak')
            ->assertSee(__('warehouse.title'));
    }

    public function test_warehouse_stats_ignore_inactive_variants(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $product = Product::factory()->for($shop)->create();

        ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'M',
            'stock_on_hand' => 1,
            'reorder_level' => 5,
            'is_active' => true,
        ]);
        ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'L',
            'stock_on_hand' => 0,
            'reorder_level' => 5,
            'is_active' => false,
        ]);

        $this->actingAs($warehouse);

        $stats = $this->get(route('warehouse.dashboard'))->viewData('stats');

        $this->assertSame(1, $stats['low_stock']);
        $this->assertSame(0, $stats['out_of_stock']);
    }

    public function test_warehouse_receipts_today_ignores_cancelled_receipts(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        StockReceipt::factory()->for($shop)->create([
            'created_by' => $warehouse->id,
            'posted_by' => $warehouse->id,
            'status' => StockReceipt::STATUS_POSTED,
            'received_at' => now(),
        ]);
        StockReceipt::factory()->for($shop)->create([
            'created_by' => $warehouse->id,
            'posted_by' => $warehouse->id,
            'status' => StockReceipt::STATUS_CANCELLED,
            'received_at' => now(),
            'cancelled_at' => now(),
            'cancelled_by' => $warehouse->id,
        ]);

        $this->actingAs($warehouse);

        $stats = $this->get(route('warehouse.dashboard'))->viewData('stats');

        $this->assertSame(1, $stats['receipts_today']);
    }

    public function test_warehouse_can_create_product_with_variants(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $this->actingAs($warehouse);

        $response = $this->post(route('warehouse.products.store'), [
            'name' => 'Futbolka',
            'sku' => 'ftb-001',
            'category' => 'Kiyim',
            'sale_price' => 150000,
            'variants' => [
                ['size' => 'M', 'color' => 'Oq', 'barcode' => '', 'reorder_level' => 3],
                ['size' => 'L', 'color' => 'Oq', 'barcode' => '', 'reorder_level' => 0],
            ],
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('products', [
            'shop_id' => $shop->id,
            'name' => 'Futbolka',
            'sku' => 'FTB-001',
        ]);

        $this->assertDatabaseCount('product_variants', 2);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => Product::where('sku', 'FTB-001')->first()->id,
            'size' => 'M',
            'color' => 'Oq',
            'reorder_level' => 3,
        ]);
    }

    public function test_product_sku_must_be_unique_within_shop(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        Product::factory()->for($shop)->create(['sku' => 'DUP-1']);

        $this->actingAs($warehouse);

        $this->post(route('warehouse.products.store'), [
            'name' => 'Dublikat',
            'sku' => 'dup-1',
            'sale_price' => 1000,
            'variants' => [['size' => 'M']],
        ])->assertSessionHasErrors(['sku']);
    }

    public function test_product_size_must_be_standard_option(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $this->actingAs($warehouse);

        $this->post(route('warehouse.products.store'), [
            'name' => 'Nostandart',
            'sku' => 'NST-1',
            'sale_price' => 1000,
            'variants' => [['size' => 'XXXL']],
        ])->assertSessionHasErrors(['variants.0.size']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_duplicate_size_color_within_product_is_rejected(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $this->actingAs($warehouse);

        $this->post(route('warehouse.products.store'), [
            'name' => 'Duplikat',
            'sku' => 'DUP-2',
            'sale_price' => 1000,
            'variants' => [
                ['size' => 'M', 'color' => 'Qora'],
                ['size' => 'm', 'color' => 'QORA'],
            ],
        ])->assertSessionHasErrors(['variants']);
    }

    public function test_receipt_increases_stock_and_records_movement(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $product = Product::factory()->for($shop)->create(['sale_price' => 100000]);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create(['stock_on_hand' => 2]);

        $this->actingAs($warehouse);

        $response = $this->post(route('warehouse.receipts.store'), [
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 10],
                ['variant_id' => $variant->id, 'quantity' => 3],
            ],
            'notes' => 'Birinchi partiya',
            'idempotency_key' => 'receipt-key-1',
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertSame(15, $variant->fresh()->stock_on_hand);
        $this->assertDatabaseCount('stock_receipts', 1);
        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovement::TYPE_RECEIPT,
            'quantity_delta' => 13,
            'stock_after' => 15,
        ]);
    }

    public function test_receipt_requires_positive_quantity(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $variant = ProductVariant::factory()->for($shop)->create();

        $this->actingAs($warehouse);

        $this->post(route('warehouse.receipts.store'), [
            'items' => [['variant_id' => $variant->id, 'quantity' => 0]],
            'idempotency_key' => 'receipt-key-quantity',
        ])->assertSessionHasErrors(['items.0.quantity']);
    }

    public function test_receipt_requires_idempotency_key(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $variant = ProductVariant::factory()->for($shop)->create(['stock_on_hand' => 2]);

        $this->actingAs($warehouse);

        $this->post(route('warehouse.receipts.store'), [
            'items' => [['variant_id' => $variant->id, 'quantity' => 5]],
        ])->assertSessionHasErrors(['idempotency_key']);

        $this->assertSame(2, $variant->fresh()->stock_on_hand);
        $this->assertDatabaseCount('stock_receipts', 0);
    }

    public function test_receipt_with_same_idempotency_key_is_not_duplicated(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $product = Product::factory()->for($shop)->create(['sale_price' => 100000]);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create(['stock_on_hand' => 2]);

        $this->actingAs($warehouse);

        $payload = [
            'items' => [['variant_id' => $variant->id, 'quantity' => 5]],
            'idempotency_key' => 'receipt-key-same',
        ];

        $first = $this->postJson(route('warehouse.receipts.store'), $payload);
        $first->assertOk();
        $receiptNo = $first->json('receipt.receipt_no');

        $second = $this->postJson(route('warehouse.receipts.store'), $payload);
        $second->assertOk();
        $second->assertJsonPath('receipt.receipt_no', $receiptNo);

        $this->assertSame(7, $variant->fresh()->stock_on_hand);
        $this->assertDatabaseCount('stock_receipts', 1);
        $this->assertSame(1, InventoryMovement::query()->where('movement_type', InventoryMovement::TYPE_RECEIPT)->count());
    }

    public function test_cross_shop_variant_cannot_be_received(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $foreignVariant = ProductVariant::factory()->for($otherShop)->create();

        $this->actingAs($warehouse);

        $this->post(route('warehouse.receipts.store'), [
            'items' => [['variant_id' => $foreignVariant->id, 'quantity' => 5]],
            'idempotency_key' => 'receipt-key-cross',
        ])->assertSessionHasErrors(['items.0.variant_id']);
    }

    public function test_cashier_cannot_access_warehouse(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);

        $this->actingAs($cashier);

        $this->get(route('warehouse.dashboard'))->assertStatus(403);
        $this->post(route('warehouse.products.store'), [
            'name' => 'X',
            'sku' => 'X-1',
            'sale_price' => 100,
            'variants' => [['size' => 'M']],
        ])->assertStatus(403);
    }

    public function test_cross_shop_product_cannot_be_updated(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $foreignProduct = Product::factory()->for($otherShop)->create();

        $this->actingAs($warehouse);

        $this->post(route('warehouse.products.update', $foreignProduct), ['is_active' => 0])
            ->assertStatus(404);
    }
}
