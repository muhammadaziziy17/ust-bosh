<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Landing sahifasi: bir IP dan 30 sekundda 30 ta so'rov
        RateLimiter::for('landing', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Ariza formasi: bir IP dan daqiqada 3 ta, soatda 10 ta (spam'ga qarshi)
        RateLimiter::for('applications', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perHour(10)->by($request->ip().'|hour'),
        ]);

        // Admin login: brute force'ga qarshi — login + IP bo'yicha daqiqada 5 ta.
        // Kompozit kalit bir IP orqali turli loginlarni urinishni ham cheklaydi.
        RateLimiter::for('admin-login', fn (Request $request) => Limit::perMinute(5)->by(
            $request->ip().'|'.strtolower((string) $request->input('login', ''))
        ));

        // Mijoz login: brute force'ga qarshi — email + IP bo'yicha daqiqada 10 ta.
        // IP+email kaliti boshqa xodimning urinishlari sizni bloklamasligini ta'minlaydi.
        RateLimiter::for('customer-login', fn (Request $request) => Limit::perMinute(10)->by(
            $request->ip().'|'.strtolower((string) $request->input('email', ''))
        ));

        // Parol tiklash: spam'ga qarshi — daqiqada 3 ta
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));

        // Admin panel: oddiy ish yuklamasi
        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        // POS sotuv: har bir kassir daqiqada 60 ta (double-click spaminga qarshi)
        RateLimiter::for('pos-sales', fn (Request $request) => Limit::perMinute(60)->by(
            $request->user()?->id ?? $request->ip()
        ));

        // Parol o'zgartirish: joriy parolni brute-force qilishga qarshi
        RateLimiter::for('change-password', fn (Request $request) => Limit::perMinute(5)->by(
            $request->user()?->id ?? $request->ip()
        ));

        // Do'kon egasi amallari (xodimlar, sozlamalar, sotuvni bekor qilish)
        RateLimiter::for('owner-actions', fn (Request $request) => Limit::perMinute(60)->by(
            $request->user()?->id ?? $request->ip()
        ));

        // Ombor amallari (mahsulot va kirimlar)
        RateLimiter::for('warehouse-actions', fn (Request $request) => Limit::perMinute(60)->by(
            $request->user()?->id ?? $request->ip()
        ));

        // Sotuvni bekor qilish
        RateLimiter::for('pos-void', fn (Request $request) => Limit::perMinute(30)->by(
            $request->user()?->id ?? $request->ip()
        ));

        // Tezkor qidiruv API'lari (JS debounce sababli kengroq limit)
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(300)->by(
            $request->user()?->id ?? $request->ip()
        ));

        // POS skaner: kassir bo'yicha daqiqada 120 ta so'rov (polling + boshqaruv)
        RateLimiter::for('pos-scanner', fn (Request $request) => Limit::perMinute(120)->by(
            $request->user()?->id ?? $request->ip()
        ));

        // Telefon skaner (public): token + IP bo'yicha daqiqada 120 ta so'rov
        RateLimiter::for('scanner-public', function (Request $request) {
            $token = $request->route('token') ?? '';

            return Limit::perMinute(120)->by(
                $request->ip().'|'.substr($token, 0, 16)
            );
        });
    }
}
