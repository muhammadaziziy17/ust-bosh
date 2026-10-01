<?php

namespace App\Http\Controllers;

use App\Models\LoginEvent;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse|JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Emailni yagona formatga keltiramiz — registratsiya ham shunday saqlaydi
        $credentials['email'] = strtolower(trim($credentials['email']));

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed_custom'),
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        $request->session()->put('auth_version', $user->auth_version);

        // Faolsizlantirilgan xodim tizimga kira olmaydi
        if ($user->is_active === false) {
            Auth::logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'email' => __('auth.user_inactive'),
            ]);
        }

        // Super admin oddiy login orqali ham admin panelga kira oladi
        if ($user->role === User::ROLE_SUPER_ADMIN) {
            $redirect = route('admin.dashboard');
        } else {
            // Suspend qilingan do'kon xodimlari kira olmaydi
            if ($user->shop && $user->shop->status === Shop::STATUS_SUSPENDED) {
                Auth::logout();
                $request->session()->invalidate();

                throw ValidationException::withMessages([
                    'email' => __('auth.shop_suspended'),
                ]);
            }

            $redirect = $user->dashboardRoute();

            // Birinchi kirishda vaqtinchalik parol bilan bo'lsa — parolni almashtirishga majburlaymiz
            if ($user->must_change_password) {
                $redirect = route('password.change');
                $request->session()->flash('info', __('auth.must_change_password'));
            }
        }

        $this->recordLogin($user, $request);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('auth.welcome', ['name' => $user->name]),
                'redirect' => $redirect,
            ]);
        }

        return redirect()->intended($redirect);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }

    public function showChangePasswordForm(): View
    {
        return view('auth.change-password');
    }

    public function changePassword(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', PasswordRule::min(8)->mixedCase()->numbers(), 'confirmed'],
        ], [
            'current_password.required' => __('auth.current_password_required'),
            'password.required' => __('auth.password_required'),
            'password.confirmed' => __('auth.password_confirmed'),
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('auth.current_password_wrong'),
            ]);
        }

        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        // Boshqa barcha eski sessiyalarni bekor qilamiz,
        // lekin joriy sessiyani ochiq qoldiramiz.
        $user->revokeAllSessions();
        $request->session()->put('auth_version', $user->fresh()->auth_version);

        $message = __('auth.password_changed');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect' => $user->dashboardRoute(),
            ]);
        }

        return redirect($user->dashboardRoute())->with('success', $message);
    }

    public function showForgotPasswordForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', strtolower(trim($request->input('email'))))->first();

        // Foydalanuvchi mavjud bo'lmasa yoki super_admin bo'lsa ham
        // bir xil javob qaytaramiz — email mavjudligi oshkor qilinmaydi.
        if ($user && $user->canReceivePasswordReset()) {
            $token = Password::broker()->createToken($user);
            $user->sendPasswordResetNotification($token);
        }

        $message = __('auth.reset_link_sent');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    public function showResetPasswordForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', PasswordRule::min(8)->mixedCase()->numbers(), 'confirmed'],
        ], [
            'password.required' => __('auth.password_required'),
            'password.confirmed' => __('auth.password_confirmed'),
        ]);

        // Emailni broker ham, saqlash ham bir xil formatda ko'rishi kerak
        $validated['email'] = strtolower(trim($validated['email']));

        $status = Password::broker()->reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                ])->save();

                // Barcha eski sessiyalar va remember token bekor qilinadi
                $user->revokeAllSessions();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $message = __('auth.password_reset_success');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'redirect' => route('login'),
                ]);
            }

            return redirect()->route('login')->with('success', $message);
        }

        throw ValidationException::withMessages([
            'email' => __('auth.password_reset_link_invalid'),
        ]);
    }

    private function recordLogin(User $user, Request $request): void
    {
        $user->forceFill(['last_login_at' => now()])->save();

        LoginEvent::create([
            'user_id' => $user->id,
            'shop_id' => $user->shop_id,
            'logged_in_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);
    }
}
