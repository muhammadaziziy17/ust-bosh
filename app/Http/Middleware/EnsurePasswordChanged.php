<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * must_change_password = true bo'lgan foydalanuvchini parol almashtirish
 * sahifasiga majburlaydi (faqat login paytida emas, har bir so'rovda).
 *
 * Istisno route'lar (parol almashtirish, logout, til almashtirish) bu
 * tekshiruvdan tashqarida bo'ladi.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user || $user->role === User::ROLE_SUPER_ADMIN) {
            return $next($request);
        }

        if ($user->must_change_password
            && ! $request->routeIs('password.change', 'password.change.submit', 'logout', 'locale.switch')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.must_change_password'),
                    'redirect' => route('password.change'),
                ], 403);
            }

            return redirect()->route('password.change')->with('info', __('auth.must_change_password'));
        }

        return $next($request);
    }
}
