<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_renders_brand_themed_page_with_correct_status(): void
    {
        $this->get('/mavjud-emas')
            ->assertStatus(404)
            ->assertSee('Xato cheki', false)
            ->assertSee('data-theme="savdomarkaz"', false);
    }

    public function test_403_renders_brand_themed_page_for_wrong_role(): void
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

        $this->get('/dashboard')
            ->assertStatus(403)
            ->assertSee('Xato cheki', false);
    }

    public function test_all_error_page_views_render_in_both_locales(): void
    {
        $expectedKickers = [
            401 => ['401 — tizimga kirish kerak', '401 — нужен вход в систему'],
            403 => ['403 — ruxsat yo‘q', '403 — доступ запрещён'],
            404 => ['404 — sahifa topilmadi', '404 — страница не найдена'],
            419 => ['419 — sessiya muddati tugadi', '419 — сессия истекла'],
            429 => ['429 — juda ko‘p urinish', '429 — слишком много попыток'],
            500 => ['500 — server xatosi', '500 — ошибка сервера'],
            503 => ['503 — texnik tanaffus', '503 — технический перерыв'],
        ];

        foreach ($expectedKickers as $code => [$uzKicker, $ruKicker]) {
            app()->setLocale('uz');
            $uz = view('errors.'.$code, ['code' => $code])->render();
            $this->assertStringContainsString($uzKicker, $uz, "uz $code kicker yo'q");

            app()->setLocale('ru');
            $ru = view('errors.'.$code, ['code' => $code])->render();
            $this->assertStringContainsString($ruKicker, $ru, "ru $code kicker yo'q");
        }
    }

    public function test_locale_switch_from_error_page_stays_on_same_page(): void
    {
        // Foydalanuvchi 404 sahifada til almashtirsa — SHU sahifada qolishi kerak
        $response = $this->get('/til/ru?back='.urlencode('/mavjud-emas'));

        $response->assertRedirect('/mavjud-emas');
    }

    public function test_locale_switch_rejects_external_back_targets(): void
    {
        // Tashqi manzilga qaytish — open redirect xavfi, landing'ga qaytishi kerak
        $response = $this->get('/til/ru?back='.urlencode('https://evil.example.com'));

        $response->assertRedirect(route('landing'));
    }

    public function test_locale_switch_rejects_backslash_back_targets(): void
    {
        // Brauzer "\" ni "/" deb talqin qiladi — tashqi manzilga chiqishga yo'l qo'yilmaydi
        $response = $this->get('/til/ru?back='.urlencode('/\\evil.example'));

        $response->assertRedirect(route('landing'));
    }
}
