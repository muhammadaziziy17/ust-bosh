<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Models\Scopes\TenantScope;
use App\Support\DatabaseError;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WarehouseDashboardController extends Controller
{
    /**
     * Ombor sahifasidagi tablar va harakat turlari.
     */
    private const TABS = ['inventory', 'receipts', 'movements'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $shopId = $user->shop_id;

        $search = trim((string) $request->query('q', ''));

        $tab = (string) $request->query('tab', 'inventory');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'inventory';
        }

        $movementType = (string) $request->query('movement_type', 'all');
        $allowedMovementTypes = [
            'all',
            InventoryMovement::TYPE_RECEIPT,
            InventoryMovement::TYPE_RECEIPT_CANCEL,
            InventoryMovement::TYPE_SALE,
            InventoryMovement::TYPE_SALE_VOID,
            InventoryMovement::TYPE_ADJUSTMENT,
        ];
        if (! in_array($movementType, $allowedMovementTypes, true)) {
            $movementType = 'all';
        }

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

        $activeVariants = ProductVariant::query()->where('is_active', true);

        $stats = [
            'products' => Product::query()->where('is_active', true)->count(),
            'total_stock' => (int) ProductVariant::query()->sum('stock_on_hand'),
            'low_stock' => (clone $activeVariants)->lowStock()->count(),
            'out_of_stock' => (clone $activeVariants)->outOfStock()->count(),
            'receipts_today' => StockReceipt::query()
                ->where('status', StockReceipt::STATUS_POSTED)
                ->whereDate('received_at', now()->toDateString())
                ->count(),
        ];

        $receipts = StockReceipt::query()
            ->with(['creator:id,name', 'canceller:id,name'])
            ->withCount('items')
            ->latest('received_at')
            ->paginate(15, ['*'], 'receipts_page')
            ->withQueryString();

        $movements = InventoryMovement::query()
            ->with([
                'variant:id,product_id,size,color',
                'variant.product:id,name',
                'actor:id,name',
            ])
            ->when($movementType !== 'all', fn ($query) => $query->where('movement_type', $movementType))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'movements_page')
            ->withQueryString();

        return view('warehouse.dashboard', [
            'user' => $user,
            'products' => $products,
            'stats' => $stats,
            'receipts' => $receipts,
            'movements' => $movements,
            'movementType' => $movementType,
            'search' => $search,
            'tab' => $tab,
            'sizes' => ProductVariant::SIZE_OPTIONS,
        ]);
    }

    public function createReceipt(Request $request): View
    {
        $hasVariants = ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->exists();

        return view('warehouse.receipt-create', [
            'hasVariants' => $hasVariants,
        ]);
    }

    public function searchVariants(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $variants = ProductVariant::query()
            ->with('product:id,name,sku,is_active')
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->when($search !== '', function ($query) use ($search): void {
                $term = str_replace(['%', '_'], ['\%', '\_'], $search);
                $query->where(function ($q) use ($term): void {
                    $q->where('barcode', 'like', "%{$term}%")
                        ->orWhereHas('product', function ($productQuery) use ($term): void {
                            $productQuery->where('name', 'like', "%{$term}%")
                                ->orWhere('sku', 'like', "%{$term}%")
                                ->orWhere('category', 'like', "%{$term}%");
                        });
                });
            })
            ->orderBy('product_id')
            ->orderBy('size')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'variants' => $variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'product_name' => $variant->product?->name ?? '—',
                'sku' => $variant->product?->sku ?? '—',
                'size' => $variant->size,
                'color' => $variant->color,
                'label' => $variant->displayLabel(),
                'stock' => $variant->stock_on_hand,
            ])->values()->all(),
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse|JsonResponse
    {
        $shopId = $request->user()->shop_id;

        // Normalizatsiya validatsiyadan OLDIN — SKU unique va razmer tekshiruvlari
        // katta-kichik harf farqini bilmasligi kerak
        $input = $this->normalizeProductInput($request->all());

        $validated = validator($input, [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')->where('shop_id', $shopId)],
            'category' => ['nullable', 'string', 'max:100'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'is_active' => ['sometimes', 'boolean'],
            'variants' => ['required', 'array', 'min:1', 'max:30'],
            'variants.*.size' => ['required', 'string', Rule::in(ProductVariant::SIZE_OPTIONS)],
            'variants.*.color' => ['nullable', 'string', 'max:50'],
            'variants.*.barcode' => ['nullable', 'string', 'max:64'],
            'variants.*.reorder_level' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], [
            'sku.unique' => __('warehouse.sku_taken'),
            'variants.required' => __('warehouse.variants_required'),
            'variants.min' => __('warehouse.variants_required'),
            'variants.*.size.in' => __('warehouse.size_invalid'),
        ])->validate();

        $validated['category'] = ($validated['category'] ?? '') !== '' ? trim($validated['category']) : null;

        $variants = array_map(function (array $variant): array {
            $variant['size'] = strtoupper(trim($variant['size']));
            $variant['color'] = ($variant['color'] ?? '') !== '' ? trim($variant['color']) : null;
            $variant['barcode'] = ($variant['barcode'] ?? '') !== '' ? trim($variant['barcode']) : $this->generateUniqueBarcode();
            $variant['reorder_level'] = (int) ($variant['reorder_level'] ?? 0);

            return $variant;
        }, $validated['variants']);

        // Bitta mahsulot ichida (size, color) va barcode takrorlanmasligi kerak
        $this->rejectDuplicateVariants($variants, $shopId);

        $product = DB::transaction(function () use ($validated, $variants, $shopId): Product {
            $product = Product::create([
                'shop_id' => $shopId,
                'name' => $validated['name'],
                'sku' => $validated['sku'],
                'category' => $validated['category'],
                'sale_price' => $validated['sale_price'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            foreach ($variants as $variant) {
                $product->variants()->create([
                    'shop_id' => $shopId,
                    'size' => $variant['size'],
                    'color' => $variant['color'],
                    'barcode' => $variant['barcode'],
                    'reorder_level' => $variant['reorder_level'],
                ]);
            }

            return $product;
        });

        $message = __('warehouse.product_created', ['name' => $product->name]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'sale_price' => $product->sale_price,
                    'variants_count' => $product->variants()->count(),
                ],
            ]);
        }

        return back()->with('success', $message);
    }

    public function showProduct(Product $product): JsonResponse
    {
        $product->load('variants');

        return response()->json([
            'success' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category,
                'sale_price' => $product->sale_price,
                'is_active' => $product->is_active,
                'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id,
                    'size' => $variant->size,
                    'color' => $variant->color,
                    'barcode' => $variant->barcode,
                    'reorder_level' => $variant->reorder_level,
                    'is_active' => $variant->is_active,
                    'stock_on_hand' => $variant->stock_on_hand,
                ])->values()->all(),
            ],
        ]);
    }

    public function printBarcodes(Product $product): View
    {
        $product->load('variants');

        return view('warehouse.print-barcodes', [
            'product' => $product,
        ]);
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $shopId = $request->user()->shop_id;

        $input = $this->normalizeProductInput($request->all());

        $validated = validator($input, [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')->where('shop_id', $shopId)->ignore($product->id)],
            'category' => ['nullable', 'string', 'max:100'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'is_active' => ['sometimes', 'boolean'],
            'variants' => ['required', 'array', 'min:1', 'max:30'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.size' => ['required', 'string', Rule::in(ProductVariant::SIZE_OPTIONS)],
            'variants.*.color' => ['nullable', 'string', 'max:50'],
            'variants.*.barcode' => ['nullable', 'string', 'max:64'],
            'variants.*.reorder_level' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
        ], [
            'sku.unique' => __('warehouse.sku_taken'),
            'variants.required' => __('warehouse.variants_required'),
            'variants.min' => __('warehouse.variants_required'),
            'variants.*.size.in' => __('warehouse.size_invalid'),
        ])->validate();

        $validated['category'] = ($validated['category'] ?? '') !== '' ? trim($validated['category']) : null;

        $variants = array_map(function (array $variant): array {
            $variant['id'] = isset($variant['id']) && $variant['id'] !== '' ? (int) $variant['id'] : null;
            $variant['size'] = strtoupper(trim($variant['size']));
            $variant['color'] = ($variant['color'] ?? '') !== '' ? trim($variant['color']) : null;
            $variant['barcode'] = ($variant['barcode'] ?? '') !== '' ? trim($variant['barcode']) : $this->generateUniqueBarcode();
            $variant['reorder_level'] = (int) ($variant['reorder_level'] ?? 0);
            $variant['is_active'] = array_key_exists('is_active', $variant) ? (bool) $variant['is_active'] : true;

            return $variant;
        }, $validated['variants']);

        $existingIds = $product->variants()->pluck('id')->all();

        // Boshqa mahsulot yoki do'konga tegishli variantni tahrirlab bo'lmaydi
        foreach ($variants as $variant) {
            if ($variant['id'] !== null && ! in_array($variant['id'], $existingIds, true)) {
                abort(404);
            }
        }

        $this->rejectDuplicateVariants($variants, $shopId, $existingIds);

        DB::transaction(function () use ($validated, $variants, $product): void {
            $product->update([
                'name' => $validated['name'],
                'sku' => $validated['sku'],
                'category' => $validated['category'],
                'sale_price' => $validated['sale_price'],
                'is_active' => $validated['is_active'] ?? $product->is_active,
            ]);

            foreach ($variants as $variant) {
                $attributes = [
                    'size' => $variant['size'],
                    'color' => $variant['color'],
                    'barcode' => $variant['barcode'],
                    'reorder_level' => $variant['reorder_level'],
                    'is_active' => $variant['is_active'],
                ];

                if ($variant['id'] !== null) {
                    $product->variants()->whereKey($variant['id'])->update($attributes);
                } else {
                    $product->variants()->create($attributes + ['shop_id' => $product->shop_id]);
                }
            }
        });

        $message = __('warehouse.product_updated');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'sale_price' => $product->sale_price,
                    'variants_count' => $product->variants()->count(),
                ],
            ]);
        }

        return back()->with('success', $message);
    }

    public function storeReceipt(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $shopId = $user->shop_id;

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->where('shop_id', $shopId)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ], [
            'items.required' => __('warehouse.items_required'),
            'items.min' => __('warehouse.items_required'),
            'items.*.quantity.min' => __('warehouse.quantity_min'),
        ]);

        // Xuddi shu kalit bilan kirim allaqachon saqlangan bo'lsa — qayta ishlamaymiz
        $existing = StockReceipt::query()
            ->where('created_by', $user->id)
            ->where('idempotency_key', $validated['idempotency_key'])
            ->first();

        if ($existing) {
            return $this->receiptSavedResponse($request, $existing, __('warehouse.receipt_already_saved'));
        }

        try {
            $receipt = DB::transaction(function () use ($validated, $user, $shopId): StockReceipt {
                $receipt = StockReceipt::create([
                    'shop_id' => $shopId,
                    'receipt_no' => 'K-'.strtoupper(Str::random(10)),
                    'idempotency_key' => $validated['idempotency_key'],
                    'status' => StockReceipt::STATUS_POSTED,
                    'created_by' => $user->id,
                    'posted_by' => $user->id,
                    'posted_at' => now(),
                    'received_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]);

                // Bitta variant bir necha marta kelsa — miqdorlarni jamlaymiz
                $lines = [];
                foreach ($validated['items'] as $item) {
                    $id = (int) $item['variant_id'];
                    $lines[$id] = ($lines[$id] ?? 0) + (int) $item['quantity'];
                }

                // Deterministik tartibda qulflash — deadlock xavfini kamaytiradi
                $variants = ProductVariant::query()
                    ->whereIn('id', array_keys($lines))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($lines as $variantId => $quantity) {
                    $variant = $variants[$variantId] ?? null;

                    if (! $variant) {
                        throw ValidationException::withMessages([
                            'items' => __('warehouse.variant_not_found'),
                        ]);
                    }

                    $item = StockReceiptItem::create([
                        'shop_id' => $shopId,
                        'stock_receipt_id' => $receipt->id,
                        'product_variant_id' => $variant->id,
                        'quantity' => $quantity,
                    ]);

                    $variant->stock_on_hand += $quantity;
                    $variant->save();

                    InventoryMovement::create([
                        'shop_id' => $shopId,
                        'product_variant_id' => $variant->id,
                        'movement_type' => InventoryMovement::TYPE_RECEIPT,
                        'quantity_delta' => $quantity,
                        'stock_after' => $variant->stock_on_hand,
                        'reference_type' => StockReceipt::class,
                        'reference_id' => $receipt->id,
                        'reference_line_id' => $item->id,
                        'movement_key' => 'receipt-'.$receipt->id.'-'.$variant->id,
                        'actor_id' => $user->id,
                        'created_at' => now(),
                    ]);
                }

                return $receipt;
            });
        } catch (QueryException $e) {
            // Parallel so'rov unique kalitga urilsa — saqlangan kirimni qaytaramiz
            if (! DatabaseError::isUniqueViolation($e)) {
                throw $e;
            }

            $existing = StockReceipt::query()
                ->where('created_by', $user->id)
                ->where('idempotency_key', $validated['idempotency_key'])
                ->first();

            if (! $existing) {
                throw $e;
            }

            return $this->receiptSavedResponse($request, $existing, __('warehouse.receipt_already_saved'));
        }

        return $this->receiptSavedResponse($request, $receipt, __('warehouse.receipt_saved', ['no' => $receipt->receipt_no]));
    }

    private function receiptSavedResponse(Request $request, StockReceipt $receipt, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'receipt' => [
                    'id' => $receipt->id,
                    'receipt_no' => $receipt->receipt_no,
                    'items_count' => $receipt->items()->count(),
                ],
                'redirect' => route('warehouse.receipts.show', $receipt),
            ]);
        }

        return redirect()->route('warehouse.receipts.show', $receipt)->with('success', $message);
    }

    public function showReceipt(Request $request, StockReceipt $receipt): View
    {
        $receipt->load([
            'items.variant.product:id,name,sku',
            'creator:id,name',
            'canceller:id,name',
        ]);

        return view('warehouse.receipt-show', [
            'receipt' => $receipt,
            'shop' => $request->user()->shop,
        ]);
    }

    public function cancelReceipt(Request $request, StockReceipt $receipt): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($receipt, $user, $validated): void {
            $lockedReceipt = StockReceipt::lockForUpdate()->findOrFail($receipt->id);

            if ($lockedReceipt->status !== StockReceipt::STATUS_POSTED) {
                abort(409, __('warehouse.receipt_already_cancelled'));
            }

            $items = $lockedReceipt->items()->orderBy('id')->get();

            $variants = ProductVariant::query()
                ->whereIn('id', $items->pluck('product_variant_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Avval tekshiruv: sotilgan tovar bo'lsa, qoldiq manfiy bo'lib ketmasligi kerak
            foreach ($items as $item) {
                $variant = $variants[$item->product_variant_id] ?? null;

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'items' => __('warehouse.variant_not_found'),
                    ]);
                }

                if ($variant->stock_on_hand < $item->quantity) {
                    throw ValidationException::withMessages([
                        'items' => __('warehouse.receipt_cancel_sold_out'),
                    ]);
                }
            }

            foreach ($items as $item) {
                $variant = $variants[$item->product_variant_id];

                $variant->stock_on_hand -= $item->quantity;
                $variant->save();

                InventoryMovement::create([
                    'shop_id' => $lockedReceipt->shop_id,
                    'product_variant_id' => $variant->id,
                    'movement_type' => InventoryMovement::TYPE_RECEIPT_CANCEL,
                    'quantity_delta' => -$item->quantity,
                    'stock_after' => $variant->stock_on_hand,
                    'reference_type' => StockReceipt::class,
                    'reference_id' => $lockedReceipt->id,
                    'reference_line_id' => $item->id,
                    'movement_key' => 'receipt-cancel-'.$lockedReceipt->id.'-'.$variant->id,
                    'actor_id' => $user->id,
                    'created_at' => now(),
                ]);
            }

            $lockedReceipt->update([
                'status' => StockReceipt::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $validated['reason'] ?? null,
            ]);
        });

        $message = __('warehouse.receipt_cancelled');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect' => route('warehouse.receipts.show', $receipt),
            ]);
        }

        return redirect()->route('warehouse.receipts.show', $receipt)->with('success', $message);
    }

    /**
     * SKU va variant razmerlarini validatsiyadan oldin yagona formatga keltiradi.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeProductInput(array $input): array
    {
        $input['sku'] = strtoupper(trim((string) ($input['sku'] ?? '')));

        if (is_array($input['variants'] ?? null)) {
            foreach ($input['variants'] as $index => $variant) {
                if (is_array($variant)) {
                    $input['variants'][$index]['size'] = strtoupper(trim((string) ($variant['size'] ?? '')));
                }
            }
        }

        return $input;
    }

    /**
     * Bitta mahsulot ichida (size, color) va barcode takrorlanmasligini tekshiradi.
     *
     * @param  array<int, array<string, mixed>>  $variants
     * @param  array<int, int>  $ignoreVariantIds  Tahrirlashda o'z variantlarini hisobga olmaslik uchun
     */
    private function rejectDuplicateVariants(array $variants, int $shopId, array $ignoreVariantIds = []): void
    {
        $seenCombos = [];
        $seenBarcodes = [];

        foreach ($variants as $variant) {
            // Razmer/rang solishtirishda registr farqini e'tiborsiz qoldiramiz
            $combo = strtolower($variant['size']).'|'.strtolower((string) ($variant['color'] ?? ''));

            if (in_array($combo, $seenCombos, true)) {
                throw ValidationException::withMessages([
                    'variants' => __('warehouse.variant_duplicate'),
                ]);
            }

            $seenCombos[] = $combo;

            if ($variant['barcode'] !== null) {
                $barcodeTaken = ProductVariant::query()
                    ->where('barcode', $variant['barcode'])
                    ->when($ignoreVariantIds !== [], fn ($query) => $query->whereNotIn('id', $ignoreVariantIds))
                    ->exists();

                if (in_array($variant['barcode'], $seenBarcodes, true) || $barcodeTaken) {
                    throw ValidationException::withMessages([
                        'variants' => __('warehouse.barcode_taken'),
                    ]);
                }

                $seenBarcodes[] = $variant['barcode'];
            }
        }
    }

    private function generateUniqueBarcode(): string
    {
        do {
            $barcode = (string) rand(100000000000, 999999999999);
        } while (ProductVariant::withoutGlobalScope(TenantScope::class)->where('barcode', $barcode)->exists());

        return $barcode;
    }
}
