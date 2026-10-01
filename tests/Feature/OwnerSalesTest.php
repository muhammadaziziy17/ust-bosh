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

class OwnerSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_all_shop_sales_and_not_other_shops(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $cashierA = $this->makeCashier($shop);
        $cashierB = $this->makeCashier($shop);
        $otherCashier = $this->makeCashier($otherShop);

        $this->makeSale($shop, $cashierA, ['sale_no' => 'S-BIRINCHI01']);
        $this->makeSale($shop, $cashierB, ['sale_no' => 'S-IKKINCHI02']);
        $this->makeSale($otherShop, $otherCashier, ['sale_no' => 'S-BOSHQA0003']);

        $this->actingAs($owner);

        $this->get(route('owner.sales.index'))
            ->assertOk()
            ->assertSee('S-BIRINCHI01')
            ->assertSee('S-IKKINCHI02')
            ->assertDontSee('S-BOSHQA0003');
    }

    public function test_owner_sales_page_filters_by_cashier_and_status(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $cashierA = $this->makeCashier($shop);
        $cashierB = $this->makeCashier($shop);

        $this->makeSale($shop, $cashierA, ['sale_no' => 'S-KASSIR-A01', 'status' => Sale::STATUS_VOIDED, 'voided_at' => now()]);
        $this->makeSale($shop, $cashierB, ['sale_no' => 'S-KASSIR-B02']);

        $this->actingAs($owner);

        $this->get(route('owner.sales.index', ['cashier' => $cashierA->id]))
            ->assertOk()
            ->assertSee('S-KASSIR-A01')
            ->assertDontSee('S-KASSIR-B02');

        $this->get(route('owner.sales.index', ['status' => Sale::STATUS_VOIDED]))
            ->assertOk()
            ->assertSee('S-KASSIR-A01')
            ->assertDontSee('S-KASSIR-B02');
    }

    public function test_owner_can_open_sale_receipt_with_autoprint(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $cashier = $this->makeCashier($shop);

        [$sale] = $this->makeSaleWithItem($shop, $cashier);

        $this->actingAs($owner);

        $this->get(route('owner.sales.show', ['sale' => $sale, 'print' => 1]))
            ->assertOk()
            ->assertSee($sale->sale_no)
            ->assertSee('data-autoprint="1"', false);
    }

    public function test_cross_shop_sale_receipt_is_not_found(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $otherCashier = $this->makeCashier($otherShop);

        $foreignSale = $this->makeSale($otherShop, $otherCashier);

        $this->actingAs($owner);

        $this->get(route('owner.sales.show', $foreignSale))->assertNotFound();
    }

    public function test_owner_void_is_forbidden_when_setting_is_disabled(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $cashier = $this->makeCashier($shop);

        [$sale, $variant] = $this->makeSaleWithItem($shop, $cashier);

        $this->actingAs($owner);

        $this->postJson(route('owner.sales.void', $sale), ['reason' => 'Xato razmer'])
            ->assertForbidden();

        $this->assertSame(Sale::STATUS_COMPLETED, $sale->fresh()->status);
        $this->assertSame(8, $variant->fresh()->stock_on_hand);
        $this->assertDatabaseMissing('inventory_movements', [
            'movement_type' => InventoryMovement::TYPE_SALE_VOID,
        ]);
    }

    public function test_owner_can_void_any_shop_sale_when_enabled(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE, 'owner_can_void_sales' => true]);
        $owner = $this->makeOwner($shop);
        $cashier = $this->makeCashier($shop);

        [$sale, $variant] = $this->makeSaleWithItem($shop, $cashier);

        $this->actingAs($owner);

        $this->postJson(route('owner.sales.void', $sale), ['reason' => 'Xato razmer tanlangan'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(Sale::STATUS_VOIDED, $sale->fresh()->status);
        $this->assertSame($owner->id, $sale->fresh()->voided_by);
        $this->assertSame('Xato razmer tanlangan', $sale->fresh()->void_reason);
        $this->assertSame(10, $variant->fresh()->stock_on_hand);

        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovement::TYPE_SALE_VOID,
            'quantity_delta' => 2,
            'stock_after' => 10,
            'actor_id' => $owner->id,
        ]);
    }

    public function test_owner_can_toggle_void_setting(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);

        $this->actingAs($owner);

        $this->post(route('owner.sales.settings'), ['owner_can_void_sales' => 1])
            ->assertRedirect();

        $this->assertTrue($shop->fresh()->owner_can_void_sales);

        $this->post(route('owner.sales.settings'), ['owner_can_void_sales' => 0])
            ->assertRedirect();

        $this->assertFalse($shop->fresh()->owner_can_void_sales);
    }

    private function makeOwner(Shop $shop): User
    {
        return User::factory()->create([
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);
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
            'subtotal' => 240000,
            'total' => 240000,
        ], $attributes));
    }

    /**
     * Qoldiq sotuvdan keyingi holatni bildiradi (8 dona), bekor qilingach 10 bo'ladi.
     *
     * @return array{0: Sale, 1: ProductVariant}
     */
    private function makeSaleWithItem(Shop $shop, User $cashier): array
    {
        $product = Product::factory()->for($shop)->create(['name' => 'Futbolka oversize', 'sku' => 'FUT-001', 'sale_price' => 120000]);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'L',
            'color' => 'Qora',
            'stock_on_hand' => 8,
        ]);

        $sale = $this->makeSale($shop, $cashier);

        SaleItem::factory()->for($sale)->create([
            'shop_id' => $shop->id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $product->sku,
            'size_snapshot' => $variant->size,
            'color_snapshot' => $variant->color,
            'unit_price' => 120000,
            'quantity' => 2,
            'line_total' => 240000,
        ]);

        return [$sale, $variant];
    }
}
