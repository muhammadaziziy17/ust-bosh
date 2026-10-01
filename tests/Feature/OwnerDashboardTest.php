<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OwnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_each_dashboard_page(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);

        $this->actingAs($owner);

        $this->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee(__('owner.recent_sales_title'));

        $this->get(route('owner.employees.index'))
            ->assertOk()
            ->assertSee(__('owner.add_employee'));

        $this->get(route('owner.reports.index'))
            ->assertOk()
            ->assertSee(__('owner.report_top_products_title'));

        $this->get(route('owner.activity.index'))
            ->assertOk()
            ->assertSee(__('owner.login_history_title'));

        $this->get(route('owner.sales.index'))
            ->assertOk()
            ->assertSee(__('owner.sales_title'));

        $this->get(route('owner.inventory.index'))
            ->assertOk()
            ->assertSee(__('owner.inventory_products_title'));
    }

    public function test_reports_totals_cover_selected_period_and_skip_voided_sales(): void
    {
        $this->travelTo(Carbon::parse('2026-09-20 12:00:00'));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);

        $product = Product::factory()->for($shop)->create(['name' => 'Futbolka']);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create(['size' => 'M']);
        $secondVariant = ProductVariant::factory()->for($shop)->for($product)->create(['size' => 'L']);

        $cashSale = $this->makeSale($shop, $cashier, [
            'payment_method' => Sale::PAYMENT_CASH,
            'total' => 300000,
        ]);
        SaleItem::factory()->for($cashSale)->create([
            'shop_id' => $shop->id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => 'Futbolka',
            'quantity' => 2,
            'line_total' => 200000,
        ]);
        SaleItem::factory()->for($cashSale)->create([
            'shop_id' => $shop->id,
            'product_variant_id' => $secondVariant->id,
            'product_name_snapshot' => 'Shim',
            'quantity' => 1,
            'line_total' => 100000,
        ]);

        $cardSale = $this->makeSale($shop, $cashier, [
            'payment_method' => Sale::PAYMENT_CARD,
            'total' => 200000,
        ]);
        SaleItem::factory()->for($cardSale)->create([
            'shop_id' => $shop->id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => 'Futbolka',
            'quantity' => 1,
            'line_total' => 200000,
        ]);

        $this->makeSale($shop, $cashier, [
            'sold_at' => now()->subDays(10),
            'total' => 500000,
        ]);

        $this->makeSale($shop, $cashier, [
            'status' => Sale::STATUS_VOIDED,
            'total' => 999999,
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('owner.reports.index', ['range' => 'today']));
        $response->assertOk();

        $stats = $response->viewData('stats');
        $this->assertSame(500000.0, $stats['revenue']);
        $this->assertSame(2, $stats['sales']);
        $this->assertSame(4, $stats['items']);
        $this->assertSame(250000.0, $stats['average_check']);

        $paymentSplit = $response->viewData('paymentSplit');
        $this->assertSame(300000.0, $paymentSplit['cash']);
        $this->assertSame(200000.0, $paymentSplit['card']);
        $this->assertSame(60, $paymentSplit['cash_percent']);

        $topProducts = $response->viewData('topProducts');
        $this->assertSame('Futbolka', $topProducts->first()->product_name_snapshot);
        $this->assertSame(3, (int) $topProducts->first()->quantity_sum);

        $dailySales = $response->viewData('dailySales');
        $this->assertCount(1, $dailySales);
        $this->assertSame(2, (int) $dailySales->first()->sales_count);
    }

    public function test_reports_exclude_other_shops_sales(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);
        $otherCashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $otherShop->id]);

        $this->makeSale($shop, $cashier, ['total' => 100000]);
        $this->makeSale($otherShop, $otherCashier, ['total' => 999999]);

        $this->actingAs($owner);

        $response = $this->get(route('owner.reports.index', ['range' => 'today']));

        $stats = $response->viewData('stats');
        $this->assertSame(100000.0, $stats['revenue']);
        $this->assertSame(1, $stats['sales']);
    }

    public function test_reports_show_variants_at_or_below_reorder_level(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);

        $product = Product::factory()->for($shop)->create(['name' => 'Kam qolgan kurtka']);
        ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'M',
            'stock_on_hand' => 1,
            'reorder_level' => 5,
        ]);
        ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'L',
            'stock_on_hand' => 20,
            'reorder_level' => 5,
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('owner.reports.index'));

        $response->assertOk()->assertSee('Kam qolgan kurtka');
        $this->assertSame(1, $response->viewData('stats')['low_stock']);
    }

    public function test_reports_low_stock_excludes_out_of_stock_variants(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);

        $product = Product::factory()->for($shop)->create(['name' => 'Kurtka']);
        ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'M',
            'stock_on_hand' => 1,
            'reorder_level' => 5,
        ]);
        ProductVariant::factory()->for($shop)->for($product)->create([
            'size' => 'L',
            'stock_on_hand' => 0,
            'reorder_level' => 5,
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('owner.reports.index'));

        $variants = $response->viewData('lowStockVariants');

        $response->assertOk();
        $this->assertSame(1, $response->viewData('stats')['low_stock']);
        $this->assertCount(1, $variants);
        $this->assertSame(1, $variants->first()->stock_on_hand);
    }

    public function test_reports_custom_range_limits_sales_to_given_dates(): void
    {
        $this->travelTo(Carbon::parse('2026-09-20 12:00:00'));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);

        $this->makeSale($shop, $cashier, ['sold_at' => Carbon::parse('2026-09-10 10:00:00'), 'total' => 150000]);
        $this->makeSale($shop, $cashier, ['sold_at' => Carbon::parse('2026-09-19 10:00:00'), 'total' => 250000]);

        $this->actingAs($owner);

        $response = $this->get(route('owner.reports.index', [
            'range' => 'custom',
            'from' => '2026-09-15',
            'to' => '2026-09-20',
        ]));

        $stats = $response->viewData('stats');
        $this->assertSame(250000.0, $stats['revenue']);
        $this->assertSame(1, $stats['sales']);
    }

    public function test_reports_custom_range_daily_breakdown_includes_every_day(): void
    {
        $this->travelTo(Carbon::parse('2026-09-20 12:00:00'));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);

        $this->makeSale($shop, $cashier, ['sold_at' => Carbon::parse('2026-07-01 10:00:00'), 'total' => 100000]);
        $this->makeSale($shop, $cashier, ['sold_at' => Carbon::parse('2026-09-19 10:00:00'), 'total' => 200000]);

        $this->actingAs($owner);

        $response = $this->get(route('owner.reports.index', [
            'range' => 'custom',
            'from' => '2026-07-01',
            'to' => '2026-09-20',
        ]));

        $dailySales = $response->viewData('dailySales');

        $this->assertCount(2, $dailySales);
        $this->assertTrue($dailySales->contains(fn ($day): bool => $day->day === '2026-07-01'));
        $this->assertTrue($dailySales->contains(fn ($day): bool => $day->day === '2026-09-19'));
    }

    public function test_reports_custom_range_is_capped_to_one_year(): void
    {
        $this->travelTo(Carbon::parse('2026-09-20 12:00:00'));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);

        $this->makeSale($shop, $cashier, ['sold_at' => Carbon::parse('2020-06-01 10:00:00'), 'total' => 999999]);
        $this->makeSale($shop, $cashier, ['sold_at' => Carbon::parse('2026-09-19 10:00:00'), 'total' => 100000]);

        $this->actingAs($owner);

        $response = $this->get(route('owner.reports.index', [
            'range' => 'custom',
            'from' => '2020-01-01',
            'to' => '2026-09-20',
        ]));

        $this->assertSame('2025-09-20', $response->viewData('from')->toDateString());
        $this->assertSame(100000.0, $response->viewData('stats')['revenue']);
    }

    public function test_reports_custom_range_keeps_exact_maximum_period(): void
    {
        $this->travelTo(Carbon::parse('2026-09-20 12:00:00'));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);

        $this->actingAs($owner);

        $response = $this->get(route('owner.reports.index', [
            'range' => 'custom',
            'from' => '2025-09-20',
            'to' => '2026-09-20',
        ]));

        // 366 kalendar kun — chegara ichida, sana o'zgarmaydi
        $this->assertSame('2025-09-20', $response->viewData('from')->toDateString());
        $this->assertSame('2026-09-20', $response->viewData('to')->toDateString());
    }

    public function test_overview_today_revenue_ignores_earlier_sales(): void
    {
        $this->travelTo(Carbon::parse('2026-09-20 12:00:00'));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);

        $this->makeSale($shop, $cashier, ['total' => 120000]);
        $this->makeSale($shop, $cashier, ['sold_at' => now()->subDay(), 'total' => 800000]);

        $this->actingAs($owner);

        $response = $this->get(route('owner.dashboard'));

        $stats = $response->viewData('stats');
        $this->assertSame(120000.0, $stats['today_revenue']);
        $this->assertSame(1, $stats['today_sales']);
    }

    public function test_cashier_cannot_open_owner_pages(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);

        $this->actingAs($cashier);

        $this->get(route('owner.employees.index'))->assertForbidden();
        $this->get(route('owner.reports.index'))->assertForbidden();
        $this->get(route('owner.activity.index'))->assertForbidden();
        $this->get(route('owner.sales.index'))->assertForbidden();
        $this->get(route('owner.inventory.index'))->assertForbidden();
        $this->get(route('owner.reports.export'))->assertForbidden();
        $this->get(route('owner.reports.print'))->assertForbidden();
    }

    public function test_employee_credentials_modal_has_copy_and_close_actions(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);

        $this->actingAs($owner);

        $this->get(route('owner.employees.index'))
            ->assertOk()
            ->assertSee('id="copy-password-btn"', false)
            ->assertSee(__('owner.copy_password'))
            ->assertSee(__('owner.temp_credentials_close'));
    }

    public function test_overview_employee_count_ignores_inactive_employees(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id, 'is_active' => true]);
        User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id, 'is_active' => false]);

        $this->actingAs($owner);

        $stats = $this->get(route('owner.dashboard'))->viewData('stats');

        $this->assertSame(1, $stats['employees']);
    }

    private function makeOwner(Shop $shop): User
    {
        return User::factory()->create([
            'role' => User::ROLE_OWNER,
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
            'total' => 0,
        ], $attributes));
    }
}
