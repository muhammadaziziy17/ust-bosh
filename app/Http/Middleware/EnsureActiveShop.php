<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Do'kon (tenant) faol ekanini tekshiradi.
 *
 * - Super admin uchun tekshiruv o'tkazilmaydi (shop_id yo'q)
 * - Suspended do'kon xodimi chiqarib yuboriladi
 * - Do'koni yo'q (orphan) yoki pending xodim ham chiqarib yuboriladi
 */
class EnsureActiveShop
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user || $user->role === User::ROLE_SUPER_ADMIN) {
            return $next($request);
        }

        $shop = $user->shop;

        if (! $shop) {
            return $this->reject($request, __('auth.shop_pending'));
        }

        if ($shop->status === Shop::STATUS_SUSPENDED) {
            return $this->reject($request, __('auth.shop_suspended'));
        }

        if (! $shop->isActive()) {
            return $this->reject($request, __('auth.shop_pending'));
        }

        return $next($request);
    }

    private function reject(Request $request, string $message): Response
    {
        Auth::logout();
        $request->session()->invalidate();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 403);
        }

        return redirect()->route('login')->with('error', $message);
    }
}
