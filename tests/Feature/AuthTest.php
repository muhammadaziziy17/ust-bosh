<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\LoginEvent;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_login_and_see_dashboard(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $response = $this->post('/login', [
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
        ]);

        $response->assertRedirect(route('owner.dashboard'));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_owner_dashboard_requires_auth(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_customer_login_page_does_not_show_logout_button(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee(__('admin.logout'))
            ->assertDontSee(route('logout'), false);
    }

    public function test_owner_can_add_cashier_employee(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($owner);

        $response = $this->post('/employees', [
            'name' => 'Vali Kassir',
            'email' => 'vali@shop.uz',
            'role' => User::ROLE_CASHIER,
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);

        $response->assertStatus(201);
        $response->assertJsonStructure(['temporary_password']);
        $this->assertNotEmpty($response->json('temporary_password'));
        $this->assertDatabaseHas('users', [
            'email' => 'vali@shop.uz',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
            'must_change_password' => true,
        ]);
    }

    public function test_login_records_last_login_and_login_event(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $this->post('/login', ['email' => 'ali@shop.uz', 'password' => 'parol123'])
            ->assertRedirect(route('owner.dashboard'));

        $this->assertNotNull($owner->fresh()->last_login_at);
        $this->assertDatabaseHas('login_events', [
            'user_id' => $owner->id,
            'shop_id' => $shop->id,
        ]);
    }

    public function test_owner_activity_page_search_filters_login_history(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);
        $cashierA = User::create([
            'name' => 'Vali Kassir',
            'email' => 'vali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
        ]);
        $cashierB = User::create([
            'name' => 'Olim Kassir',
            'email' => 'olim@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
        ]);

        LoginEvent::create(['user_id' => $cashierA->id, 'shop_id' => $shop->id, 'logged_in_at' => now(), 'ip_address' => '1.1.1.1']);
        LoginEvent::create(['user_id' => $cashierB->id, 'shop_id' => $shop->id, 'logged_in_at' => now(), 'ip_address' => '2.2.2.2']);

        $this->actingAs($owner);

        $this->get(route('owner.activity.index', ['q' => 'Vali']))
            ->assertOk()
            ->assertSee('1.1.1.1')
            ->assertDontSee('2.2.2.2');
    }

    public function test_owner_activity_page_role_filter(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);
        $cashier = User::create([
            'name' => 'Vali Kassir',
            'email' => 'vali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
        ]);
        $warehouse = User::create([
            'name' => 'Olim Skladchi',
            'email' => 'olim@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_WAREHOUSE,
            'shop_id' => $shop->id,
        ]);

        LoginEvent::create(['user_id' => $cashier->id, 'shop_id' => $shop->id, 'logged_in_at' => now(), 'ip_address' => '1.1.1.1']);
        LoginEvent::create(['user_id' => $warehouse->id, 'shop_id' => $shop->id, 'logged_in_at' => now(), 'ip_address' => '2.2.2.2']);

        $this->actingAs($owner);

        $this->get(route('owner.activity.index', ['role' => User::ROLE_CASHIER]))
            ->assertOk()
            ->assertSee('1.1.1.1')
            ->assertDontSee('2.2.2.2');
    }

    public function test_owner_activity_search_escapes_like_wildcards(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);
        $cashier = User::create([
            'name' => 'Zulfiya Sotuvchi',
            'email' => 'zulfiya@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
        ]);

        LoginEvent::create([
            'user_id' => $cashier->id,
            'shop_id' => $shop->id,
            'logged_in_at' => now(),
            'ip_address' => '1.1.1.1',
        ]);

        $this->actingAs($owner);

        $this->get(route('owner.activity.index', ['q' => '%']))
            ->assertOk()
            ->assertDontSee('Zulfiya');
    }

    public function test_worker_with_must_change_password_is_redirected_to_change_password(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $cashier = User::create([
            'name' => 'Vali',
            'email' => 'vali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
            'must_change_password' => true,
        ]);

        $this->post('/login', ['email' => 'vali@shop.uz', 'password' => 'parol123'])
            ->assertRedirect(route('password.change'));
    }

    public function test_worker_is_redirected_to_own_dashboard_after_password_change(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $cashier = User::create([
            'name' => 'Vali',
            'email' => 'vali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
            'must_change_password' => true,
        ]);

        $this->post('/login', ['email' => 'vali@shop.uz', 'password' => 'parol123'])
            ->assertRedirect(route('password.change'));

        $response = $this->post('/change-password', [
            'current_password' => 'parol123',
            'password' => 'YangiParol123',
            'password_confirmation' => 'YangiParol123',
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'redirect' => route('pos.index'),
        ]);
        $this->assertFalse($cashier->fresh()->must_change_password);

        // Kassir o'z dashboardiga (POS) 403siz kira oladi
        $this->get(route('pos.index'))->assertOk();
    }

    public function test_change_password_page_shows_live_password_rules(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($owner);

        $this->get(route('password.change'))
            ->assertOk()
            ->assertSee('data-password-form', false)
            ->assertSee(__('auth.password_rule_length'))
            ->assertSee(__('auth.password_rule_case'))
            ->assertSee(__('auth.password_rule_number'))
            ->assertSee(__('auth.password_rule_match'));
    }

    public function test_reset_password_page_shows_live_password_rules(): void
    {
        $this->get(route('password.reset', ['token' => 'test-token', 'email' => 'ali@shop.uz']))
            ->assertOk()
            ->assertSee('data-password-form', false)
            ->assertSee(__('auth.password_rule_length'));
    }

    public function test_login_page_does_not_show_password_rules(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('data-password-form', false)
            ->assertDontSee(__('auth.password_rule_length'));
    }

    public function test_regenerated_password_works_after_owner_reset(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);
        $cashier = User::create([
            'name' => 'Vali',
            'email' => 'vali@shop.uz',
            'password' => 'BirinchiParol1',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
            'must_change_password' => true,
        ]);

        // Xodim birinchi parolni o'rnatadi
        $this->post('/login', ['email' => 'vali@shop.uz', 'password' => 'BirinchiParol1']);
        $this->post('/change-password', [
            'current_password' => 'BirinchiParol1',
            'password' => 'IkkinchiParol2',
            'password_confirmation' => 'IkkinchiParol2',
        ]);

        // Owner yangi vaqtinchalik parol generatsiya qiladi
        $this->post('/login', ['email' => 'ali@shop.uz', 'password' => 'parol123']);

        $response = $this->post(
            route('owner.employees.reset-password', $cashier),
            [],
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']
        );

        $response->assertOk();
        $newPassword = $response->json('temporary_password');
        $this->assertNotEmpty($newPassword);
        $this->assertTrue($cashier->fresh()->must_change_password);

        // Eski parollar ishlamasligi kerak
        $this->post('/login', ['email' => 'vali@shop.uz', 'password' => 'BirinchiParol1'])
            ->assertSessionHasErrors(['email']);
        $this->post('/login', ['email' => 'vali@shop.uz', 'password' => 'IkkinchiParol2'])
            ->assertSessionHasErrors(['email']);

        // Yangi vaqtinchalik parol ishlashi kerak
        $this->post('/login', ['email' => 'vali@shop.uz', 'password' => $newPassword])
            ->assertRedirect(route('password.change'));
    }

    public function test_owner_can_regenerate_worker_password(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);
        $cashier = User::create([
            'name' => 'Vali',
            'email' => 'vali@shop.uz',
            'password' => 'eskiParol123',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($owner);

        $response = $this->post(
            route('owner.employees.reset-password', $cashier),
            [],
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']
        );

        $response->assertOk();
        $temporaryPassword = $response->json('temporary_password');
        $this->assertNotEmpty($temporaryPassword);
        $this->assertTrue(password_verify($temporaryPassword, $cashier->fresh()->password));
        $this->assertTrue($cashier->fresh()->must_change_password);
    }

    public function test_owner_cannot_regenerate_password_of_other_shop_employee(): void
    {
        $shopOne = Shop::create(['name' => 'Shop One', 'status' => Shop::STATUS_ACTIVE]);
        $shopTwo = Shop::create(['name' => 'Shop Two', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shopOne->id,
        ]);
        $otherCashier = User::create([
            'name' => 'Vali',
            'email' => 'vali@boshqa.uz',
            'password' => 'parol123',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shopTwo->id,
        ]);

        $this->actingAs($owner);

        $this->post(route('owner.employees.reset-password', $otherCashier))
            ->assertStatus(403);
    }

    public function test_password_reset_link_is_sent_and_password_updated(): void
    {
        Notification::fake();

        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $this->post('/forgot-password', ['email' => 'ali@shop.uz'])
            ->assertRedirect();

        Notification::assertSentTo($owner, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'ali@shop.uz',
            'password' => 'YangiParol123',
            'password_confirmation' => 'YangiParol123',
        ])->assertRedirect(route('login'));

        $this->post('/login', ['email' => 'ali@shop.uz', 'password' => 'YangiParol123'])
            ->assertRedirect(route('owner.dashboard'));
    }

    public function test_login_throttle_blocks_only_same_email_and_ip(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        User::create([
            'name' => 'Vali',
            'email' => 'vali@shop.uz',
            'password' => 'ToGriParol1',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
            'must_change_password' => false,
        ]);

        // Boshqa emaildagi xatolar bu emailni bloklamaydi
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => 'boshqa@shop.uz', 'password' => 'Xato']);
        }

        $this->post('/login', ['email' => 'vali@shop.uz', 'password' => 'ToGriParol1'])
            ->assertRedirect(route('pos.index'));

        // Xuddi shu email+IP dan 10 ta xato urinish — bloklanadi va tushunarli xabar qaytadi
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => 'vali@shop.uz', 'password' => 'Xato']);
        }

        $this->post('/login', [
            'email' => 'vali@shop.uz',
            'password' => 'ToGriParol1',
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->assertStatus(429)
            ->assertJson(['message' => __('auth.too_many_attempts')]);
    }

    public function test_super_admin_does_not_receive_reset_link(): void
    {
        Notification::fake();

        User::create([
            'name' => 'Super',
            'email' => 'super@ustbosh.uz',
            'password' => 'parol123',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->post('/forgot-password', ['email' => 'super@ustbosh.uz'])
            ->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_cashier_cannot_access_owner_dashboard(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $cashier = User::create([
            'name' => 'Vali',
            'email' => 'vali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($cashier);
        $this->get('/dashboard')->assertStatus(403);
    }

    public function test_suspended_shop_owner_cannot_login(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_SUSPENDED]);
        User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $response = $this->post('/login', [
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_approving_application_creates_shop_and_owner_without_exposing_password(): void
    {
        Mail::fake();

        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@ustbosh.uz',
            'password' => 'adminpass1',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->post('/admin/login', ['login' => 'admin@ustbosh.uz', 'password' => 'adminpass1']);

        $application = Application::create([
            'shop_name' => 'Yangi Do\'kon',
            'contact_name' => 'Ali Valiyev',
            'email' => 'ali@newshop.uz',
            'phone' => '+998901234567',
            'phone_normalized' => '998901234567',
            'contact_key' => 'ali@newshop.uz|998901234567',
            'locale' => 'uz',
        ]);

        $response = $this->post(
            route('admin.applications.approve', $application),
            [],
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']
        );

        $response->assertStatus(200);

        // Shop yaratildi
        $shop = Shop::where('name', "Yangi Do'kon")->first();
        $this->assertNotNull($shop);
        $this->assertEquals(Shop::STATUS_ACTIVE, $shop->status);
        $this->assertSame('+998901234567', $shop->phone);

        // Owner yaratildi va parol hash'langan
        $owner = User::where('email', 'ali@newshop.uz')->first();
        $this->assertNotNull($owner);
        $this->assertEquals(User::ROLE_OWNER, $owner->role);
        $this->assertEquals($shop->id, $owner->shop_id);
        $this->assertTrue($owner->must_change_password);
        $this->assertNotEquals($response->json('credentials.password') ?? '', $owner->password);

        // Javobda hech qanday parol ko'rsatilmaydi
        $response->assertJsonMissing(['credentials']);
    }

    public function test_admin_search_finds_by_email_phone_name_and_shop(): void
    {
        User::factory()->create([
            'email' => 'admin@ustbosh.uz',
            'password' => 'adminpass1',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $this->post('/admin/login', ['login' => 'admin@ustbosh.uz', 'password' => 'adminpass1']);

        Application::create([
            'shop_name' => 'Chayon Savdo',
            'contact_name' => 'Ali Valiyev',
            'email' => 'ali@chayon.uz',
            'phone' => '+998901112233',
            'phone_normalized' => '998901112233',
            'contact_key' => 'ali@chayon.uz|998901112233',
        ]);
        Application::create([
            'shop_name' => 'Boshqa Do\'kon',
            'contact_name' => 'Vali Toshmatov',
            'email' => 'vali@boshqa.uz',
            'phone' => '+998909998877',
            'phone_normalized' => '998909998877',
            'contact_key' => 'vali@boshqa.uz|998909998877',
        ]);

        // Email bo'yicha
        $this->get('/admin/dashboard?q=ali@chayon.uz')
            ->assertOk()
            ->assertSee('Chayon Savdo')
            ->assertDontSee('Boshqa Do\'kon');

        // Telefon bo'yicha
        $this->get('/admin/dashboard?q=909998877')
            ->assertOk()
            ->assertSee('Vali Toshmatov')
            ->assertDontSee('Chayon Savdo');

        // Ism bo'yicha
        $this->get('/admin/dashboard?q=Vali')
            ->assertOk()
            ->assertSee('Vali Toshmatov');

        // Do'kon nomi bo'yicha
        $this->get('/admin/dashboard?q=Chayon')
            ->assertOk()
            ->assertSee('Ali Valiyev');
    }

    public function test_admin_login_page_is_hidden_from_authenticated_customer(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($owner);

        // Kirgan mijoz admin login sahifasini ko'ra olmaydi
        $this->get('/admin/login')->assertStatus(404);
    }

    public function test_customer_login_page_is_hidden_while_admin_authenticated(): void
    {
        User::factory()->create([
            'email' => 'admin@ustbosh.uz',
            'password' => 'pass',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->actingAs(User::where('email', 'admin@ustbosh.uz')->first());

        // Super admin kirgan — mijoz login sahifasi ko'rsatilmaydi
        $this->get('/login')->assertRedirect(route('admin.dashboard'));
    }

    public function test_customer_login_replaces_admin_session(): void
    {
        User::factory()->create([
            'email' => 'admin@ustbosh.uz',
            'password' => 'pass',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $this->post('/admin/login', ['login' => 'admin@ustbosh.uz', 'password' => 'pass']);

        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $this->post('/login', ['email' => 'ali@shop.uz', 'password' => 'parol123']);

        $this->assertAuthenticatedAs($owner);
        // Admin endi kirgan emas
        $this->get('/admin/dashboard')->assertStatus(403);
    }

    public function test_admin_login_replaces_customer_session(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs(User::where('email', 'ali@shop.uz')->first());
        $this->assertAuthenticated();

        User::factory()->create([
            'email' => 'admin@ustbosh.uz',
            'password' => 'pass',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $this->post('/admin/login', ['login' => 'admin@ustbosh.uz', 'password' => 'pass']);

        // Mijoz sessiyasi yopildi
        $super = User::where('email', 'admin@ustbosh.uz')->first();
        $this->assertAuthenticatedAs($super);
        $this->get('/dashboard')->assertStatus(403);
    }

    public function test_super_admin_role_cannot_access_customer_dashboards(): void
    {
        $super = User::create([
            'name' => 'Super',
            'email' => 'super@ustbosh.uz',
            'password' => 'parol123',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->actingAs($super);

        $this->get('/dashboard')->assertStatus(403);
        $this->get('/warehouse')->assertStatus(403);
        $this->get('/pos')->assertStatus(403);
    }

    public function test_admin_dashboard_rejects_customer_session(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        // Faqat Auth sessiyasi bor, super_admin emas
        $this->actingAs($owner);

        $this->get('/admin/dashboard')->assertStatus(403);
    }

    public function test_worker_cannot_bypass_must_change_password_via_direct_url(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $cashier = User::create([
            'name' => 'Vali',
            'email' => 'vali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
            'must_change_password' => true,
        ]);

        $this->actingAs($cashier);

        // To'g'ridan-to'g'ri POS'ga kirish — parol almashtirishga yo'naltiriladi
        $this->get('/pos')->assertRedirect(route('password.change'));
        $this->get('/warehouse')->assertRedirect(route('password.change'));
        $this->get('/dashboard')->assertRedirect(route('password.change'));
    }

    public function test_pending_shop_owner_cannot_access_dashboard(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_PENDING]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($owner);

        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_owner_without_shop_cannot_access_dashboard(): void
    {
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@orphan.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
        ]);

        $this->actingAs($owner);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_regeneration_revokes_existing_session(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);
        $cashier = User::create([
            'name' => 'Vali',
            'email' => 'vali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
        ]);

        // Kassir kirgan
        $this->post('/login', ['email' => 'vali@shop.uz', 'password' => 'parol123'])
            ->assertRedirect(route('pos.index'));

        // Owner parolni qayta generatsiya qiladi
        $this->actingAs($owner);
        $this->post(route('owner.employees.reset-password', $cashier));

        // Kassirning eski sessiyasi bekor qilingan — auth_version mos kelmaydi
        $this->actingAs($cashier->fresh());
        $this->get('/pos')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', __('auth.session_revoked'));
        $this->assertGuest();
    }

    public function test_change_password_keeps_current_session_active(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $cashier = User::create([
            'name' => 'Vali',
            'email' => 'vali@shop.uz',
            'password' => 'BirinchiParol1',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
            'must_change_password' => true,
        ]);

        $this->post('/login', ['email' => 'vali@shop.uz', 'password' => 'BirinchiParol1'])
            ->assertRedirect(route('password.change'));

        $this->post('/change-password', [
            'current_password' => 'BirinchiParol1',
            'password' => 'IkkinchiParol2',
            'password_confirmation' => 'IkkinchiParol2',
        ])->assertRedirect(route('pos.index'));

        // Joriy sessiya ochiq qoladi
        $this->get('/pos')->assertOk();
    }

    public function test_login_is_case_insensitive_for_email(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $this->post('/login', ['email' => 'ALI@SHOP.UZ', 'password' => 'parol123'])
            ->assertRedirect(route('owner.dashboard'));
    }

    public function test_employee_email_is_normalized_when_created(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($owner);

        $this->post('/employees', [
            'name' => 'Vali',
            'email' => '  VALI@Shop.uz  ',
            'role' => User::ROLE_CASHIER,
        ]);

        $this->assertDatabaseHas('users', ['email' => 'vali@shop.uz', 'role' => User::ROLE_CASHIER]);
    }

    public function test_employee_email_uniqueness_ignores_case(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);
        User::create([
            'name' => 'Vali',
            'email' => 'vali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_CASHIER,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($owner);

        $this->post('/employees', [
            'name' => 'Yangi Xodim',
            'email' => 'VALI@SHOP.UZ',
            'role' => User::ROLE_WAREHOUSE,
        ])->assertSessionHasErrors(['email']);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_password_reset_accepts_uppercase_email(): void
    {
        Notification::fake();

        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::create([
            'name' => 'Ali',
            'email' => 'ali@shop.uz',
            'password' => 'parol123',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $this->post('/forgot-password', ['email' => 'ali@shop.uz'])->assertRedirect();

        Notification::assertSentTo($owner, ResetPassword::class, function ($notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'ALI@SHOP.UZ',
            'password' => 'YangiParol123',
            'password_confirmation' => 'YangiParol123',
        ])->assertRedirect(route('login'));

        $this->post('/login', ['email' => 'ali@shop.uz', 'password' => 'YangiParol123'])
            ->assertRedirect(route('owner.dashboard'));
    }
}
