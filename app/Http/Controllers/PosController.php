<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\DatabaseError;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PosController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $products = $this->searchProducts($search, 50);

        return view('pos.index', [
            'products' => $products,
            'productsJson' => $products->map(fn (Product $product): array => $this->productPayload($product))->values()->all(),
            'search' => $search,
        ]);
    }

    public function catalog(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $products = $this->searchProducts($search, 50);

        return response()->json([
            'success' => true,
            'products' => $products->map(fn (Product $product): array => $this->productPayload($product)),
        ]);
    }

    public function storeSale(Request $request): JsonResponse
    {
        $user = $request->user();
        $shopId = $user->shop_id;

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in([Sale::PAYMENT_CASH, Sale::PAYMENT_CARD])],
            'amount_received' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.variant_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ], [
            'items.required' => __('pos.cart_empty'),
            'items.min' => __('pos.cart_empty'),
            'payment_method.in' => __('pos.payment_invalid'),
            'amount_received.numeric' => __('pos.amount_received_invalid'),
        ]);

        // Xuddi shu idempotency key bilan oldin yakunlangan sotuv bo'lsa —
        // qayta ishlamaymiz, mavjud sotuvni qaytaramiz.
        $existing = Sale::query()
            ->where('cashier_id', $user->id)
            ->where('idempotency_key', $validated['idempotency_key'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => __('pos.sale_already_processed'),
                'sale' => $this->salePayload($existing),
            ]);
        }

        // Bitta variant bir necha marta kelsa — miqdorlarni jamlaymiz
        $lines = [];
        foreach ($validated['items'] as $item) {
            $id = (int) $item['variant_id'];
            $lines[$id] = ($lines[$id] ?? 0) + (int) $item['quantity'];
        }

        $amountReceived = $validated['amount_received'] ?? null;

        try {
            $sale = DB::transaction(function () use ($lines, $validated, $user, $shopId, $amountReceived): Sale {
                $variants = ProductVariant::query()
                    ->whereIn('id', array_keys($lines))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $sale = Sale::create([
                    'shop_id' => $shopId,
                    'sale_no' => 'S-'.strtoupper(Str::random(10)),
                    'status' => Sale::STATUS_COMPLETED,
                    'cashier_id' => $user->id,
                    'sold_at' => now(),
                    'payment_method' => $validated['payment_method'],
                    'subtotal' => 0,
                    'discount_amount' => 0,
                    'total' => 0,
                    'idempotency_key' => $validated['idempotency_key'],
                ]);

                $subtotal = 0.0;

                foreach ($lines as $variantId => $quantity) {
                    $variant = $variants[$variantId] ?? null;

                    if (! $variant) {
                        throw ValidationException::withMessages([
                            'items' => __('pos.variant_not_found'),
                        ]);
                    }

                    if (! $variant->is_active) {
                        throw ValidationException::withMessages([
                            'items' => __('pos.variant_inactive', ['name' => $variant->product?->name ?? '', 'label' => $variant->displayLabel()]),
                        ]);
                    }

                    if ($variant->stock_on_hand < $quantity) {
                        abort(409, __('pos.insufficient_stock', [
                            'name' => $variant->product?->name ?? '',
                            'label' => $variant->displayLabel(),
                            'stock' => $variant->stock_on_hand,
                        ]));
                    }

                    $lineTotal = (float) $variant->product->sale_price * $quantity;
                    $subtotal += $lineTotal;

                    $item = SaleItem::create([
                        'shop_id' => $shopId,
                        'sale_id' => $sale->id,
                        'product_variant_id' => $variant->id,
                        'product_name_snapshot' => $variant->product->name,
                        'sku_snapshot' => $variant->product->sku,
                        'size_snapshot' => $variant->size,
                        'color_snapshot' => $variant->color,
                        'unit_price' => $variant->product->sale_price,
                        'quantity' => $quantity,
                        'line_total' => $lineTotal,
                    ]);

                    $variant->stock_on_hand -= $quantity;
                    $variant->save();

                    InventoryMovement::create([
                        'shop_id' => $shopId,
                        'product_variant_id' => $variant->id,
                        'movement_type' => InventoryMovement::TYPE_SALE,
                        'quantity_delta' => -$quantity,
                        'stock_after' => $variant->stock_on_hand,
                        'reference_type' => Sale::class,
                        'reference_id' => $sale->id,
                        'reference_line_id' => $item->id,
                        'movement_key' => 'sale-'.$sale->id.'-'.$variant->id,
                        'actor_id' => $user->id,
                        'created_at' => now(),
                    ]);
                }

                // Naqd savdoda olingan summa kiritilgan bo'lsa — qaytimni hisoblaymiz
                $changeAmount = null;
                $receivedForSale = null;

                if ($validated['payment_method'] === Sale::PAYMENT_CASH && $amountReceived !== null) {
                    if ((float) $amountReceived < $subtotal) {
                        throw ValidationException::withMessages([
                            'amount_received' => __('pos.amount_received_insufficient'),
                        ]);
                    }

                    $receivedForSale = $amountReceived;
                    $changeAmount = round((float) $amountReceived - $subtotal, 2);
                }

                $sale->update([
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                    'amount_received' => $receivedForSale,
                    'change_amount' => $changeAmount,
                ]);

                return $sale;
            });
        } catch (QueryException $e) {
            // Parallel so'rov unique kalitga urilsa — birinchi yakunlangan sotuvni qaytaramiz.
            if (! DatabaseError::isUniqueViolation($e)) {
                throw $e;
            }

            $existing = Sale::query()
                ->where('cashier_id', $user->id)
                ->where('idempotency_key', $validated['idempotency_key'])
                ->first();

            if (! $existing) {
                throw $e;
            }

            return response()->json([
                'success' => true,
                'message' => __('pos.sale_already_processed'),
                'sale' => $this->salePayload($existing),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('pos.sale_saved'),
            'sale' => $this->salePayload($sale),
        ]);
    }

    public function salesIndex(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $date = $this->parseFilterDate($request->query('date'));

        $sales = Sale::query()
            ->with('cashier:id,name')
            ->withCount('items')
            ->when($search !== '', function ($query) use ($search): void {
                $term = str_replace(['%', '_'], ['\%', '\_'], $search);
                $query->where('sale_no', 'like', "%{$term}%");
            })
            ->when($date !== null, fn ($query) => $query->whereDate('sold_at', $date))
            ->orderByDesc('sold_at')
            ->paginate(20)
            ->withQueryString();

        return view('pos.sales', [
            'sales' => $sales,
            'search' => $search,
            'date' => $date,
        ]);
    }

    public function showSale(Request $request, Sale $sale): View
    {
        $sale->load(['items', 'cashier:id,name']);

        return view('pos.sale-show', [
            'sale' => $sale,
            'shop' => $request->user()->shop,
            'canVoid' => $sale->status === Sale::STATUS_COMPLETED
                && $sale->cashier_id === $request->user()->id,
            'autoprint' => $request->boolean('print'),
        ]);
    }

    public function voidSale(Request $request, Sale $sale): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        abort_unless($sale->cashier_id === $user->id, 403, __('pos.void_forbidden'));

        // Sotuvni shunchaki bekor qilib bo'lmaydi — sabab majburiy
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'reason.required' => __('pos.void_reason_required'),
            'reason.min' => __('pos.void_reason_required'),
        ]);

        // Bekor qilish logikasi Sale modelida — owner ham shu yo'ldan foydalanadi
        $sale->voidBy($user, $validated['reason']);

        $message = __('pos.void_success');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect' => route('pos.sales.show', $sale),
            ]);
        }

        return redirect()->route('pos.sales.show', $sale)->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function salePayload(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'sale_no' => $sale->sale_no,
            'total' => $sale->total,
            'items_count' => $sale->items()->count(),
            'amount_received' => $sale->amount_received,
            'change_amount' => $sale->change_amount,
        ];
    }

    private function parseFilterDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function searchProducts(string $search, int $limit)
    {
        return Product::query()
            ->active()
            ->with(['variants' => fn ($q) => $q->active()])
            ->when($search !== '', function ($query) use ($search): void {
                $term = str_replace(['%', '_'], ['\%', '\_'], $search);
                $query->where(function ($q) use ($term): void {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%")
                        ->orWhere('category', 'like', "%{$term}%")
                        ->orWhereHas('variants', fn ($v) => $v->where('barcode', 'like', "%{$term}%"));
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    private function productPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'category' => $product->category,
            'price' => $product->sale_price,
            'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'size' => $variant->size,
                'color' => $variant->color,
                'barcode' => $variant->barcode,
                'stock' => $variant->stock_on_hand,
                'label' => $variant->displayLabel(),
            ])->values()->all(),
        ];
    }
}
