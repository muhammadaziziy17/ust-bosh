<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminShopManagementTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsAdmin(): User
    {
        $admin = User::factory()->create([
            'email' => 'admin_test@ustbosh.uz',
            'password' => 'secret123',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->post('/admin/login', [
            'login' => $admin->email,
            'password' => 'secret123',
        ]);

        return $admin;
    }

    private function makeShop(array $attributes = []): Shop
    {
        return Shop::factory()->create(array_merge([
            'status' => Shop::STATUS_ACTIVE,
        ], $attributes));
    }

    public function test_admin_can_open_shop_detail(): void
    {
        $shop = $this->makeShop(['name' => 'Kurtka Markazi', 'phone' => '+998901112233']);
        $owner = User::factory()->create([
            'name' => 'Ali Egasi',
            'email' => 'ali@shop.uz',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $this->loginAsAdmin();

        $this->get(route('admin.shops.show', $shop))
            ->assertOk()
            ->assertSee('Kurtka Markazi')
            ->assertSee('+998901112233')
            ->assertSee($owner->email);
    }

    public function test_customer_cannot_open_shop_detail(): void
    {
        $shop = $this->makeShop();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'shop_id' => $shop->id]);

        $this->actingAs($owner);

        $this->get(route('admin.shops.show', $shop))->assertForbidden();
    }

    public function test_admin_can_update_shop_profile_and_it_is_audited(): void
    {
        $shop = $this->makeShop(['name' => 'Eski nom', 'phone' => null]);
        $admin = $this->loginAsAdmin();

        $this->post(route('admin.shops.update', $shop), [
            'name' => 'Yangi nom',
            'phone' => '+998901234567',
        ])->assertRedirect();

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'name' => 'Yangi nom',
            'phone' => '+998901234567',
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $admin->id,
            'shop_id' => $shop->id,
            'action' => 'shop.updated',
        ]);
    }

    public function test_shop_profile_update_validates_phone(): void
    {
        $shop = $this->makeShop(['name' => 'Asl nom']);
        $this->loginAsAdmin();

        $this->post(route('admin.shops.update', $shop), [
            'name' => 'Nom',
            'phone' => 'not-a-phone',
        ])->assertSessionHasErrors(['phone']);

        $this->assertSame('Asl nom', $shop->fresh()->name);
        $this->assertDatabaseMissing('admin_audit_logs', ['action' => 'shop.updated']);
    }

    public function test_shop_status_change_is_audited_and_idempotent(): void
    {
        $shop = $this->makeShop();
        $admin = $this->loginAsAdmin();

        $this->post(route('admin.shops.suspend', $shop))->assertRedirect();
        $this->post(route('admin.shops.suspend', $shop))->assertRedirect();

        $this->assertSame(Shop::STATUS_SUSPENDED, $shop->fresh()->status);
        $this->assertSame(2, AdminAuditLog::query()
            ->where('action', 'shop.suspended')
            ->where('shop_id', $shop->id)
            ->where('actor_id', $admin->id)
            ->count());

        $this->post(route('admin.shops.activate', $shop))->assertRedirect();
        $this->assertSame(Shop::STATUS_ACTIVE, $shop->fresh()->status);
    }

    public function test_admin_can_deactivate_and_activate_shop_user(): void
    {
        $shop = $this->makeShop();
        $member = User::factory()->create([
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
        ]);
        $version = $member->auth_version;

        $this->loginAsAdmin();

        $this->post(route('admin.shops.users.toggle', [$shop, $member]))->assertRedirect();

        $this->assertFalse($member->fresh()->is_active);
        $this->assertSame($version + 1, $member->fresh()->auth_version);

        $this->assertDatabaseHas('admin_audit_logs', [
            'shop_id' => $shop->id,
            'action' => 'user.deactivated',
            'subject_id' => $member->id,
        ]);

        $this->post(route('admin.shops.users.toggle', [$shop, $member]))->assertRedirect();

        $this->assertTrue($member->fresh()->is_active);
        $this->assertDatabaseHas('admin_audit_logs', [
            'shop_id' => $shop->id,
            'action' => 'user.activated',
            'subject_id' => $member->id,
        ]);
    }

    public function test_admin_cannot_manage_user_of_another_shop(): void
    {
        $shop = $this->makeShop();
        $otherShop = $this->makeShop();
        $foreign = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $otherShop->id]);

        $this->loginAsAdmin();

        $this->post(route('admin.shops.users.toggle', [$shop, $foreign]))->assertNotFound();
        $this->post(route('admin.shops.users.reset-password', [$shop, $foreign]))->assertNotFound();

        $this->assertTrue($foreign->fresh()->is_active);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_admin_can_send_password_reset_to_shop_user(): void
    {
        Notification::fake();

        $shop = $this->makeShop();
        $member = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);

        $this->loginAsAdmin();

        $this->post(route('admin.shops.users.reset-password', [$shop, $member]))
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentTo($member, ResetPassword::class);

        $this->assertDatabaseHas('admin_audit_logs', [
            'shop_id' => $shop->id,
            'action' => 'user.password_reset',
            'subject_id' => $member->id,
        ]);
    }

    public function test_admin_can_adjust_variant_stock_with_audit_and_movement(): void
    {
        $shop = $this->makeShop();
        $product = Product::factory()->for($shop)->create(['name' => 'Kurtka']);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'M',
            'stock_on_hand' => 3,
        ]);

        $this->loginAsAdmin();

        $this->post(route('admin.shops.variants.adjust', [$shop, $variant]), [
            'delta' => 5,
            'reason' => 'Inventarizatsiya tuzatishi',
        ])->assertRedirect();

        $this->assertSame(8, $variant->fresh()->stock_on_hand);

        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovement::TYPE_ADJUSTMENT,
            'quantity_delta' => 5,
            'stock_after' => 8,
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'shop_id' => $shop->id,
            'action' => 'variant.stock_adjusted',
            'subject_id' => $variant->id,
            'reason' => 'Inventarizatsiya tuzatishi',
        ]);
    }

    public function test_adjustment_cannot_make_stock_negative(): void
    {
        $shop = $this->makeShop();
        $variant = ProductVariant::factory()->for($shop)->create(['stock_on_hand' => 2]);

        $this->loginAsAdmin();

        $this->post(route('admin.shops.variants.adjust', [$shop, $variant]), [
            'delta' => -5,
            'reason' => 'Xato tuzatish',
        ])->assertSessionHasErrors(['delta']);

        $this->assertSame(2, $variant->fresh()->stock_on_hand);
        $this->assertDatabaseMissing('inventory_movements', ['product_variant_id' => $variant->id]);
        $this->assertDatabaseMissing('admin_audit_logs', ['action' => 'variant.stock_adjusted']);
    }

    public function test_adjustment_requires_reason(): void
    {
        $shop = $this->makeShop();
        $variant = ProductVariant::factory()->for($shop)->create(['stock_on_hand' => 2]);

        $this->loginAsAdmin();

        $this->post(route('admin.shops.variants.adjust', [$shop, $variant]), ['delta' => 1])
            ->assertSessionHasErrors(['reason']);

        $this->assertSame(2, $variant->fresh()->stock_on_hand);
    }

    public function test_adjustment_cannot_target_another_shops_variant(): void
    {
        $shop = $this->makeShop();
        $otherShop = $this->makeShop();
        $foreign = ProductVariant::factory()->for($otherShop)->create(['stock_on_hand' => 4]);

        $this->loginAsAdmin();

        $this->post(route('admin.shops.variants.adjust', [$shop, $foreign]), [
            'delta' => 1,
            'reason' => 'Tegishli emas',
        ])->assertNotFound();

        $this->assertSame(4, $foreign->fresh()->stock_on_hand);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_admin_can_void_shop_sale_and_stock_is_returned(): void
    {
        $shop = $this->makeShop();
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);
        $product = Product::factory()->for($shop)->create(['name' => 'Futbolka', 'sale_price' => 120000]);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'M',
            'stock_on_hand' => 8,
        ]);

        $sale = Sale::factory()->for($shop)->create([
            'cashier_id' => $cashier->id,
            'status' => Sale::STATUS_COMPLETED,
            'payment_method' => Sale::PAYMENT_CASH,
            'subtotal' => 240000,
            'total' => 240000,
        ]);

        SaleItem::factory()->for($sale)->create([
            'shop_id' => $shop->id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => 'Futbolka',
            'sku_snapshot' => $product->sku,
            'size_snapshot' => 'M',
            'color_snapshot' => null,
            'unit_price' => 120000,
            'quantity' => 2,
            'line_total' => 240000,
        ]);

        $this->loginAsAdmin();

        $this->post(route('admin.shops.sales.void', [$shop, $sale]), ['reason' => 'Xato razmer'])
            ->assertRedirect();

        $this->assertSame(Sale::STATUS_VOIDED, $sale->fresh()->status);
        $this->assertSame(10, $variant->fresh()->stock_on_hand);

        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovement::TYPE_SALE_VOID,
            'quantity_delta' => 2,
            'stock_after' => 10,
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'shop_id' => $shop->id,
            'action' => 'sale.voided',
            'subject_id' => $sale->id,
            'reason' => 'Xato razmer',
        ]);
    }

    public function test_admin_void_requires_reason(): void
    {
        $shop = $this->makeShop();
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);
        $sale = Sale::factory()->for($shop)->create([
            'cashier_id' => $cashier->id,
            'status' => Sale::STATUS_COMPLETED,
            'payment_method' => Sale::PAYMENT_CASH,
        ]);

        $this->loginAsAdmin();

        $this->post(route('admin.shops.sales.void', [$shop, $sale]))
            ->assertSessionHasErrors(['reason']);

        $this->assertSame(Sale::STATUS_COMPLETED, $sale->fresh()->status);
    }

    public function test_admin_cannot_void_another_shops_sale(): void
    {
        $shop = $this->makeShop();
        $otherShop = $this->makeShop();
        $otherCashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $otherShop->id]);
        $foreignSale = Sale::factory()->for($otherShop)->create([
            'cashier_id' => $otherCashier->id,
            'status' => Sale::STATUS_COMPLETED,
            'payment_method' => Sale::PAYMENT_CASH,
        ]);

        $this->loginAsAdmin();

        $this->post(route('admin.shops.sales.void', [$shop, $foreignSale]), ['reason' => 'Tegishli emas'])
            ->assertNotFound();

        $this->assertSame(Sale::STATUS_COMPLETED, $foreignSale->fresh()->status);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_admin_void_is_rejected_for_already_voided_sale(): void
    {
        $shop = $this->makeShop();
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);
        $sale = Sale::factory()->for($shop)->create([
            'cashier_id' => $cashier->id,
            'status' => Sale::STATUS_VOIDED,
            'payment_method' => Sale::PAYMENT_CASH,
            'voided_at' => now(),
        ]);

        $this->loginAsAdmin();

        $this->postJson(route('admin.shops.sales.void', [$shop, $sale]), ['reason' => 'Yana bekor'])
            ->assertStatus(409);
    }

    public function test_admin_can_update_shop_product_and_it_is_audited(): void
    {
        $shop = $this->makeShop();
        $product = Product::factory()->for($shop)->create([
            'name' => 'Eski nom',
            'sku' => 'ESKI-1',
            'sale_price' => 100000,
            'is_active' => true,
        ]);

        $this->loginAsAdmin();

        $this->get(route('admin.shops.products.edit', [$shop, $product]))
            ->assertOk()
            ->assertSee('ESKI-1');

        $this->post(route('admin.shops.products.update', [$shop, $product]), [
            'name' => 'Yangi nom',
            'sku' => 'yangi-1',
            'category' => 'Kiyim',
            'sale_price' => 180000,
            'is_active' => 0,
        ])->assertRedirect(route('admin.shops.show', $shop));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Yangi nom',
            'sku' => 'YANGI-1',
            'category' => 'Kiyim',
            'sale_price' => 180000,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'shop_id' => $shop->id,
            'action' => 'product.updated',
            'subject_id' => $product->id,
        ]);
    }

    public function test_admin_product_update_rejects_duplicate_sku(): void
    {
        $shop = $this->makeShop();
        Product::factory()->for($shop)->create(['sku' => 'DUP-1']);
        $product = Product::factory()->for($shop)->create(['sku' => 'OWN-1']);

        $this->loginAsAdmin();

        $this->post(route('admin.shops.products.update', [$shop, $product]), [
            'name' => 'Nom',
            'sku' => 'dup-1',
            'sale_price' => 1000,
        ])->assertSessionHasErrors(['sku']);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'sku' => 'OWN-1']);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_admin_cannot_edit_another_shops_product(): void
    {
        $shop = $this->makeShop();
        $otherShop = $this->makeShop();
        $foreign = Product::factory()->for($otherShop)->create(['name' => 'Boshqa tovar', 'sku' => 'FOR-1']);

        $this->loginAsAdmin();

        $this->get(route('admin.shops.products.edit', [$shop, $foreign]))->assertNotFound();
        $this->post(route('admin.shops.products.update', [$shop, $foreign]), [
            'name' => 'X',
            'sku' => 'X-1',
            'sale_price' => 1000,
        ])->assertNotFound();

        $this->assertDatabaseHas('products', ['id' => $foreign->id, 'name' => 'Boshqa tovar']);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_admin_can_view_audit_log(): void
    {
        $shop = $this->makeShop();

        $this->loginAsAdmin();

        $this->post(route('admin.shops.update', $shop), ['name' => 'Audit nomi', 'phone' => null]);

        $this->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('shop.updated')
            ->assertSee('Audit nomi');
    }

    public function test_customer_cannot_view_audit_log(): void
    {
        $shop = $this->makeShop();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'shop_id' => $shop->id]);

        $this->actingAs($owner);

        $this->get(route('admin.audit.index'))->assertForbidden();
    }

    public function test_platform_overview_shows_shop_counters(): void
    {
        Shop::factory()->count(2)->create(['status' => Shop::STATUS_ACTIVE]);
        Shop::factory()->create(['status' => Shop::STATUS_SUSPENDED]);
        User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->loginAsAdmin();

        $response = $this->get(route('admin.dashboard'));
        $response->assertOk()->assertSee(__('admin.stat_shops_total'));

        $platformStats = $response->viewData('platformStats');

        $this->assertSame(3, $platformStats['shops_total']);
        $this->assertSame(2, $platformStats['shops_active']);
        $this->assertSame(1, $platformStats['shops_suspended']);
        $this->assertSame(1, $platformStats['users_total']);
    }

    public function test_shop_list_can_be_filtered_by_status(): void
    {
        $this->makeShop(['name' => 'Faol Markaz']);
        $this->makeShop(['name' => 'Toxtagan Markaz', 'status' => Shop::STATUS_SUSPENDED]);

        $this->loginAsAdmin();

        $this->get('/admin/dashboard?shop_status=suspended')
            ->assertOk()
            ->assertSee('Toxtagan Markaz')
            ->assertDontSee('Faol Markaz');
    }
}
