<?php

namespace Tests\Feature;

use App\Mail\ApplicationApproved;
use App\Mail\ApplicationRejected;
use App\Models\Application;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsAdmin(): void
    {
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin_test@ustbosh.uz',
            'password' => 'secret123',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->post('/admin/login', [
            'login' => 'admin_test@ustbosh.uz',
            'password' => 'secret123',
        ]);
    }

    public function test_admin_login_page_loads(): void
    {
        $this->get('/admin/login')->assertStatus(200);
    }

    public function test_admin_login_page_does_not_show_logout_button(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee(__('admin.logout'))
            ->assertDontSee(route('admin.logout'), false);
    }

    public function test_admin_cannot_login_with_wrong_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@ustbosh.uz',
            'password' => 'rightpass1',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $response = $this->post('/admin/login', [
            'login' => 'admin@ustbosh.uz',
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors(['login']);
        $this->assertGuest();
    }

    public function test_deactivated_admin_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'admin_test@ustbosh.uz',
            'password' => 'secret123',
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => false,
        ]);

        $this->post('/admin/login', [
            'login' => 'admin_test@ustbosh.uz',
            'password' => 'secret123',
        ])->assertSessionHasErrors(['login']);

        $this->assertGuest();
    }

    public function test_admin_is_logged_out_after_deactivation(): void
    {
        $this->loginAsAdmin();

        User::where('email', 'admin_test@ustbosh.uz')->firstOrFail()->update(['is_active' => false]);

        // Yangi so'rovda auth guard foydalanuvchini bazadan qayta o'qiydi
        $this->app['auth']->forgetGuards();

        $this->get('/admin/dashboard')
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', __('auth.user_inactive'));

        $this->assertGuest();
    }

    public function test_admin_session_is_revoked_when_auth_version_changes(): void
    {
        $this->loginAsAdmin();

        $admin = User::where('email', 'admin_test@ustbosh.uz')->firstOrFail();
        $admin->update(['auth_version' => $admin->auth_version + 1]);

        // Yangi so'rovda auth guard foydalanuvchini bazadan qayta o'qiydi
        $this->app['auth']->forgetGuards();

        $this->get('/admin/dashboard')
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', __('auth.session_revoked'));

        $this->assertGuest();
    }

    public function test_admin_is_auto_provisioned_from_env_on_first_login(): void
    {
        config([
            'admin.email' => 'provision@ustbosh.uz',
            'admin.password' => 'ProvisionPass1',
        ]);

        $this->post('/admin/login', [
            'login' => 'provision@ustbosh.uz',
            'password' => 'ProvisionPass1',
        ])->assertRedirect(route('admin.dashboard'));

        $user = User::where('email', 'provision@ustbosh.uz')->first();
        $this->assertNotNull($user);
        $this->assertEquals(User::ROLE_SUPER_ADMIN, $user->role);
        $this->assertTrue(Hash::check('ProvisionPass1', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_can_login_and_see_dashboard(): void
    {
        $this->loginAsAdmin();

        $this->get('/admin/dashboard')
            ->assertStatus(200)
            ->assertSee(__('admin.stat_total'));
    }

    public function test_admin_dashboard_has_language_switch_button(): void
    {
        $this->loginAsAdmin();

        $this->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(__('landing.lang_switch'), false);
    }

    public function test_admin_can_switch_locale_and_return_to_dashboard(): void
    {
        $this->loginAsAdmin();

        $this->get(route('locale.switch', [
            'locale' => 'ru',
            'from' => 'admin.dashboard',
        ]))->assertRedirect(route('admin.dashboard'));

        $this->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Админ-панель', false);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_admin_is_redirected_from_login(): void
    {
        $this->loginAsAdmin();

        $this->get('/admin/login')->assertRedirect(route('admin.dashboard'));
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::factory()->create([
            'email' => 'owner@shop.uz',
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($owner);

        $this->get('/admin/dashboard')->assertStatus(403);
    }

    public function test_admin_can_approve_application_and_email_is_sent(): void
    {
        Mail::fake();

        $application = Application::create([
            'shop_name' => 'Test Shop',
            'contact_name' => 'Ali',
            'email' => 'ali@example.com',
            'phone' => '+998901234567',
            'phone_normalized' => '998901234567',
            'contact_key' => 'ali@example.com|998901234567',
            'locale' => 'uz',
        ]);

        $this->loginAsAdmin();

        $response = $this->post(route('admin.applications.approve', $application));

        $response->assertRedirect();

        $application->refresh();
        $this->assertEquals(Application::STATUS_APPROVED, $application->status);
        $this->assertNotNull($application->reviewed_at);

        // reviewed_by admin tomonidan to'ldirilgan
        $admin = User::where('email', 'admin_test@ustbosh.uz')->first();
        $this->assertEquals($admin->id, $application->reviewed_by);

        Mail::assertSent(ApplicationApproved::class, fn ($mail) => $mail->hasTo('ali@example.com'));
    }

    public function test_admin_can_reject_application_with_reason_and_email_is_sent(): void
    {
        Mail::fake();

        $application = Application::create([
            'shop_name' => 'Test Shop',
            'contact_name' => 'Ali',
            'email' => 'ali@example.com',
            'phone' => '+998901234567',
            'phone_normalized' => '998901234567',
            'contact_key' => 'ali@example.com|998901234567',
            'locale' => 'ru',
        ]);

        $this->loginAsAdmin();

        $response = $this->post(route('admin.applications.reject', $application), [
            'reason' => 'Hozircha yangi doirlarni qabul qilmaymiz',
        ]);

        $response->assertRedirect();

        $application->refresh();
        $this->assertEquals(Application::STATUS_REJECTED, $application->status);
        $this->assertNotNull($application->rejection_reason);

        Mail::assertSent(ApplicationRejected::class, fn ($mail) => $mail->hasTo('ali@example.com'));
    }

    public function test_reject_requires_reason(): void
    {
        Mail::fake();

        $application = Application::create([
            'shop_name' => 'Test Shop',
            'contact_name' => 'Ali',
            'email' => 'ali@example.com',
            'phone' => '+998901234567',
            'phone_normalized' => '998901234567',
            'contact_key' => 'ali@example.com|998901234567',
        ]);

        $this->loginAsAdmin();

        $this->post(route('admin.applications.reject', $application), ['reason' => ''])
            ->assertSessionHasErrors(['reason']);

        Mail::assertNothingSent();
    }

    public function test_application_cannot_be_approved_twice(): void
    {
        Mail::fake();

        $application = Application::create([
            'shop_name' => 'Test Shop',
            'contact_name' => 'Ali',
            'email' => 'ali@example.com',
            'phone' => '+998901234567',
            'phone_normalized' => '998901234567',
            'contact_key' => 'ali@example.com|998901234567',
            'status' => Application::STATUS_APPROVED,
        ]);

        $this->loginAsAdmin();

        $this->post(route('admin.applications.approve', $application))->assertStatus(409);

        Mail::assertNothingSent();
    }

    public function test_admin_login_page_is_not_rate_limited(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->get('/admin/login')->assertOk();
        }
    }

    public function test_admin_dashboard_lists_shops_with_status(): void
    {
        Shop::create(['name' => 'Kurtka Markazi', 'status' => Shop::STATUS_SUSPENDED]);

        $this->loginAsAdmin();

        $this->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Kurtka Markazi')
            ->assertSee(__('admin.shop_status_suspended'))
            ->assertSee(__('admin.activate_btn'));
    }

    public function test_admin_can_suspend_and_activate_shop(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);

        $this->loginAsAdmin();

        $this->post(route('admin.shops.suspend', $shop))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame(Shop::STATUS_SUSPENDED, $shop->fresh()->status);

        // Takroriy so'rov holatni teskarisiga o'zgartirmaydi
        $this->post(route('admin.shops.suspend', $shop))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame(Shop::STATUS_SUSPENDED, $shop->fresh()->status);

        $this->post(route('admin.shops.activate', $shop))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame(Shop::STATUS_ACTIVE, $shop->fresh()->status);
    }

    public function test_suspending_shop_logs_out_its_owner(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $this->loginAsAdmin();
        $this->post(route('admin.shops.suspend', $shop));

        $this->actingAs($owner);
        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_customer_cannot_suspend_shop(): void
    {
        $shop = Shop::create(['name' => 'Test Shop', 'status' => Shop::STATUS_ACTIVE]);
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($owner);

        $this->post(route('admin.shops.suspend', $shop))->assertForbidden();

        $this->assertSame(Shop::STATUS_ACTIVE, $shop->fresh()->status);
    }

    public function test_approve_flashes_warning_when_email_fails(): void
    {
        Exceptions::fake();

        $application = Application::create([
            'shop_name' => 'Test Shop',
            'contact_name' => 'Ali',
            'email' => 'ali@example.com',
            'phone' => '+998901234567',
            'phone_normalized' => '998901234567',
            'contact_key' => 'ali@example.com|998901234567',
        ]);

        $this->loginAsAdmin();

        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('smtp down'));

        $this->post(route('admin.applications.approve', $application))
            ->assertRedirect()
            ->assertSessionHas('warning', __('admin.flash_approved_email_failed'));

        $this->assertSame(Application::STATUS_APPROVED, $application->fresh()->status);
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'smtp down');
    }

    public function test_approve_returns_warning_flag_in_json_when_email_fails(): void
    {
        Exceptions::fake();

        $application = Application::create([
            'shop_name' => 'Test Shop',
            'contact_name' => 'Ali',
            'email' => 'ali@example.com',
            'phone' => '+998901234567',
            'phone_normalized' => '998901234567',
            'contact_key' => 'ali@example.com|998901234567',
        ]);

        $this->loginAsAdmin();

        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('smtp down'));

        $this->postJson(route('admin.applications.approve', $application))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'warning' => true,
            ]);
    }

    public function test_admin_can_resend_approval_email(): void
    {
        Mail::fake();

        $application = Application::create([
            'shop_name' => 'Test Shop',
            'contact_name' => 'Ali',
            'email' => 'ali@example.com',
            'phone' => '+998901234567',
            'phone_normalized' => '998901234567',
            'contact_key' => 'ali@example.com|998901234567',
            'status' => Application::STATUS_APPROVED,
        ]);
        User::factory()->create([
            'email' => 'ali@example.com',
            'role' => User::ROLE_OWNER,
        ]);

        $this->loginAsAdmin();

        $this->post(route('admin.applications.resend', $application))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success');

        Mail::assertSent(ApplicationApproved::class, fn ($mail) => $mail->hasTo('ali@example.com'));
    }

    public function test_admin_can_resend_rejection_email(): void
    {
        Mail::fake();

        $application = Application::create([
            'shop_name' => 'Test Shop',
            'contact_name' => 'Ali',
            'email' => 'ali@example.com',
            'phone' => '+998901234567',
            'phone_normalized' => '998901234567',
            'contact_key' => 'ali@example.com|998901234567',
            'status' => Application::STATUS_REJECTED,
            'rejection_reason' => 'Hozircha qabul qilmaymiz',
        ]);

        $this->loginAsAdmin();

        $this->post(route('admin.applications.resend', $application))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success');

        Mail::assertSent(ApplicationRejected::class, fn ($mail) => $mail->hasTo('ali@example.com'));
    }

    public function test_resend_email_is_rejected_for_pending_application(): void
    {
        Mail::fake();

        $application = Application::create([
            'shop_name' => 'Test Shop',
            'contact_name' => 'Ali',
            'email' => 'ali@example.com',
            'phone' => '+998901234567',
            'phone_normalized' => '998901234567',
            'contact_key' => 'ali@example.com|998901234567',
        ]);

        $this->loginAsAdmin();

        $this->post(route('admin.applications.resend', $application))->assertStatus(409);

        Mail::assertNothingSent();
    }

    public function test_admin_login_shows_generic_error_for_unknown_email(): void
    {
        $this->post('/admin/login', [
            'login' => 'unknown@ustbosh.uz',
            'password' => 'whatever1',
        ])->assertSessionHasErrors(['login']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_admin_email_taken_by_customer_skips_auto_provision(): void
    {
        Log::spy();

        config([
            'admin.email' => 'ali@shop.uz',
            'admin.password' => 'SecretPass1',
        ]);

        User::factory()->create([
            'email' => 'ali@shop.uz',
            'role' => User::ROLE_OWNER,
        ]);

        $this->post('/admin/login', [
            'login' => 'ali@shop.uz',
            'password' => 'SecretPass1',
        ])->assertSessionHasErrors(['login']);

        $this->assertSame(0, User::where('role', User::ROLE_SUPER_ADMIN)->count());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_admin_can_search_shops_by_name(): void
    {
        Shop::create(['name' => 'Kurtka Markazi', 'status' => Shop::STATUS_ACTIVE]);
        Shop::create(['name' => 'Shim Markazi', 'status' => Shop::STATUS_ACTIVE]);

        $this->loginAsAdmin();

        $this->get('/admin/dashboard?shop_q=Kurtka')
            ->assertOk()
            ->assertSee('Kurtka Markazi')
            ->assertDontSee('Shim Markazi');
    }

    public function test_admin_shop_search_escapes_like_wildcards(): void
    {
        Shop::create(['name' => 'Kurtka Markazi', 'status' => Shop::STATUS_ACTIVE]);

        $this->loginAsAdmin();

        $this->get('/admin/dashboard?shop_q=%25')
            ->assertOk()
            ->assertDontSee('Kurtka Markazi');
    }
}
