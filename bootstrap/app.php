<?php

use App\Http\Middleware\EnsureActiveShop;
use App\Http\Middleware\EnsureAdminAuth;
use App\Http\Middleware\EnsureAuthenticated;
use App\Http\Middleware\EnsureGuest;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\RedirectIfAdminAuthenticated;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Xavfsizlik headerlari barcha javoblarga (404/500 ham) qo'shilishi uchun global
        $middleware->append(SetSecurityHeaders::class);

        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->alias([
            // Standart 'guest' o'rniga maxsus: kirgan user o'z paneliga yo'naltiriladi
            'guest' => EnsureGuest::class,
            'auth' => EnsureAuthenticated::class,

            'admin.auth' => EnsureAdminAuth::class,
            'guest-of-admin' => RedirectIfAdminAuthenticated::class,
            'role' => EnsureRole::class,
            'shop.active' => EnsureActiveShop::class,
            'password.changed' => EnsurePasswordChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Login/so'rov cheklanganda AJAX'ga tushunarli xabar qaytaramiz
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.too_many_attempts'),
                ], 429);
            }
        });
    })->create();
