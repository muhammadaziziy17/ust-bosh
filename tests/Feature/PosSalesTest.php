<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_history_lists_only_shop_sales(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);
        $otherCashier = $this->makeCashier($otherShop);

        $this->makeSale($shop, $cashier, ['sale_no' => 'S-BIZNING01']);
        $this->makeSale($otherShop, $otherCashier, ['sale_no' => 'S-BOSHQA001']);

        $this->actingAs($cashier);

        $this->get(route('pos.sales.index'))
            ->assertOk()
            ->assertSee('S-BIZNING01')
            ->assertDontSee('S-BOSHQA001');
    }

    public function test_receipt_page_shows_items_and_totals(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        [$sale] = $this->makeSaleWithItem($shop, $cashier, quantity: 2, unitPrice: 50000);

        $this->actingAs($cashier);

        $this->get(route('pos.sales.show', $sale))
            ->assertOk()
            ->assertSee('Futbolka')
            ->assertSee('M · Qora', false)
            ->assertSee(format_som(100000), false);
    }

    public function test_cross_shop_receipt_is_not_found(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);
        $otherCashier = $this->makeCashier($otherShop);

        $foreignSale = $this->makeSale($otherShop, $otherCashier);

        $this->actingAs($cashier);

        $this->get(route('pos.sales.show', $foreignSale))->assertNotFound();
    }

    public function test_cashier_can_void_own_sale_and_stock_is_returned(): void
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

        $saleId = $this->postJson(route('pos.sales.store'), [
            'payment_method' => Sale::PAYMENT_CASH,
            'idempotency_key' => 'key-void',
            'items' => [['variant_id' => $variant->id, 'quantity' => 3]],
        ])->assertOk()->json('sale.id');

        $sale = Sale::findOrFail($saleId);
        $this->assertSame(7, $variant->fresh()->stock_on_hand);

        $response = $this->postJson(route('pos.sales.void', $sale), ['reason' => 'Xato razmer tanlandi']);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertSame(Sale::STATUS_VOIDED, $sale->fresh()->status);
        $this->assertNotNull($sale->fresh()->voided_at);
        $this->assertSame($cashier->id, $sale->fresh()->voided_by);
        $this->assertSame('Xato razmer tanlandi', $sale->fresh()->void_reason);
        $this->assertSame(10, $variant->fresh()->stock_on_hand);

        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovement::TYPE_SALE_VOID,
            'quantity_delta' => 3,
            'stock_after' => 10,
        ]);
    }

    public function test_void_requires_reason(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        [$sale] = $this->makeSaleWithItem($shop, $cashier);

        $this->actingAs($cashier);

        $this->postJson(route('pos.sales.void', $sale))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->assertSame(Sale::STATUS_COMPLETED, $sale->fresh()->status);
        $this->assertDatabaseMissing('inventory_movements', [
            'movement_type' => InventoryMovement::TYPE_SALE_VOID,
        ]);
    }

    public function test_cashier_cannot_void_another_cashiers_sale(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);
        $otherCashier = $this->makeCashier($shop);

        $sale = $this->makeSale($shop, $otherCashier);

        $this->actingAs($cashier);

        $this->postJson(route('pos.sales.void', $sale), ['reason' => 'Xato'])
            ->assertForbidden();

        $this->assertSame(Sale::STATUS_COMPLETED, $sale->fresh()->status);
    }

    public function test_voided_sale_cannot_be_voided_again(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $sale = $this->makeSale($shop, $cashier, ['status' => Sale::STATUS_VOIDED, 'voided_at' => now()]);

        $this->actingAs($cashier);

        $this->postJson(route('pos.sales.void', $sale), ['reason' => 'Yana bekor'])
            ->assertStatus(409);
    }

    public function test_cash_checkout_records_received_amount_and_change(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $product = Product::factory()->for($shop)->create(['sale_price' => 120000]);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create(['stock_on_hand' => 5]);

        $this->actingAs($cashier);

        $response = $this->postJson(route('pos.sales.store'), [
            'payment_method' => Sale::PAYMENT_CASH,
            'amount_received' => 500000,
            'idempotency_key' => 'key-cash-change',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        $response->assertOk();

        $sale = Sale::query()->firstOrFail();
        $this->assertSame(500000.0, (float) $sale->amount_received);
        $this->assertSame(380000.0, (float) $sale->change_amount);
        $this->assertSame(380000.0, (float) $response->json('sale.change_amount'));
    }

    public function test_cash_checkout_rejects_amount_below_total(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $product = Product::factory()->for($shop)->create(['sale_price' => 120000]);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create(['stock_on_hand' => 5]);

        $this->actingAs($cashier);

        $this->postJson(route('pos.sales.store'), [
            'payment_method' => Sale::PAYMENT_CASH,
            'amount_received' => 100000,
            'idempotency_key' => 'key-cash-short',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['amount_received']);

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(5, $variant->fresh()->stock_on_hand);
    }

    public function test_receipt_page_autoprints_when_print_requested(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        [$sale] = $this->makeSaleWithItem($shop, $cashier);

        $this->actingAs($cashier);

        $this->get(route('pos.sales.show', ['sale' => $sale, 'print' => 1]))
            ->assertOk()
            ->assertSee('data-autoprint="1"', false);

        $this->get(route('pos.sales.show', $sale))
            ->assertOk()
            ->assertSee('data-autoprint="0"', false);
    }

    public function test_pos_page_offers_print_and_new_sale_actions(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $this->actingAs($cashier);

        $this->get(route('pos.index'))
            ->assertOk()
            ->assertSee(__('pos.print_receipt'))
            ->assertSee(__('pos.new_sale'));
    }

    public function test_owner_cannot_open_sales_history(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'shop_id' => $shop->id]);

        $this->actingAs($owner);

        $this->get(route('pos.sales.index'))->assertForbidden();
    }

    private function makeCashier(Shop $shop): User
    {
        return User::factory()->create([
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeSale(Shop $shop, User $cashier, array $attributes = []): Sale
    {
        return Sale::factory()->for($shop)->create(array_merge([
            'cashier_id' => $cashier->id,
            'status' => Sale::STATUS_COMPLETED,
            'payment_method' => Sale::PAYMENT_CASH,
            'subtotal' => 100000,
            'total' => 100000,
        ], $attributes));
    }

    /**
     * @return array{0: Sale, 1: ProductVariant}
     */
    private function makeSaleWithItem(Shop $shop, User $cashier, int $quantity = 1, float $unitPrice = 100000): array
    {
        $product = Product::factory()->for($shop)->create(['name' => 'Futbolka', 'sale_price' => $unitPrice]);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'M',
            'color' => 'Qora',
            'stock_on_hand' => 10,
        ]);

        $sale = $this->makeSale($shop, $cashier, [
            'subtotal' => $quantity * $unitPrice,
            'total' => $quantity * $unitPrice,
        ]);

        SaleItem::factory()->for($sale)->create([
            'shop_id' => $shop->id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $product->sku,
            'size_snapshot' => $variant->size,
            'color_snapshot' => $variant->color,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'line_total' => $quantity * $unitPrice,
        ]);

        return [$sale, $variant];
    }
}
