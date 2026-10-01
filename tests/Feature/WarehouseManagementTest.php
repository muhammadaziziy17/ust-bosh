<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_search_keeps_inventory_tab_active(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        Product::factory()->for($shop)->create(['name' => 'Ko\'ylak', 'sku' => 'KOY-1']);

        $this->actingAs($warehouse);

        $this->get(route('warehouse.dashboard', ['q' => 'Ko\'ylak', 'tab' => 'inventory']))
            ->assertOk()
            ->assertSee('Ko\'ylak')
            ->assertSee('data-wh-tab="inventory" class="mt-6"', false)
            ->assertSee('data-wh-tab="receipts" class="mt-6 hidden"', false);
    }

    public function test_movements_tab_lists_stock_changes(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $product = Product::factory()->for($shop)->create(['name' => 'Shim']);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create(['stock_on_hand' => 5]);

        InventoryMovement::create([
            'shop_id' => $shop->id,
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovement::TYPE_RECEIPT,
            'quantity_delta' => 5,
            'stock_after' => 5,
            'reference_type' => StockReceipt::class,
            'reference_id' => 1,
            'movement_key' => 'receipt-1-'.$variant->id,
            'actor_id' => $warehouse->id,
            'created_at' => now(),
        ]);

        $this->actingAs($warehouse);

        $this->get(route('warehouse.dashboard', ['tab' => 'movements']))
            ->assertOk()
            ->assertSee('Shim')
            ->assertSee(__('warehouse.movement_receipt'));
    }

    public function test_product_edit_data_lists_variants(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $product = Product::factory()->for($shop)->create(['name' => 'Kurtka']);
        ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'L',
            'color' => 'Bej',
            'barcode' => '4812345678901',
            'reorder_level' => 4,
        ]);

        $this->actingAs($warehouse);

        $this->getJson(route('warehouse.products.show', $product))
            ->assertOk()
            ->assertJsonPath('product.variants.0.size', 'L')
            ->assertJsonPath('product.variants.0.reorder_level', 4)
            ->assertJsonPath('product.variants.0.barcode', '4812345678901');
    }

    public function test_cross_shop_product_edit_data_is_not_found(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $foreignProduct = Product::factory()->for($otherShop)->create();

        $this->actingAs($warehouse);

        $this->getJson(route('warehouse.products.show', $foreignProduct))->assertNotFound();
    }

    public function test_product_search_escapes_like_wildcards(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        Product::factory()->for($shop)->create(['name' => "Ko'ylak", 'sku' => 'KOY-1']);

        $this->actingAs($warehouse);

        $this->get(route('warehouse.dashboard', ['q' => '%']))
            ->assertOk()
            ->assertDontSee("Ko'ylak");
    }

    public function test_product_form_submits_raw_price_value(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $this->actingAs($warehouse);

        $this->get(route('warehouse.dashboard'))
            ->assertOk()
            ->assertSee('data-price-input', false)
            ->assertSee('id="p-price-value" name="sale_price"', false)
            ->assertSee('id="e-price-value" name="sale_price"', false);
    }

    public function test_product_form_sends_inactive_value_when_checkbox_is_unchecked(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $this->actingAs($warehouse);

        $this->get(route('warehouse.dashboard'))
            ->assertOk()
            ->assertSee('<input type="hidden" name="is_active" value="0" />', false);
    }

    public function test_warehouse_can_deactivate_product_and_variant(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $product = Product::factory()->for($shop)->create(['name' => 'Kurtka', 'sku' => 'KUR-1']);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create(['size' => 'M']);

        $this->actingAs($warehouse);

        $this->post(route('warehouse.products.update', $product), [
            'name' => 'Kurtka',
            'sku' => 'KUR-1',
            'sale_price' => 100000,
            'is_active' => 0,
            'variants' => [
                ['id' => $variant->id, 'size' => 'M', 'is_active' => 0],
            ],
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertFalse($product->fresh()->is_active);
        $this->assertFalse($variant->fresh()->is_active);
    }

    public function test_warehouse_can_update_product_and_variants(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $product = Product::factory()->for($shop)->create(['name' => 'Eski nom', 'sku' => 'ESKI-1']);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'M',
            'color' => 'Oq',
            'reorder_level' => 1,
        ]);

        $this->actingAs($warehouse);

        $response = $this->post(route('warehouse.products.update', $product), [
            'name' => 'Yangi nom',
            'sku' => 'yangi-1',
            'category' => 'Kiyim',
            'sale_price' => 180000,
            'is_active' => 1,
            'variants' => [
                ['id' => $variant->id, 'size' => 'M', 'color' => 'Oq', 'reorder_level' => 5, 'is_active' => 1],
                ['size' => 'L', 'color' => 'Qora', 'reorder_level' => 2, 'is_active' => 1],
            ],
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Yangi nom',
            'sku' => 'YANGI-1',
            'category' => 'Kiyim',
            'sale_price' => 180000,
        ]);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'reorder_level' => 5,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'size' => 'L',
            'color' => 'Qora',
            'reorder_level' => 2,
        ]);

        $this->assertSame(2, $product->variants()->count());
    }

    public function test_update_rejects_variant_from_another_shop(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $product = Product::factory()->for($shop)->create();
        $foreignVariant = ProductVariant::factory()->for($otherShop)->create();

        $this->actingAs($warehouse);

        $this->post(route('warehouse.products.update', $product), [
            'name' => 'Nom',
            'sku' => 'NOM-1',
            'sale_price' => 1000,
            'variants' => [
                ['id' => $foreignVariant->id, 'size' => 'M'],
            ],
        ])->assertNotFound();
    }

    public function test_update_rejects_duplicate_size_color(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $product = Product::factory()->for($shop)->create();

        $this->actingAs($warehouse);

        $this->post(route('warehouse.products.update', $product), [
            'name' => 'Nom',
            'sku' => 'NOM-2',
            'sale_price' => 1000,
            'variants' => [
                ['size' => 'M', 'color' => 'Qora'],
                ['size' => 'm', 'color' => 'QORA'],
            ],
        ])->assertSessionHasErrors(['variants']);
    }

    public function test_update_rejects_non_standard_size(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $product = Product::factory()->for($shop)->create(['name' => 'Eski nom', 'sku' => 'ESKI-9']);

        $this->actingAs($warehouse);

        $this->post(route('warehouse.products.update', $product), [
            'name' => 'Yangi nom',
            'sku' => 'YANGI-9',
            'sale_price' => 1000,
            'variants' => [['size' => 'XXXL']],
        ])->assertSessionHasErrors(['variants.0.size']);

        $this->assertSame('Eski nom', $product->fresh()->name);
        $this->assertSame(0, $product->variants()->count());
    }

    public function test_receipt_page_shows_items_and_status(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $product = Product::factory()->for($shop)->create(['name' => 'Kurtka']);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'XL',
            'color' => 'Bej',
        ]);

        $receipt = StockReceipt::factory()->for($shop)->create([
            'receipt_no' => 'K-TEST0001',
            'created_by' => $warehouse->id,
            'posted_by' => $warehouse->id,
        ]);

        StockReceiptItem::create([
            'shop_id' => $shop->id,
            'stock_receipt_id' => $receipt->id,
            'product_variant_id' => $variant->id,
            'quantity' => 4,
        ]);

        $this->actingAs($warehouse);

        $this->get(route('warehouse.receipts.show', $receipt))
            ->assertOk()
            ->assertSee('K-TEST0001')
            ->assertSee('Kurtka')
            ->assertSee('XL · Bej', false)
            ->assertSee(__('warehouse.receipt_status_posted'));
    }

    public function test_cross_shop_receipt_is_not_found(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $foreignReceipt = StockReceipt::factory()->for($otherShop)->create();

        $this->actingAs($warehouse);

        $this->get(route('warehouse.receipts.show', $foreignReceipt))->assertNotFound();
        $this->post(route('warehouse.receipts.cancel', $foreignReceipt))->assertNotFound();
    }

    public function test_cancel_receipt_returns_stock_and_records_movement(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $product = Product::factory()->for($shop)->create();
        $variant = ProductVariant::factory()->for($shop)->for($product)->create(['stock_on_hand' => 8]);

        $receipt = StockReceipt::factory()->for($shop)->create([
            'created_by' => $warehouse->id,
            'posted_by' => $warehouse->id,
        ]);

        StockReceiptItem::create([
            'shop_id' => $shop->id,
            'stock_receipt_id' => $receipt->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ]);

        $this->actingAs($warehouse);

        $response = $this->postJson(route('warehouse.receipts.cancel', $receipt), [
            'reason' => 'Xato kiritilgan',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertSame(5, $variant->fresh()->stock_on_hand);

        $this->assertDatabaseHas('stock_receipts', [
            'id' => $receipt->id,
            'status' => StockReceipt::STATUS_CANCELLED,
            'cancelled_by' => $warehouse->id,
            'cancel_reason' => 'Xato kiritilgan',
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovement::TYPE_RECEIPT_CANCEL,
            'quantity_delta' => -3,
            'stock_after' => 5,
        ]);
    }

    public function test_cancel_receipt_is_blocked_when_items_already_sold(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $product = Product::factory()->for($shop)->create();
        $variant = ProductVariant::factory()->for($shop)->for($product)->create(['stock_on_hand' => 2]);

        $receipt = StockReceipt::factory()->for($shop)->create([
            'created_by' => $warehouse->id,
            'posted_by' => $warehouse->id,
        ]);

        StockReceiptItem::create([
            'shop_id' => $shop->id,
            'stock_receipt_id' => $receipt->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ]);

        $this->actingAs($warehouse);

        $this->post(route('warehouse.receipts.cancel', $receipt), ['reason' => 'Bekor'])
            ->assertSessionHasErrors(['items']);

        $this->assertSame(2, $variant->fresh()->stock_on_hand);
        $this->assertSame(StockReceipt::STATUS_POSTED, $receipt->fresh()->status);
        $this->assertDatabaseMissing('inventory_movements', [
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovement::TYPE_RECEIPT_CANCEL,
        ]);
    }

    public function test_cancelled_receipt_cannot_be_cancelled_again(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $receipt = StockReceipt::factory()->for($shop)->create([
            'status' => StockReceipt::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $warehouse->id,
            'created_by' => $warehouse->id,
            'posted_by' => $warehouse->id,
        ]);

        $this->actingAs($warehouse);

        $this->postJson(route('warehouse.receipts.cancel', $receipt))
            ->assertStatus(409);
    }

    public function test_cashier_cannot_use_warehouse_actions(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);

        $product = Product::factory()->for($shop)->create();
        $receipt = StockReceipt::factory()->for($shop)->create();

        $this->actingAs($cashier);

        $this->get(route('warehouse.products.show', $product))->assertForbidden();
        $this->get(route('warehouse.receipts.show', $receipt))->assertForbidden();
        $this->post(route('warehouse.receipts.cancel', $receipt))->assertForbidden();
        $this->get(route('warehouse.receipts.create'))->assertForbidden();
        $this->getJson(route('warehouse.variants.search'))->assertForbidden();
    }

    public function test_receipt_create_page_guides_when_no_variants_exist(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $this->actingAs($warehouse);

        $this->get(route('warehouse.receipts.create'))
            ->assertOk()
            ->assertSee(__('warehouse.no_variants_yet'))
            ->assertDontSee('id="receipt-search"', false);
    }

    public function test_receipt_create_page_shows_search_when_variants_exist(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $product = Product::factory()->for($shop)->create(['name' => 'Ko\'ylak']);
        ProductVariant::factory()->for($shop)->for($product)->create(['size' => 'M']);

        $this->actingAs($warehouse);

        $this->get(route('warehouse.receipts.create'))
            ->assertOk()
            ->assertSee('id="receipt-search"', false)
            ->assertSee('id="receipt-submit-btn"', false)
            ->assertSee('name="idempotency_key"', false)
            ->assertSee('ustbosh_receipt_idempotency_key', false)
            ->assertDontSee(__('warehouse.no_variants_yet'));
    }

    public function test_variant_search_finds_by_name_sku_and_barcode(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        $product = Product::factory()->for($shop)->create(['name' => 'Ko\'ylak', 'sku' => 'KOY-1']);
        ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'L',
            'color' => null,
            'barcode' => '4812345678901',
            'stock_on_hand' => 4,
        ]);

        $this->actingAs($warehouse);

        $this->getJson(route('warehouse.variants.search', ['q' => 'ko\'ylak']))
            ->assertOk()
            ->assertJsonPath('variants.0.product_name', 'Ko\'ylak')
            ->assertJsonPath('variants.0.stock', 4);

        $this->getJson(route('warehouse.variants.search', ['q' => 'KOY-1']))
            ->assertOk()
            ->assertJsonCount(1, 'variants');

        $this->getJson(route('warehouse.variants.search', ['q' => '4812345678901']))
            ->assertOk()
            ->assertJsonCount(1, 'variants')
            ->assertJsonPath('variants.0.label', 'L');
    }

    public function test_variant_search_excludes_other_shops_variants(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = $this->makeWarehouse($shop);

        Product::factory()->for($otherShop)->create(['name' => 'Boshqa do\'kon mahsuloti']);

        $this->actingAs($warehouse);

        $this->getJson(route('warehouse.variants.search', ['q' => 'Boshqa']))
            ->assertOk()
            ->assertJsonCount(0, 'variants');
    }

    private function makeWarehouse(Shop $shop): User
    {
        return User::factory()->create([
            'role' => User::ROLE_WAREHOUSE,
            'shop_id' => $shop->id,
        ]);
    }
}
