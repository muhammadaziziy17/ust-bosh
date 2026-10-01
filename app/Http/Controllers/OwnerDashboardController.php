<?php

namespace App\Http\Controllers;

use App\Models\LoginEvent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\OwnerReportExcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class OwnerDashboardController extends Controller
{
    /**
     * Hisobot davri variantlari.
     */
    private const REPORT_RANGES = ['today', '7d', '30d', 'month', 'custom'];

    /**
     * "Boshqa davr" uchun maksimal kun soni — sahifa va Excel eksporti xotirasini cheklaydi.
     */
    private const MAX_REPORT_DAYS = 366;

    public function index(Request $request): View
    {
        $user = $request->user();
        $shop = $user->shop;

        $todaySales = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sold_at', [now()->startOfDay(), now()->endOfDay()]);

        $lowStockQuery = ProductVariant::query()
            ->where('is_active', true)
            ->lowStock();

        $stats = [
            'employees' => $shop ? $shop->users()->where('id', '!=', $user->id)->where('is_active', true)->count() : 0,
            'today_revenue' => (float) (clone $todaySales)->sum('total'),
            'today_sales' => (clone $todaySales)->count(),
            'low_stock' => (clone $lowStockQuery)->count(),
        ];

        $recentSales = Sale::query()
            ->with('cashier:id,name')
            ->latest('sold_at')
            ->limit(6)
            ->get();

        $lowStockVariants = (clone $lowStockQuery)
            ->with('product:id,name')
            ->orderBy('stock_on_hand')
            ->orderBy('id')
            ->limit(6)
            ->get();

        return view('owner.dashboard', [
            'shop' => $shop,
            'stats' => $stats,
            'recentSales' => $recentSales,
            'lowStockVariants' => $lowStockVariants,
        ]);
    }

    public function employees(Request $request): View
    {
        $user = $request->user();
        $shop = $user->shop;

        $search = trim((string) $request->query('q', ''));

        $employees = $shop
            ? $shop->users()
                ->where('id', '!=', $user->id)
                ->when($search !== '', function ($query) use ($search): void {
                    $term = str_replace(['%', '_'], ['\%', '\_'], $search);

                    $query->where(function ($q) use ($term): void {
                        $q->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    });
                })
                ->orderBy('name')
                ->get()
            : collect();

        return view('owner.employees', [
            'shop' => $shop,
            'employees' => $employees,
            'search' => $search,
        ]);
    }

    public function reports(Request $request): View
    {
        return view('owner.reports', $this->reportData($request));
    }

    public function reportPrint(Request $request): View
    {
        return view('owner.reports-print', $this->reportData($request) + [
            'autoprint' => $request->boolean('print'),
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $data = $this->reportExportData($request);

        $spreadsheet = (new OwnerReportExcel($data))->build();
        $writer = new Xlsx($spreadsheet);

        $path = tempnam(sys_get_temp_dir(), 'ustbosh_report');
        $writer->save($path);

        $fileName = 'hisobot_'.$data['from']->format('Y-m-d').'_'.$data['to']->format('Y-m-d').'.xlsx';

        return response()->download($path, $fileName)->deleteFileAfterSend();
    }

    /**
     * Hisobot sahifasi, chop etish va eksport uchun umumiy ma'lumotlar.
     *
     * @return array<string, mixed>
     */
    private function reportData(Request $request): array
    {
        $shop = $request->user()->shop;

        [$from, $to] = $this->resolveReportPeriod($request);

        $sales = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sold_at', [$from, $to]);

        $revenue = (float) (clone $sales)->sum('total');
        $salesCount = (clone $sales)->count();

        $paymentCash = (float) (clone $sales)->where('payment_method', Sale::PAYMENT_CASH)->sum('total');
        $paymentCard = (float) (clone $sales)->where('payment_method', Sale::PAYMENT_CARD)->sum('total');
        $paymentTotal = $paymentCash + $paymentCard;
        $cashPercent = $paymentTotal > 0 ? (int) round($paymentCash / $paymentTotal * 100) : 0;

        $topProducts = SaleItem::query()
            ->whereIn('sale_id', (clone $sales)->select('id'))
            ->selectRaw('product_name_snapshot, SUM(quantity) as quantity_sum, SUM(line_total) as revenue_sum')
            ->groupBy('product_name_snapshot')
            ->orderByDesc('revenue_sum')
            ->limit(8)
            ->get();

        $dailySales = (clone $sales)
            ->selectRaw('DATE(sold_at) as day, COUNT(*) as sales_count, SUM(total) as revenue_sum')
            ->groupBy('day')
            ->orderByDesc('day')
            ->get();

        $lowStockQuery = ProductVariant::query()
            ->where('is_active', true)
            ->lowStock();

        $lowStockVariants = (clone $lowStockQuery)
            ->with('product:id,name')
            ->orderBy('stock_on_hand')
            ->orderBy('id')
            ->limit(12)
            ->get();

        $exportLimit = (int) config('reports.max_export_sales');

        return [
            'shop' => $shop,
            'from' => $from,
            'to' => $to,
            'range' => $this->currentRange($request),
            'exportLimit' => $exportLimit,
            'exportTruncated' => Sale::query()
                ->whereBetween('sold_at', [$from, $to])
                ->count() > $exportLimit,
            'stats' => [
                'revenue' => $revenue,
                'sales' => $salesCount,
                'average_check' => $salesCount > 0 ? $revenue / $salesCount : 0.0,
                'items' => (int) SaleItem::query()->whereIn('sale_id', (clone $sales)->select('id'))->sum('quantity'),
                'low_stock' => (clone $lowStockQuery)->count(),
            ],
            'paymentSplit' => [
                'cash' => $paymentCash,
                'card' => $paymentCard,
                'cash_percent' => $cashPercent,
                'card_percent' => $paymentTotal > 0 ? 100 - $cashPercent : 0,
            ],
            'topProducts' => $topProducts,
            'dailySales' => $dailySales,
            'lowStockVariants' => $lowStockVariants,
        ];
    }

    /**
     * Excel eksporti uchun to'liq ma'lumotlar: davrdagi barcha sotuvlar,
     * barcha kunlar, barcha top mahsulotlar va barcha kam qolgan variantlar.
     *
     * @return array<string, mixed>
     */
    private function reportExportData(Request $request): array
    {
        $shop = $request->user()->shop;

        [$from, $to] = $this->resolveReportPeriod($request);

        $exportLimit = (int) config('reports.max_export_sales');

        $salesTotal = Sale::query()
            ->whereBetween('sold_at', [$from, $to])
            ->count();

        // Xotirani cheklash uchun sotuvlar varag'iga eng oxirgi yozuvlar tushadi
        $sales = Sale::query()
            ->with('cashier:id,name')
            ->withCount('items')
            ->whereBetween('sold_at', [$from, $to])
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->limit($exportLimit)
            ->get()
            ->sortBy('sold_at')
            ->values();

        $completedSales = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sold_at', [$from, $to]);

        $salesCount = (clone $completedSales)->count();
        $revenue = (float) (clone $completedSales)->sum('total');

        $paymentCash = (float) (clone $completedSales)->where('payment_method', Sale::PAYMENT_CASH)->sum('total');
        $paymentCard = (float) (clone $completedSales)->where('payment_method', Sale::PAYMENT_CARD)->sum('total');
        $paymentTotal = $paymentCash + $paymentCard;
        $cashPercent = $paymentTotal > 0 ? (int) round($paymentCash / $paymentTotal * 100) : 0;

        $completedSaleIds = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sold_at', [$from, $to])
            ->select('id');

        $dailySales = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sold_at', [$from, $to])
            ->selectRaw('DATE(sold_at) as day, COUNT(*) as sales_count, SUM(total) as revenue_sum')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $topProductsBase = SaleItem::query()->whereIn('sale_id', $completedSaleIds);
        $topProductsTotal = (clone $topProductsBase)->distinct()->count('product_name_snapshot');

        $topProducts = (clone $topProductsBase)
            ->selectRaw('product_name_snapshot, SUM(quantity) as quantity_sum, SUM(line_total) as revenue_sum')
            ->groupBy('product_name_snapshot')
            ->orderByDesc('revenue_sum')
            ->limit((int) config('reports.max_export_products'))
            ->get();

        $lowStockBase = ProductVariant::query()
            ->where('is_active', true)
            ->lowStock();

        $lowStockTotal = (clone $lowStockBase)->count();

        $lowStockVariants = (clone $lowStockBase)
            ->with('product:id,name')
            ->orderBy('stock_on_hand')
            ->orderBy('id')
            ->limit((int) config('reports.max_export_low_stock'))
            ->get();

        return [
            'shop' => $shop,
            'from' => $from,
            'to' => $to,
            'generatedAt' => now(),
            'sales' => $sales,
            'sales_total' => $salesTotal,
            'sales_truncated' => $salesTotal > $sales->count(),
            'stats' => [
                'revenue' => $revenue,
                'sales' => $salesCount,
                'average_check' => $salesCount > 0 ? $revenue / $salesCount : 0.0,
                'items' => (int) SaleItem::query()->whereIn('sale_id', (clone $completedSaleIds))->sum('quantity'),
                'low_stock' => $lowStockTotal,
            ],
            'paymentSplit' => [
                'cash' => $paymentCash,
                'card' => $paymentCard,
                'cash_percent' => $cashPercent,
                'card_percent' => $paymentTotal > 0 ? 100 - $cashPercent : 0,
            ],
            'dailySales' => $dailySales,
            'topProducts' => $topProducts,
            'products_total' => $topProductsTotal,
            'products_truncated' => $topProductsTotal > $topProducts->count(),
            'lowStockVariants' => $lowStockVariants,
            'low_stock_total' => $lowStockTotal,
            'low_stock_truncated' => $lowStockTotal > $lowStockVariants->count(),
        ];
    }

    public function activity(Request $request): View
    {
        $shop = $request->user()->shop;

        $search = trim((string) $request->query('q', ''));
        $sort = $request->query('sort', 'desc') === 'asc' ? 'asc' : 'desc';
        $role = $request->query('role', 'all');

        $allowedRoles = [User::ROLE_OWNER, User::ROLE_CASHIER, User::ROLE_WAREHOUSE];
        if (! in_array($role, $allowedRoles, true)) {
            $role = 'all';
        }

        $loginEvents = $shop
            ? LoginEvent::with('user')
                ->where('shop_id', $shop->id)
                ->when($search !== '', function ($query) use ($search): void {
                    $term = str_replace(['%', '_'], ['\%', '\_'], $search);

                    $query->whereHas('user', function ($q) use ($term): void {
                        $q->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    });
                })
                ->when($role !== 'all', function ($query) use ($role): void {
                    $query->whereHas('user', function ($q) use ($role): void {
                        $q->where('role', $role);
                    });
                })
                ->orderBy('logged_in_at', $sort)
                ->paginate(15)
                ->withQueryString()
            : LoginEvent::query()->whereNull('id')->paginate(15);

        $stats = [
            'logins_today' => $shop
                ? LoginEvent::where('shop_id', $shop->id)->whereDate('logged_in_at', now()->toDateString())->count()
                : 0,
            'logins_total' => $shop ? LoginEvent::where('shop_id', $shop->id)->count() : 0,
            'must_change' => $shop
                ? $shop->users()->where('must_change_password', true)->count()
                : 0,
        ];

        return view('owner.activity', [
            'shop' => $shop,
            'loginEvents' => $loginEvents,
            'loginSearch' => $search,
            'loginSort' => $sort,
            'loginRole' => $role,
            'stats' => $stats,
        ]);
    }

    public function sales(Request $request): View
    {
        $shop = $request->user()->shop;

        [$from, $to] = $this->resolveReportPeriod($request);

        $search = trim((string) $request->query('q', ''));
        $cashierId = (int) $request->query('cashier', 0);

        $payment = (string) $request->query('payment', 'all');
        if (! in_array($payment, ['all', Sale::PAYMENT_CASH, Sale::PAYMENT_CARD], true)) {
            $payment = 'all';
        }

        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', Sale::STATUS_COMPLETED, Sale::STATUS_VOIDED], true)) {
            $status = 'all';
        }

        $cashiers = $shop
            ? $shop->users()->whereIn('role', [User::ROLE_OWNER, User::ROLE_CASHIER])->orderBy('name')->get(['id', 'name'])
            : collect();

        if ($cashierId !== 0 && ! $cashiers->contains('id', $cashierId)) {
            $cashierId = 0;
        }

        $sales = Sale::query()
            ->with('cashier:id,name')
            ->withCount('items')
            ->whereBetween('sold_at', [$from, $to])
            ->when($search !== '', function ($query) use ($search): void {
                $term = str_replace(['%', '_'], ['\%', '\_'], $search);
                $query->where('sale_no', 'like', "%{$term}%");
            })
            ->when($cashierId !== 0, fn ($query) => $query->where('cashier_id', $cashierId))
            ->when($payment !== 'all', fn ($query) => $query->where('payment_method', $payment))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByDesc('sold_at')
            ->paginate(20)
            ->withQueryString();

        return view('owner.sales', [
            'shop' => $shop,
            'sales' => $sales,
            'cashiers' => $cashiers,
            'search' => $search,
            'cashierId' => $cashierId,
            'payment' => $payment,
            'status' => $status,
            'range' => $this->currentRange($request),
            'from' => $from,
            'to' => $to,
            'canVoidSales' => (bool) $shop?->owner_can_void_sales,
        ]);
    }

    public function saleShow(Request $request, Sale $sale): View
    {
        $shop = $request->user()->shop;

        $sale->load(['items', 'cashier:id,name', 'voider:id,name']);

        return view('owner.sale-show', [
            'shop' => $shop,
            'sale' => $sale,
            'canVoid' => $sale->status === Sale::STATUS_COMPLETED && (bool) $shop?->owner_can_void_sales,
            'autoprint' => $request->boolean('print'),
        ]);
    }

    public function voidSale(Request $request, Sale $sale): RedirectResponse|JsonResponse
    {
        $shop = $request->user()->shop;

        // Egasining bekor qilish huquqi sozlamasi o'chiq bo'lsa — ruxsat yo'q
        abort_unless($shop && $shop->owner_can_void_sales, 403, __('owner.void_not_allowed'));

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'reason.required' => __('pos.void_reason_required'),
            'reason.min' => __('pos.void_reason_required'),
        ]);

        $sale->voidBy($request->user(), $validated['reason']);

        $message = __('pos.void_success');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect' => route('owner.sales.show', $sale),
            ]);
        }

        return redirect()->route('owner.sales.show', $sale)->with('success', $message);
    }

    public function updateVoidSetting(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        abort_unless($shop, 403);

        $validated = $request->validate([
            'owner_can_void_sales' => ['required', 'boolean'],
        ]);

        $shop->update([
            'owner_can_void_sales' => (bool) $validated['owner_can_void_sales'],
        ]);

        return back()->with(
            'success',
            $shop->owner_can_void_sales ? __('owner.void_setting_enabled') : __('owner.void_setting_disabled')
        );
    }

    public function inventory(Request $request): View
    {
        $shop = $request->user()->shop;

        $search = trim((string) $request->query('q', ''));

        $products = Product::query()
            ->withCount(['variants' => fn ($q) => $q->where('is_active', true)])
            ->withSum('variants as total_stock', 'stock_on_hand')
            ->when($search !== '', function ($query) use ($search): void {
                $term = str_replace(['%', '_'], ['\%', '\_'], $search);

                $query->where(function ($q) use ($term): void {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%")
                        ->orWhere('category', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $lowStockVariants = ProductVariant::query()
            ->with('product:id,name')
            ->where('is_active', true)
            ->lowStock()
            ->orderBy('stock_on_hand')
            ->orderBy('id')
            ->limit(20)
            ->get();

        $outOfStockVariants = ProductVariant::query()
            ->with('product:id,name')
            ->where('is_active', true)
            ->outOfStock()
            ->orderBy('id')
            ->limit(20)
            ->get();

        $activeVariants = ProductVariant::query()->where('is_active', true);

        $stats = [
            'products' => Product::query()->where('is_active', true)->count(),
            'total_stock' => (int) ProductVariant::query()->sum('stock_on_hand'),
            'low_stock' => (clone $activeVariants)->lowStock()->count(),
            'out_of_stock' => (clone $activeVariants)->outOfStock()->count(),
        ];

        return view('owner.inventory', [
            'shop' => $shop,
            'products' => $products,
            'lowStockVariants' => $lowStockVariants,
            'outOfStockVariants' => $outOfStockVariants,
            'stats' => $stats,
            'search' => $search,
        ]);
    }

    public function updateEmployee(Request $request, User $employee): RedirectResponse|JsonResponse
    {
        $owner = $request->user();

        $this->ensureManagedEmployee($owner, $employee);

        $input = $request->all();
        $input['email'] = strtolower(trim((string) ($input['email'] ?? '')));

        $validated = validator($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($employee->id)],
            'role' => ['required', Rule::in([User::ROLE_CASHIER, User::ROLE_WAREHOUSE])],
        ], [
            'email.unique' => __('owner.email_taken'),
            'role.in' => __('owner.role_invalid'),
        ])->validate();

        $employee->update($validated);

        $message = __('owner.employee_updated', ['name' => $employee->name]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function toggleEmployee(Request $request, User $employee): RedirectResponse|JsonResponse
    {
        $owner = $request->user();

        $this->ensureManagedEmployee($owner, $employee);

        // Joriy holatni bazadan olamiz — model atributi har doim yuklangan bo'lmasligi mumkin
        $employee->refresh();

        $employee->update(['is_active' => ! $employee->is_active]);

        // Faolsizlantirilganda barcha sessiyalari yopiladi
        if (! $employee->is_active) {
            $employee->revokeAllSessions();
        }

        $message = $employee->is_active
            ? __('owner.employee_activated', ['name' => $employee->name])
            : __('owner.employee_deactivated', ['name' => $employee->name]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    /**
     * Faqat shu do'konga tegishli kassir yoki skladchini boshqarish mumkin.
     */
    private function ensureManagedEmployee(User $owner, User $employee): void
    {
        abort_unless($owner->shop && $owner->shop_id === $employee->shop_id, 403);
        abort_if($employee->id === $owner->id, 403);
        abort_unless(in_array($employee->role, [User::ROLE_CASHIER, User::ROLE_WAREHOUSE], true), 403);
    }

    public function storeEmployee(Request $request): RedirectResponse|JsonResponse
    {
        // Emailni yagona formatga keltiramiz — unique tekshiruvi ham, login ham shu formatda
        $input = $request->all();
        $input['email'] = strtolower(trim((string) ($input['email'] ?? '')));

        $validated = validator($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'role' => ['required', Rule::in([User::ROLE_CASHIER, User::ROLE_WAREHOUSE])],
        ], [
            'email.unique' => __('owner.email_taken'),
            'role.in' => __('owner.role_invalid'),
        ])->validate();

        $user = $request->user();
        $shop = $user->shop;

        abort_unless($shop && $shop->isActive(), 403);

        $temporaryPassword = Str::password(12, symbols: false);

        $employee = $shop->users()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $temporaryPassword,
            'role' => $validated['role'],
            'must_change_password' => true,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('owner.employee_created', ['name' => $employee->name]),
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'role' => $employee->role,
                ],
                'temporary_password' => $temporaryPassword,
            ], 201);
        }

        return back()
            ->with('success', __('owner.employee_created', ['name' => $employee->name]))
            ->with('employee_password', $temporaryPassword);
    }

    public function regeneratePassword(Request $request, User $employee): RedirectResponse|JsonResponse
    {
        $owner = $request->user();

        // Middleware do'kon faolligini kafolatlaydi, shu bois shop_id null bo'lishi mumkin emas
        abort_unless($owner->shop && $owner->shop_id === $employee->shop_id, 403);
        abort_if($employee->id === $owner->id, 403);
        abort_unless(in_array($employee->role, [User::ROLE_CASHIER, User::ROLE_WAREHOUSE], true), 403);

        $temporaryPassword = Str::password(12, symbols: false);

        $employee->update([
            'password' => $temporaryPassword,
            'must_change_password' => true,
        ]);

        // Barcha eski sessiyalari va remember token bekor qilinadi
        $employee->revokeAllSessions();

        $message = __('owner.regenerated_password', ['password' => $temporaryPassword]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('owner.password_regenerated', ['name' => $employee->name]),
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'role' => $employee->role,
                ],
                'temporary_password' => $temporaryPassword,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Faqat ruxsat etilgan davrni qaytaradi.
     */
    private function currentRange(Request $request): string
    {
        $range = (string) $request->query('range', '7d');

        return in_array($range, self::REPORT_RANGES, true) ? $range : '7d';
    }

    /**
     * Tanlangan davrning boshlanish va tugash vaqtlari.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveReportPeriod(Request $request): array
    {
        $now = now();

        return match ($this->currentRange($request)) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'custom' => $this->resolveCustomPeriod($request, $now),
            default => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    /**
     * "Boshqa davr" uchun sana maydonlarini xavfsiz o'qish.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveCustomPeriod(Request $request, Carbon $now): array
    {
        $from = $this->parseReportDate($request->query('from'), $now->copy()->subDays(6)->startOfDay(), false);
        $to = $this->parseReportDate($request->query('to'), $now->copy()->endOfDay(), true);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        // Eng uzun davr — MAX_REPORT_DAYS kalendar kuni (boshlanish va tugash kunlari bilan)
        if ($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) >= self::MAX_REPORT_DAYS) {
            $from = $to->copy()->startOfDay()->subDays(self::MAX_REPORT_DAYS - 1);
        }

        return [$from, $to];
    }

    private function parseReportDate(mixed $value, Carbon $fallback, bool $endOfDay): Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        try {
            $date = Carbon::parse($value);
        } catch (Throwable) {
            return $fallback;
        }

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }
}
