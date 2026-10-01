<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('admin.auth_required'),
                ], 401);
            }

            return redirect()
                ->route('admin.login')
                ->with('error', __('admin.auth_required'));
        }

        // Kirgan bo'lsa-yu, super_admin bo'lmasa — ruxsat yo'q
        abort_if($user->role !== User::ROLE_SUPER_ADMIN, 403);

        // Faolsizlantirilgan adminning mavjud sessiyasi ham yopiladi
        if ($user->is_active === false) {
            return $this->reject($request, __('auth.user_inactive'));
        }

        // Parol o'zgargan bo'lsa sessiya versiyasi mos kelmaydi — chiqarib yuboramiz
        $sessionVersion = (int) $request->session()->get('auth_version', -1);

        if ($sessionVersion === -1) {
            $request->session()->put('auth_version', $user->auth_version);
        } elseif ($sessionVersion !== (int) $user->auth_version) {
            return $this->reject($request, __('auth.session_revoked'));
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
            ], 401);
        }

        return redirect()
            ->route('admin.login')
            ->with('error', $message);
    }
}
