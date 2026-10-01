<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\ScannerScan;
use App\Models\ScannerSession;
use App\Models\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScannerDeviceController extends Controller
{
    /**
     * Telefon skaner sahifasini ko'rsatish.
     */
    public function show(string $token): View
    {
        $session = $this->resolveSession($token);
        abort_unless($session && $session->isActive(), 404);

        return view('scanner.device', ['token' => $token]);
    }

    /**
     * Sessiyaga ulanish.
     */
    public function connect(string $token): JsonResponse
    {
        $session = $this->resolveSession($token);
        abort_unless($session && $session->isActive(), 404);

        return response()->json([
            'success' => true,
            'connected' => true,
            'expires_at' => $session->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Barkod skanerlash va navbatga yozish.
     */
    public function scan(Request $request, string $token): JsonResponse
    {
        $session = $this->resolveSession($token);
        abort_unless($session && $session->isActive(), 404);

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:100'],
        ]);

        $barcode = trim($validated['barcode']);

        // TenantScope bypass — sessiyadan shop_id qo'lda filter
        $variant = ProductVariant::withoutGlobalScope(TenantScope::class)
            ->where('shop_id', $session->shop_id)
            ->where('barcode', $barcode)
            ->first();

        if (! $variant) {
            $session->scans()->create([
                'barcode' => $barcode,
                'status' => ScannerScan::STATUS_NOT_FOUND,
            ]);

            return response()->json([
                'success' => true,
                'status' => ScannerScan::STATUS_NOT_FOUND,
            ]);
        }

        if (! $variant->is_active) {
            $session->scans()->create([
                'product_variant_id' => $variant->id,
                'barcode' => $barcode,
                'status' => ScannerScan::STATUS_INACTIVE,
            ]);

            return response()->json([
                'success' => true,
                'status' => ScannerScan::STATUS_INACTIVE,
            ]);
        }

        $session->scans()->create([
            'product_variant_id' => $variant->id,
            'barcode' => $barcode,
            'status' => ScannerScan::STATUS_FOUND,
        ]);

        return response()->json([
            'success' => true,
            'status' => ScannerScan::STATUS_FOUND,
            'name' => $variant->product?->name,
        ]);
    }

    /**
     * Telefonda nom/SKU/barcode bo'yicha qidirish (sessiya do'koniga cheklangan).
     */
    public function search(Request $request, string $token): JsonResponse
    {
        $session = $this->resolveSession($token);
        abort_unless($session && $session->isActive(), 404);

        $q = trim((string) $request->query('q', ''));
        abort_if($q === '', 422);

        $term = str_replace(['%', '_'], ['\%', '\_'], $q);

        $variants = ProductVariant::withoutGlobalScope(TenantScope::class)
            ->where('product_variants.shop_id', $session->shop_id)
            ->where('product_variants.is_active', true)
            ->where(function ($query) use ($term): void {
                $query->where('product_variants.barcode', 'like', "%{$term}%")
                    ->orWhereHas('product', fn ($pq) => $pq
                        ->withoutGlobalScope(TenantScope::class)
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%"));
            })
            ->with(['product' => fn ($pq) => $pq->withoutGlobalScope(TenantScope::class)])
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'results' => $variants->map(fn (ProductVariant $v): array => [
                'barcode' => $v->barcode,
                'name' => $v->product?->name,
                'sku' => $v->product?->sku,
                'label' => $v->displayLabel(),
            ])->all(),
        ]);
    }

    private function resolveSession(string $token): ?ScannerSession
    {
        $hash = hash('sha256', $token);

        return ScannerSession::where('token_hash', $hash)->first();
    }
}
