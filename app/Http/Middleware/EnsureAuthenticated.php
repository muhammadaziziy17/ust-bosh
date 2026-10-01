<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mijoz panellarini himoyalash ("auth" alias o'rniga).
 *
 * - Login qilmagan foydalanuvchi /login sahifasiga yo'naltiriladi
 * - Parol/sessiya versiyasi o'zgargan bo'lsa — chiqarib yuboriladi
 *   (owner parolni qayta generatsiya qilganda eski sessiyalar bekor bo'ladi)
 * - Super admin bu panellarga kira olmaydi (403) — u faqat admin panelga ega
 */
class EnsureAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.failed_custom'),
                ], 401);
            }

            return redirect()->route('login');
        }

        /** @var User $user */
        $user = Auth::user();

        // Faolsizlantirilgan xodimning mavjud sessiyasi ham yopiladi.
        // Modelda qiymat yuklanmagan bo'lishi mumkin — faqat aniq false'ni bloklaymiz.
        if ($user->is_active === false) {
            Auth::logout();
            $request->session()->invalidate();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.user_inactive'),
                ], 401);
            }

            return redirect()->route('login')->with('error', __('auth.user_inactive'));
        }

        // Sessiya auth_version'ga ega emas (remember-me orqali tiklangan yoki
        // eski sessiya) — joriy qiymatni yozib qo'yamiz. Mavjud bo'lib, farq
        // qilsa — sessiya bekor qilingan, chiqaramiz.
        $sessionVersion = (int) $request->session()->get('auth_version', -1);

        if ($sessionVersion === -1) {
            $request->session()->put('auth_version', $user->auth_version);
        } elseif ($sessionVersion !== (int) $user->auth_version) {
            Auth::logout();
            $request->session()->invalidate();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.session_revoked'),
                ], 401);
            }

            return redirect()->route('login')->with('error', __('auth.session_revoked'));
        }

        return $next($request);
    }
}
