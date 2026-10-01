<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\InventoryMovement;
use App\Models\LoginEvent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Scopes\TenantScope;
use App\Models\Shop;
use App\Models\StockReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Super admin uchun do'konlarni boshqarish: profil, holat, egalar/xodimlar
 * va nazoratli zaxira tuzatishlari. Barcha o'zgarishlar audit jurnaliga yoziladi.
 */
class AdminShopController extends Controller
{
    public function show(Shop $shop): View
    {
        $users = $shop->users()->orderBy('name')->get();

        $stats = [
            'users' => $users->count(),
            'active_users' => $users->where('is_active', true)->count(),
            'products' => Product::withoutGlobalScope(TenantScope::class)->where('shop_id', $shop->id)->count(),
            'stock' => (int) ProductVariant::withoutGlobalScope(TenantScope::class)->where('shop_id', $shop->id)->sum('stock_on_hand'),
            'sales_30d' => (float) $this->shopSalesQuery($shop)
                ->where('status', Sale::STATUS_COMPLETED)
                ->where('sold_at', '>=', now()->subDays(30))
                ->sum('total'),
        ];

        $recentSales = $this->shopSalesQuery($shop)
            ->with('cashier:id,name')
            ->latest('sold_at')
            ->limit(10)
            ->get();

        $products = Product::withoutGlobalScope(TenantScope::class)
            ->where('shop_id', $shop->id)
            ->withCount('variants')
            ->latest()
            ->paginate(10, ['*'], 'products_page')
            ->withQueryString();

        $lowStockVariants = ProductVariant::withoutGlobalScope(TenantScope::class)
            ->where('shop_id', $shop->id)
            ->with(['product' => fn ($query) => $query->withoutGlobalScope(TenantScope::class)])
            ->where('is_active', true)
            ->lowStock()
            ->orderBy('stock_on_hand')
            ->orderBy('id')
            ->limit(10)
            ->get();

        $recentReceipts = StockReceipt::withoutGlobalScope(TenantScope::class)
            ->where('shop_id', $shop->id)
            ->with('creator:id,name')
            ->latest('received_at')
            ->limit(5)
            ->get();

        $loginEvents = LoginEvent::query()
            ->where('shop_id', $shop->id)
            ->with('user:id,name')
            ->latest('logged_in_at')
            ->limit(5)
            ->get();

        $auditLogs = AdminAuditLog::query()
            ->where('shop_id', $shop->id)
            ->with('actor:id,name')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('admin.shop-show', [
            'shop' => $shop,
            'users' => $users,
            'stats' => $stats,
            'recentSales' => $recentSales,
            'products' => $products,
            'lowStockVariants' => $lowStockVariants,
            'recentReceipts' => $recentReceipts,
            'loginEvents' => $loginEvents,
            'auditLogs' => $auditLogs,
        ]);
    }

    public function update(Request $request, Shop $shop): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\(\) ]{7,32}$/'],
        ], [
            'name.required' => __('admin.shop_name_required'),
            'phone.regex' => __('landing.validation.phone_regex'),
        ]);

        $before = ['name' => $shop->name, 'phone' => $shop->phone];

        $shop->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] !== null ? trim($validated['phone']) : null,
        ]);

        AdminAuditLog::record($request, 'shop.updated', $shop, $shop, $before, [
            'name' => $shop->name,
            'phone' => $shop->phone,
        ]);

        return back()->with('success', __('admin.flash_shop_updated'));
    }

    public function suspendShop(Request $request, Shop $shop): RedirectResponse|JsonResponse
    {
        return $this->setShopStatus($request, $shop, Shop::STATUS_SUSPENDED);
    }

    public function activateShop(Request $request, Shop $shop): RedirectResponse|JsonResponse
    {
        return $this->setShopStatus($request, $shop, Shop::STATUS_ACTIVE);
    }

    public function toggleUser(Request $request, Shop $shop, User $user): RedirectResponse|JsonResponse
    {
        $this->ensureShopUser($shop, $user);

        $user->refresh();
        $before = $user->is_active;

        $user->update(['is_active' => ! $before]);

        if (! $user->is_active) {
            $user->revokeAllSessions();
        }

        AdminAuditLog::record(
            $request,
            $user->is_active ? 'user.activated' : 'user.deactivated',
            $shop,
            $user,
            ['is_active' => $before],
            ['is_active' => $user->is_active],
        );

        $message = $user->is_active
            ? __('admin.flash_user_activated', ['name' => $user->name])
            : __('admin.flash_user_deactivated', ['name' => $user->name]);

        return $this->jsonOrBack($request, $message, ['user_id' => $user->id, 'is_active' => $user->is_active]);
    }

    public function resetUserPassword(Request $request, Shop $shop, User $user): RedirectResponse|JsonResponse
    {
        $this->ensureShopUser($shop, $user);

        abort_unless($user->canReceivePasswordReset(), 403);

        try {
            $token = Password::broker()->createToken($user);
            $user->sendPasswordResetNotification($token);
            $mailSent = true;
        } catch (Throwable $e) {
            report($e);
            $mailSent = false;
        }

        AdminAuditLog::record($request, 'user.password_reset', $shop, $user);

        $message = $mailSent
            ? __('admin.flash_password_reset_sent', ['name' => $user->name])
            : __('admin.flash_password_reset_failed');

        return $this->jsonOrBack($request, $message, [
            'user_id' => $user->id,
            'mail_failed' => ! $mailSent,
            'warning' => ! $mailSent,
        ], $mailSent ? 'success' : 'warning');
    }

    public function editProduct(Shop $shop, int $product): View
    {
        $target = Product::withoutGlobalScope(TenantScope::class)
            ->where('shop_id', $shop->id)
            ->findOrFail($product);

        $target->load([
            'variants' => fn ($query) => $query
                ->withoutGlobalScope(TenantScope::class)
                ->orderBy('size')
                ->orderBy('id'),
        ]);

        return view('admin.product-edit', [
            'shop' => $shop,
            'product' => $target,
        ]);
    }

    public function updateProduct(Request $request, Shop $shop, int $product): RedirectResponse
    {
        $input = $request->all();
        $input['sku'] = strtoupper(trim((string) ($input['sku'] ?? '')));

        $validated = validator($input, [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')->where('shop_id', $shop->id)->ignore($product)],
            'category' => ['nullable', 'string', 'max:100'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'sku.unique' => __('warehouse.sku_taken'),
        ])->validate();

        $target = Product::withoutGlobalScope(TenantScope::class)
            ->where('shop_id', $shop->id)
            ->findOrFail($product);

        $before = [
            'name' => $target->name,
            'sku' => $target->sku,
            'category' => $target->category,
            'sale_price' => (float) $target->sale_price,
            'is_active' => $target->is_active,
        ];

        $target->update([
            'name' => $validated['name'],
            'sku' => $validated['sku'],
            'category' => ($validated['category'] ?? '') !== '' ? trim($validated['category']) : null,
            'sale_price' => $validated['sale_price'],
            'is_active' => $validated['is_active'] ?? $target->is_active,
        ]);

        AdminAuditLog::record($request, 'product.updated', $shop, $target, $before, [
            'name' => $target->name,
            'sku' => $target->sku,
            'category' => $target->category,
            'sale_price' => (float) $target->sale_price,
            'is_active' => $target->is_active,
        ]);

        return redirect()
            ->route('admin.shops.show', $shop)
            ->with('success', __('admin.flash_product_updated', ['name' => $target->name]));
    }

    public function voidSale(Request $request, Shop $shop, int $sale): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'reason.required' => __('pos.void_reason_required'),
            'reason.min' => __('pos.void_reason_required'),
        ]);

        $target = Sale::withoutGlobalScope(TenantScope::class)
            ->where('shop_id', $shop->id)
            ->findOrFail($sale);

        abort_unless($target->status === Sale::STATUS_COMPLETED, 409, __('pos.void_already'));

        $target->voidBy($request->user(), $validated['reason']);

        AdminAuditLog::record(
            $request,
            'sale.voided',
            $shop,
            $target,
            ['status' => Sale::STATUS_COMPLETED],
            ['status' => Sale::STATUS_VOIDED],
            $validated['reason'],
        );

        return $this->jsonOrBack($request, __('pos.void_success'), ['sale_id' => $target->id]);
    }

    public function adjustStock(Request $request, Shop $shop, int $variant): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'delta.not_in' => __('admin.adjustment_delta_zero'),
            'reason.required' => __('admin.adjustment_reason_required'),
            'reason.min' => __('admin.adjustment_reason_required'),
        ]);

        $delta = (int) $validated['delta'];

        $adjusted = DB::transaction(function () use ($request, $shop, $variant, $delta, $validated): ProductVariant {
            $locked = ProductVariant::withoutGlobalScope(TenantScope::class)
                ->where('shop_id', $shop->id)
                ->lockForUpdate()
                ->findOrFail($variant);

            $before = $locked->stock_on_hand;
            $after = $before + $delta;

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'delta' => __('admin.adjustment_negative_stock', ['stock' => $before]),
                ]);
            }

            $locked->update(['stock_on_hand' => $after]);

            InventoryMovement::create([
                'shop_id' => $shop->id,
                'product_variant_id' => $locked->id,
                'movement_type' => InventoryMovement::TYPE_ADJUSTMENT,
                'quantity_delta' => $delta,
                'stock_after' => $after,
                'reference_type' => ProductVariant::class,
                'reference_id' => $locked->id,
                'reference_line_id' => null,
                'movement_key' => 'adjustment-'.$locked->id.'-'.Str::lower(Str::random(12)),
                'actor_id' => $request->user()->id,
                'created_at' => now(),
            ]);

            AdminAuditLog::record(
                $request,
                'variant.stock_adjusted',
                $shop,
                $locked,
                ['stock_on_hand' => $before],
                ['stock_on_hand' => $after],
                $validated['reason'],
            );

            return $locked;
        });

        $message = __('admin.adjustment_saved', [
            'name' => Product::withoutGlobalScope(TenantScope::class)->whereKey($adjusted->product_id)->value('name') ?? '',
            'delta' => ($delta > 0 ? '+' : '').$delta,
        ]);

        return $this->jsonOrBack($request, $message, ['variant_id' => $adjusted->id]);
    }

    private function setShopStatus(Request $request, Shop $shop, string $status): RedirectResponse|JsonResponse
    {
        $before = $shop->status;

        $shop->update(['status' => $status]);

        AdminAuditLog::record(
            $request,
            $status === Shop::STATUS_SUSPENDED ? 'shop.suspended' : 'shop.activated',
            $shop,
            $shop,
            ['status' => $before],
            ['status' => $shop->status],
        );

        $message = $status === Shop::STATUS_SUSPENDED
            ? __('admin.flash_shop_suspended', ['shop' => $shop->name])
            : __('admin.flash_shop_activated', ['shop' => $shop->name]);

        return $this->jsonOrBack($request, $message, ['shop_id' => $shop->id, 'status' => $shop->status]);
    }

    private function ensureShopUser(Shop $shop, User $user): void
    {
        abort_unless($user->shop_id === $shop->id, 404);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function jsonOrBack(Request $request, string $message, array $extra = [], string $flashKey = 'success'): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message] + $extra);
        }

        // Referer bo'lmasa — admin dashboardga qaytamiz
        return redirect()->back(302, [], route('admin.dashboard'))->with($flashKey, $message);
    }

    private function shopSalesQuery(Shop $shop): Builder
    {
        return Sale::withoutGlobalScope(TenantScope::class)->where('shop_id', $shop->id);
    }
}
