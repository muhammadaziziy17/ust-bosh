<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin login sahifasi uchun redirect himoyasi.
 *
 * - Super admin kirgan bo'lsa — darhol admin dashboard'ga yo'naltiriladi
 * - Tizimga kirgan mijoz (owner/warehouse/cashier) admin login sahifasini
 *   ko'ra olmaydi — 404 qaytariladi
 */
class RedirectIfAdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        // Login urinishlari (POST) erkin o'tadi — muvaffaqiyatli kirishda
        // kontroller o'zi kerakli joyga yo'naltiradi
        if ($request->isMethod('post')) {
            return $next($request);
        }

        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($user->role === User::ROLE_SUPER_ADMIN) {
                return redirect()->route('admin.dashboard');
            }

            abort(404);
        }

        return $next($request);
    }
}
