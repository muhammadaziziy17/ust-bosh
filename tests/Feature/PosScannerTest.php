<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ScannerScan;
use App\Models\ScannerSession;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosScannerTest extends TestCase
{
    use RefreshDatabase;

    private function makeCashier(Shop $shop): User
    {
        return User::factory()->create([
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
        ]);
    }

    private function createSession(Shop $shop, User $cashier, ?string $rawToken = null): array
    {
        $rawToken ??= bin2hex(random_bytes(32));
        $session = ScannerSession::factory()->create([
            'shop_id' => $shop->id,
            'cashier_id' => $cashier->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        return [$session, $rawToken];
    }

    /* ===== 1. Kassir sessiya yaratadi ===== */

    public function test_cashier_can_create_scanner_session(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $this->actingAs($cashier);

        $response = $this->postJson(route('pos.scanner.store'));

        $response->assertOk()
            ->assertJsonStructure(['success', 'session_id', 'token', 'url']);

        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('token'));

        // token_hash bazada bor
        $this->assertDatabaseHas('scanner_sessions', [
            'shop_id' => $shop->id,
            'cashier_id' => $cashier->id,
            'token_hash' => hash('sha256', $response->json('token')),
        ]);
    }

    /* ===== 2. Begona kassir eventsni ololmaydi ===== */

    public function test_other_cashier_cannot_poll_foreign_session(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier1 = $this->makeCashier($shop);
        $cashier2 = $this->makeCashier($shop);

        [$session] = $this->createSession($shop, $cashier1);

        $this->actingAs($cashier2);

        $this->getJson(route('pos.scanner.events', ['session' => $session->id]))
            ->assertNotFound();
    }

    /* ===== 3. Barcode skan found navbatga yozadi ===== */

    public function test_scan_found_creates_scan_record(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $product = Product::factory()->for($shop)->create(['sale_price' => 100000]);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create([
            'barcode' => '4812345678901',
            'stock_on_hand' => 10,
        ]);

        [$session, $rawToken] = $this->createSession($shop, $cashier);

        $response = $this->postJson(route('scanner.scan', ['token' => $rawToken]), [
            'barcode' => '4812345678901',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', ScannerScan::STATUS_FOUND);

        $this->assertDatabaseHas('scanner_scans', [
            'scanner_session_id' => $session->id,
            'product_variant_id' => $variant->id,
            'barcode' => '4812345678901',
            'status' => ScannerScan::STATUS_FOUND,
        ]);
    }

    /* ===== 4. Events uni qaytaradi; after bilan takrorlanmaydi ===== */

    public function test_events_returns_scans_and_after_cursor_works(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $product = Product::factory()->for($shop)->create(['sale_price' => 50000]);
        $variant = ProductVariant::factory()->for($shop)->for($product)->create([
            'barcode' => '1234567890123',
            'stock_on_hand' => 5,
        ]);

        [$session, $rawToken] = $this->createSession($shop, $cashier);

        // Skan yozish
        $this->postJson(route('scanner.scan', ['token' => $rawToken]), [
            'barcode' => '1234567890123',
        ])->assertOk();

        // Events — birinchi so'rov
        $this->actingAs($cashier);

        $response = $this->getJson(route('pos.scanner.events', [
            'session' => $session->id,
            'after' => 0,
        ]));

        $response->assertOk();
        $scans = $response->json('scans');
        $this->assertCount(1, $scans);
        $this->assertSame('found', $scans[0]['status']);

        $lastId = $scans[0]['id'];

        // Ikkinchi so'rov — after bilan takrorlanmaydi
        $response2 = $this->getJson(route('pos.scanner.events', [
            'session' => $session->id,
            'after' => $lastId,
        ]));

        $response2->assertOk();
        $this->assertCount(0, $response2->json('scans'));
    }

    /* ===== 5. Not found holati ===== */

    public function test_scan_not_found_status(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        [$session, $rawToken] = $this->createSession($shop, $cashier);

        $response = $this->postJson(route('scanner.scan', ['token' => $rawToken]), [
            'barcode' => '9999999999999',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', ScannerScan::STATUS_NOT_FOUND);

        $this->assertDatabaseHas('scanner_scans', [
            'scanner_session_id' => $session->id,
            'barcode' => '9999999999999',
            'status' => ScannerScan::STATUS_NOT_FOUND,
            'product_variant_id' => null,
        ]);
    }

    /* ===== 6. Inactive variant holati ===== */

    public function test_scan_inactive_variant_status(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $product = Product::factory()->for($shop)->create();
        ProductVariant::factory()->for($shop)->for($product)->create([
            'barcode' => '5555555555555',
            'stock_on_hand' => 10,
            'is_active' => false,
        ]);

        [$session, $rawToken] = $this->createSession($shop, $cashier);

        $response = $this->postJson(route('scanner.scan', ['token' => $rawToken]), [
            'barcode' => '5555555555555',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', ScannerScan::STATUS_INACTIVE);
    }

    /* ===== 7. Muddati o'tgan sessiya skanlarni rad etadi ===== */

    public function test_expired_session_rejects_scans(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $rawToken = bin2hex(random_bytes(32));
        ScannerSession::factory()->expired()->create([
            'shop_id' => $shop->id,
            'cashier_id' => $cashier->id,
            'token_hash' => hash('sha256', $rawToken),
        ]);

        $response = $this->postJson(route('scanner.scan', ['token' => $rawToken]), [
            'barcode' => '1111111111111',
        ]);

        $response->assertNotFound();
    }

    /* ===== 8. Yopilgan sessiya skanlarni rad etadi ===== */

    public function test_closed_session_rejects_scans(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $rawToken = bin2hex(random_bytes(32));
        ScannerSession::factory()->closed()->create([
            'shop_id' => $shop->id,
            'cashier_id' => $cashier->id,
            'token_hash' => hash('sha256', $rawToken),
        ]);

        $response = $this->postJson(route('scanner.scan', ['token' => $rawToken]), [
            'barcode' => '1111111111111',
        ]);

        $response->assertNotFound();
    }

    /* ===== 9. Search faqat o'z do'konini ko'radi ===== */

    public function test_search_only_sees_own_shop(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $cashier = $this->makeCashier($shop);

        $product = Product::factory()->for($shop)->create(['name' => 'Ko\'ylak']);
        ProductVariant::factory()->for($shop)->for($product)->create([
            'barcode' => '1111111111111',
            'stock_on_hand' => 5,
        ]);

        $foreignProduct = Product::factory()->for($otherShop)->create(['name' => 'Boshqa tovar']);
        ProductVariant::factory()->for($otherShop)->for($foreignProduct)->create([
            'barcode' => '2222222222222',
            'stock_on_hand' => 5,
        ]);

        [$session, $rawToken] = $this->createSession($shop, $cashier);

        $response = $this->getJson(route('scanner.search', [
            'token' => $rawToken,
            'q' => 'Ko\'ylak',
        ]));

        $response->assertOk();
        $names = collect($response->json('results'))->pluck('name');
        $this->assertTrue($names->contains('Ko\'ylak'));
        $this->assertFalse($names->contains('Boshqa tovar'));
    }

    /* ===== 10. Owner/warehouse scanner endpointlariga kira olmaydi ===== */

    public function test_owner_cannot_access_scanner_endpoints(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($owner);

        $this->postJson(route('pos.scanner.store'))->assertForbidden();
    }

    public function test_warehouse_cannot_access_scanner_endpoints(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $warehouse = User::factory()->create([
            'role' => User::ROLE_WAREHOUSE,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($warehouse);

        $this->postJson(route('pos.scanner.store'))->assertForbidden();
    }
}
