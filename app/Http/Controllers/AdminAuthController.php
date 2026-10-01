<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    /**
     * Foydalanuvchi topilmasa ham parol tekshiruvi bir xil vaqt olishi uchun
     * ishlatiladigan "bo'sh" bcrypt hash (email enumeration'ning oldini oladi).
     */
    private const DUMMY_PASSWORD_HASH = '$2y$12$Z9NM4TNC1wWuyXFdlbHin.8E98b7FMGUCwFEuk4q4DKPYLoot8uPi';

    public function showLoginForm(): View
    {
        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($validated['login']));

        $user = User::where('email', $email)
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->first();

        // Hali super_admin yaratilmagan bo'lsa — .env'dagi ADMIN_EMAIL/ADMIN_PASSWORD
        // asosida birinchi login paytida avtomatik yaratamiz (hash'langan parol bilan).
        if (! $user && $email === strtolower(trim((string) config('admin.email')))
            && config('admin.password') !== '') {
            // Bu email boshqa rol bilan band bo'lsa — super admin yaratib bo'lmaydi
            if (User::where('email', $email)->exists()) {
                Log::warning('Super admin auto-provision failed: email already taken.', ['email' => $email]);
            } else {
                try {
                    $user = User::create([
                        'name' => 'Super Admin',
                        'email' => $email,
                        'password' => (string) config('admin.password'),
                        'role' => User::ROLE_SUPER_ADMIN,
                        'must_change_password' => false,
                    ]);
                } catch (QueryException) {
                    // Ikki parallel so'rov bir vaqtda yaratishga urinsa — qayta topamiz
                    $user = User::where('email', $email)
                        ->where('role', User::ROLE_SUPER_ADMIN)
                        ->first();
                }
            }
        }

        // Foydalanuvchi topilmasa ham parol tekshiruvi bir xil vaqt oladi
        if (! Hash::check($validated['password'], $user?->password ?? self::DUMMY_PASSWORD_HASH)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('admin.login_error'),
                ], 422);
            }

            return back()
                ->withErrors(['login' => __('admin.login_error')])
                ->onlyInput('login');
        }

        // Faolsizlantirilgan super admin tizimga kira olmaydi
        if ($user->is_active === false) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.user_inactive'),
                ], 422);
            }

            return back()
                ->withErrors(['login' => __('auth.user_inactive')])
                ->onlyInput('login');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_version', $user->auth_version);

        $message = __('admin.login_welcome');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect' => route('admin.dashboard'),
            ]);
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('success', $message);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
