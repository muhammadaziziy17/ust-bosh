<?php

namespace App\Http\Controllers;

use App\Mail\ApplicationApproved;
use App\Mail\ApplicationRejected;
use App\Models\Application;
use App\Models\Sale;
use App\Models\Scopes\TenantScope;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AdminDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));
        $shopSearch = trim((string) $request->query('shop_q', ''));
        $shopStatus = (string) $request->query('shop_status', 'all');

        $query = Application::query()->latest();

        if (in_array($status, [Application::STATUS_PENDING, Application::STATUS_APPROVED, Application::STATUS_REJECTED], true)) {
            $query->where('status', $status);
        }

        // Qidiruv: ism, email, telefon yoki do'kon nomi bo'yicha (LIKE escape bilan)
        if ($search !== '') {
            $term = str_replace(['%', '_'], ['\%', '\_'], $search);
            $query->where(function ($q) use ($term) {
                $q->where('contact_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('shop_name', 'like', "%{$term}%");
            });
        }

        $applications = $query->paginate(15)->withQueryString();

        $shopsQuery = Shop::query()
            ->with('owner:id,name,email,shop_id')
            ->withCount('users');

        if ($shopSearch !== '') {
            $shopTerm = str_replace(['%', '_'], ['\%', '\_'], $shopSearch);
            $shopsQuery->where('name', 'like', "%{$shopTerm}%");
        }

        if (in_array($shopStatus, [Shop::STATUS_ACTIVE, Shop::STATUS_SUSPENDED, Shop::STATUS_PENDING], true)) {
            $shopsQuery->where('status', $shopStatus);
        }

        $shops = $shopsQuery
            ->latest()
            ->paginate(15, ['*'], 'shops_page')
            ->withQueryString();

        // Optimizatsiya: statistika bitta query'da (4 emas)
        $stats = Application::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as pending', [Application::STATUS_PENDING])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as approved', [Application::STATUS_APPROVED])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as rejected', [Application::STATUS_REJECTED])
            ->first();

        $platformStats = [
            'shops_total' => Shop::query()->count(),
            'shops_active' => Shop::query()->where('status', Shop::STATUS_ACTIVE)->count(),
            'shops_suspended' => Shop::query()->where('status', Shop::STATUS_SUSPENDED)->count(),
            'users_total' => User::query()
                ->whereIn('role', [User::ROLE_OWNER, User::ROLE_CASHIER, User::ROLE_WAREHOUSE])
                ->count(),
            'sales_30d' => (float) Sale::withoutGlobalScope(TenantScope::class)
                ->where('status', Sale::STATUS_COMPLETED)
                ->where('sold_at', '>=', now()->subDays(30))
                ->sum('total'),
        ];

        return view('admin.dashboard', [
            'applications' => $applications,
            'shops' => $shops,
            'status' => $status,
            'search' => $search,
            'shopSearch' => $shopSearch,
            'shopStatus' => $shopStatus,
            'platformStats' => $platformStats,
            'stats' => [
                'total' => (int) $stats->total,
                'pending' => (int) $stats->pending,
                'approved' => (int) $stats->approved,
                'rejected' => (int) $stats->rejected,
            ],
        ]);
    }

    public function approve(Request $request, Application $application): RedirectResponse|JsonResponse
    {
        abort_unless($application->status === Application::STATUS_PENDING, 409);

        $adminId = $request->user()?->id;

        // Do'kon + egasi (login) yaratiladi — barchasi bitta transaction'da.
        // Email transaction'dan KEYIN yuboriladi: DB xatosi bo'lsa email ketmaydi,
        // email xatosi bo'lsa DB o'zgarishlari saqlanib qoladi (holat buzilmaydi).
        [$application, $shop, $owner] = DB::transaction(function () use ($application, $adminId) {
            $lockedApplication = Application::lockForUpdate()->findOrFail($application->id);

            abort_unless($lockedApplication->status === Application::STATUS_PENDING, 409);

            $shop = Shop::create([
                'name' => $lockedApplication->shop_name,
                'phone' => $lockedApplication->phone,
                'status' => Shop::STATUS_ACTIVE,
            ]);

            $owner = User::create([
                'name' => $lockedApplication->contact_name,
                'email' => $lockedApplication->email,
                'password' => Str::password(10, symbols: false),
                'role' => User::ROLE_OWNER,
                'shop_id' => $shop->id,
                'must_change_password' => true,
            ]);

            $lockedApplication->update([
                'status' => Application::STATUS_APPROVED,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ]);

            return [$lockedApplication->fresh(), $shop, $owner];
        });

        // Email mijozning tilida yuboriladi — parol o'rnatish linki bilan.
        // Oddiy parol email orqali ham, javobda ham yuborilmaydi.
        $resetToken = Password::broker()->createToken($owner);
        $resetUrl = url(route('password.reset', ['token' => $resetToken]).'?email='.urlencode($owner->email));

        $mailSent = $this->sendApplicationMail($application, new ApplicationApproved($application, $owner->email, $resetUrl));

        $message = $mailSent ? __('admin.flash_approved') : __('admin.flash_approved_email_failed');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'mail_failed' => ! $mailSent,
                'warning' => ! $mailSent,
                'status' => Application::STATUS_APPROVED,
                'application_id' => $application->id,
            ]);
        }

        return redirect()
            ->route('admin.dashboard', ['status' => Application::STATUS_APPROVED])
            ->with($mailSent ? 'success' : 'warning', $message);
    }

    public function reject(Request $request, Application $application): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'reason.required' => __('admin.reject_reason_required'),
            'reason.min' => __('admin.reject_reason_required'),
        ]);

        abort_unless($application->status === Application::STATUS_PENDING, 409);

        $adminId = $request->user()?->id;

        [$application, $reason] = DB::transaction(function () use ($application, $validated, $adminId) {
            $lockedApplication = Application::lockForUpdate()->findOrFail($application->id);

            abort_unless($lockedApplication->status === Application::STATUS_PENDING, 409);

            $lockedApplication->update([
                'status' => Application::STATUS_REJECTED,
                'rejection_reason' => $validated['reason'],
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ]);

            return [$lockedApplication->fresh(), $validated['reason']];
        });

        $mailSent = $this->sendApplicationMail($application, new ApplicationRejected($application, $reason));

        $message = $mailSent ? __('admin.flash_rejected') : __('admin.flash_rejected_email_failed');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'mail_failed' => ! $mailSent,
                'warning' => ! $mailSent,
                'status' => Application::STATUS_REJECTED,
                'application_id' => $application->id,
            ]);
        }

        return redirect()
            ->route('admin.dashboard', ['status' => Application::STATUS_REJECTED])
            ->with($mailSent ? 'success' : 'warning', $message);
    }

    public function resend(Request $request, Application $application): RedirectResponse|JsonResponse
    {
        abort_if($application->status === Application::STATUS_PENDING, 409);

        if ($application->status === Application::STATUS_APPROVED) {
            $owner = User::where('email', $application->email)->first();

            abort_unless($owner, 404);

            $resetToken = Password::broker()->createToken($owner);
            $resetUrl = url(route('password.reset', ['token' => $resetToken]).'?email='.urlencode($owner->email));

            $mailSent = $this->sendApplicationMail($application, new ApplicationApproved($application, $owner->email, $resetUrl));
        } else {
            $mailSent = $this->sendApplicationMail($application, new ApplicationRejected($application, (string) $application->rejection_reason));
        }

        $message = $mailSent ? __('admin.flash_email_resent') : __('admin.flash_email_failed');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => $mailSent,
                'message' => $message,
                'mail_failed' => ! $mailSent,
                'warning' => ! $mailSent,
                'application_id' => $application->id,
            ]);
        }

        return redirect()
            ->route('admin.dashboard')
            ->with($mailSent ? 'success' : 'warning', $message);
    }

    /**
     * Emailni mijoz tilida yuboradi. Yuborish xatosi oqimni uzmaydi —
     * holat DB'da allaqachon saqlangan, admin keyin qayta yuborishi mumkin.
     */
    private function sendApplicationMail(Application $application, Mailable $mailable): bool
    {
        $previousLocale = app()->getLocale();
        App::setLocale($application->locale ?: 'uz');

        try {
            Mail::to($application->email)->send($mailable);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        } finally {
            App::setLocale($previousLocale);
        }
    }
}
