<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_is_rate_limited_per_user(): void
    {
        RateLimiter::for('change-password', fn () => Limit::perMinute(2));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'shop_id' => $shop->id]);

        $this->actingAs($owner);

        $this->post('/change-password', [])->assertRedirect();
        $this->post('/change-password', [])->assertRedirect();

        $this->postJson('/change-password', [])->assertTooManyRequests();
    }

    public function test_owner_actions_are_rate_limited(): void
    {
        RateLimiter::for('owner-actions', fn () => Limit::perMinute(2));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'shop_id' => $shop->id]);

        $this->actingAs($owner);

        $this->post('/employees', [])->assertRedirect();
        $this->post('/employees', [])->assertRedirect();

        $this->postJson('/employees', [])->assertTooManyRequests();
    }

    public function test_warehouse_actions_are_rate_limited(): void
    {
        RateLimiter::for('warehouse-actions', fn () => Limit::perMinute(2));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = User::factory()->create(['role' => User::ROLE_WAREHOUSE, 'shop_id' => $shop->id]);

        $this->actingAs($warehouse);

        $this->post('/warehouse/products', [])->assertRedirect();
        $this->post('/warehouse/products', [])->assertRedirect();

        $this->postJson('/warehouse/products', [])->assertTooManyRequests();
    }

    public function test_pos_void_is_rate_limited(): void
    {
        RateLimiter::for('pos-void', fn () => Limit::perMinute(2));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);
        $sale = Sale::factory()->for($shop)->create([
            'cashier_id' => $cashier->id,
            'status' => Sale::STATUS_COMPLETED,
            'payment_method' => Sale::PAYMENT_CASH,
        ]);

        $this->actingAs($cashier);

        $this->postJson(route('pos.sales.void', $sale), [])->assertStatus(422);
        $this->postJson(route('pos.sales.void', $sale), [])->assertStatus(422);

        $this->postJson(route('pos.sales.void', $sale), [])->assertTooManyRequests();
    }

    public function test_catalog_search_is_rate_limited(): void
    {
        RateLimiter::for('search', fn () => Limit::perMinute(2));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);

        $this->actingAs($cashier);

        $this->getJson(route('pos.catalog'))->assertOk();
        $this->getJson(route('pos.catalog'))->assertOk();

        $this->getJson(route('pos.catalog'))->assertTooManyRequests();
    }

    public function test_warehouse_variant_search_is_rate_limited(): void
    {
        RateLimiter::for('search', fn () => Limit::perMinute(2));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = User::factory()->create(['role' => User::ROLE_WAREHOUSE, 'shop_id' => $shop->id]);

        $product = Product::factory()->for($shop)->create();
        ProductVariant::factory()->for($shop)->for($product)->create();

        $this->actingAs($warehouse);

        $this->getJson(route('warehouse.variants.search'))->assertOk();
        $this->getJson(route('warehouse.variants.search'))->assertOk();

        $this->getJson(route('warehouse.variants.search'))->assertTooManyRequests();
    }

    public function test_product_edit_data_is_rate_limited(): void
    {
        RateLimiter::for('search', fn () => Limit::perMinute(2));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = User::factory()->create(['role' => User::ROLE_WAREHOUSE, 'shop_id' => $shop->id]);
        $product = Product::factory()->for($shop)->create();

        $this->actingAs($warehouse);

        $this->getJson(route('warehouse.products.show', $product))->assertOk();
        $this->getJson(route('warehouse.products.show', $product))->assertOk();

        $this->getJson(route('warehouse.products.show', $product))->assertTooManyRequests();
    }

    public function test_admin_login_throttle_is_keyed_by_login_and_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['login' => 'admin_one@ustbosh.uz', 'password' => 'wrong1'])
                ->assertSessionHasErrors(['login']);
        }

        // Xuddi shu login + IP — bloklangan
        $this->postJson('/admin/login', ['login' => 'admin_one@ustbosh.uz', 'password' => 'wrong1'])
            ->assertTooManyRequests();

        // Boshqa login — bloklanmagan
        $this->postJson('/admin/login', ['login' => 'admin_two@ustbosh.uz', 'password' => 'wrong1'])
            ->assertUnprocessable();
    }
}
