<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mijoz login sahifasi uchun "guest" himoyasi.
 *
 * - Tizimga kirgan foydalanuvchi o'z paneliga yo'naltiriladi (owner/warehouse/cashier/super_admin)
 * - Admin login sahifasi uchun alohida himoya (RedirectIfAdminAuthenticated)
 */
class EnsureGuest
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        // Login urinishlari (POST) erkin o'tadi — muvaffaqiyatli kirishda
        // kontroller o'zi kerakli joyga yo'naltiradi
        if ($request->isMethod('post')) {
            return $next($request);
        }

        foreach ($guards ?: [null] as $guard) {
            if (Auth::guard($guard)->check()) {
                /** @var User $user */
                $user = Auth::guard($guard)->user();

                return redirect($user->dashboardRoute());
            }
        }

        return $next($request);
    }
}
