<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_employee_search_filters_by_name_or_email(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);

        User::factory()->create(['name' => 'Vali Kassir', 'email' => 'vali@shop.uz', 'role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);
        User::factory()->create(['name' => 'Olim Skladchi', 'email' => 'olim@shop.uz', 'role' => User::ROLE_WAREHOUSE, 'shop_id' => $shop->id]);

        $this->actingAs($owner);

        $this->get(route('owner.employees.index', ['q' => 'Vali']))
            ->assertOk()
            ->assertSee('Vali Kassir')
            ->assertDontSee('Olim Skladchi');
    }

    public function test_owner_can_update_employee(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $employee = User::factory()->create([
            'name' => 'Eski Ism',
            'email' => 'eski@shop.uz',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($owner);

        $this->post(route('owner.employees.update', $employee), [
            'name' => 'Yangi Ism',
            'email' => '  YANGI@Shop.uz ',
            'role' => User::ROLE_WAREHOUSE,
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'name' => 'Yangi Ism',
            'email' => 'yangi@shop.uz',
            'role' => User::ROLE_WAREHOUSE,
        ]);
    }

    public function test_owner_cannot_manage_another_shops_employee(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $foreignEmployee = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $otherShop->id]);

        $this->actingAs($owner);

        $this->post(route('owner.employees.update', $foreignEmployee), [
            'name' => 'X',
            'email' => 'x@shop.uz',
            'role' => User::ROLE_CASHIER,
        ])->assertForbidden();

        $this->post(route('owner.employees.toggle', $foreignEmployee))->assertForbidden();
    }

    public function test_owner_cannot_manage_himself(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);

        $this->actingAs($owner);

        $this->post(route('owner.employees.update', $owner), [
            'name' => 'X',
            'email' => 'x@shop.uz',
            'role' => User::ROLE_CASHIER,
        ])->assertForbidden();

        $this->post(route('owner.employees.toggle', $owner))->assertForbidden();
    }

    public function test_owner_can_deactivate_and_reactivate_employee(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $employee = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);

        $versionBefore = $employee->auth_version;

        $this->actingAs($owner);

        $this->post(route('owner.employees.toggle', $employee))->assertRedirect();

        $this->assertFalse($employee->fresh()->is_active);
        $this->assertSame($versionBefore + 1, $employee->fresh()->auth_version);

        $this->post(route('owner.employees.toggle', $employee))->assertRedirect();

        $this->assertTrue($employee->fresh()->is_active);
    }

    public function test_deactivated_employee_cannot_login(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        User::factory()->create([
            'email' => 'vali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
            'is_active' => false,
        ]);

        $this->post('/login', ['email' => 'vali@shop.uz', 'password' => 'parol123'])
            ->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_owner_inventory_page_is_read_only(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);

        $product = Product::factory()->for($shop)->create(['name' => 'Ko\'ylak', 'sku' => 'KOY-1']);
        ProductVariant::factory()->for($shop)->for($product)->create(['size' => 'M', 'stock_on_hand' => 1, 'reorder_level' => 3]);

        Product::factory()->for($otherShop)->create(['name' => 'Boshqa do\'kon mahsuloti']);

        $this->actingAs($owner);

        $this->get(route('owner.inventory.index'))
            ->assertOk()
            ->assertSee('Ko\'ylak')
            ->assertDontSee('Boshqa do\'kon mahsuloti')
            ->assertDontSee('data-edit-product', false)
            ->assertDontSee('data-price-input', false);
    }

    public function test_owner_inventory_stats_ignore_inactive_variants(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);

        $product = Product::factory()->for($shop)->create();

        ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'M',
            'stock_on_hand' => 1,
            'reorder_level' => 5,
            'is_active' => true,
        ]);
        ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'L',
            'stock_on_hand' => 1,
            'reorder_level' => 5,
            'is_active' => false,
        ]);
        ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'XL',
            'stock_on_hand' => 0,
            'reorder_level' => 5,
            'is_active' => false,
        ]);
        ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'S',
            'stock_on_hand' => 0,
            'reorder_level' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($owner);

        $stats = $this->get(route('owner.inventory.index'))->viewData('stats');

        // "Kam qolgan" faqat zaxirasi bor variantlar; 0 qoldiq "tugagan" hisoblanadi
        $this->assertSame(1, $stats['low_stock']);
        $this->assertSame(1, $stats['out_of_stock']);
    }

    public function test_employee_search_escapes_like_wildcards(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        User::factory()->create([
            'name' => 'Vali Kassir',
            'email' => 'vali@shop.uz',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($owner);

        $this->get(route('owner.employees.index', ['q' => '%']))
            ->assertOk()
            ->assertDontSee('Vali Kassir');
    }

    public function test_inventory_search_escapes_like_wildcards(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        Product::factory()->for($shop)->create(['name' => "Ko'ylak", 'sku' => 'KOY-1']);

        $this->actingAs($owner);

        $this->get(route('owner.inventory.index', ['q' => '%']))
            ->assertOk()
            ->assertDontSee("Ko'ylak");
    }

    public function test_inventory_page_lists_out_of_stock_variants(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);

        $product = Product::factory()->for($shop)->create(['name' => 'Kurtka']);
        ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'XL',
            'stock_on_hand' => 0,
            'reorder_level' => 5,
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('owner.inventory.index'));

        $response->assertOk()->assertSee(__('owner.report_out_of_stock_title'));

        $variants = $response->viewData('outOfStockVariants');
        $this->assertCount(1, $variants);
        $this->assertSame('XL', $variants->first()->size);
    }

    private function makeOwner(Shop $shop): User
    {
        return User::factory()->create([
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);
    }
}
