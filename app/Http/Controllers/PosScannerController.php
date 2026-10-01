<?php

namespace App\Http\Controllers;

use App\Models\ScannerScan;
use App\Models\ScannerSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosScannerController extends Controller
{
    /**
     * Yangi skaner sessiyasi yaratish. Eski yozuvlar opportunistik tozalanadi.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $shopId = $user->shop_id;

        // 24 soatdan oshgan sessiyalarni tozalash
        ScannerSession::where('shop_id', $shopId)
            ->where('created_at', '<', now()->subDay())
            ->delete();

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $session = ScannerSession::create([
            'shop_id' => $shopId,
            'cashier_id' => $user->id,
            'token_hash' => $tokenHash,
            'expires_at' => now()->addMinutes(15),
        ]);

        return response()->json([
            'success' => true,
            'session_id' => $session->id,
            'token' => $rawToken,
            'url' => route('scanner.device', $rawToken),
        ]);
    }

    /**
     * Polling: sessiyaga kelgan yangi skanlarni qaytarish.
     */
    public function events(Request $request, ScannerSession $session): JsonResponse
    {
        abort_unless($session->cashier_id === $request->user()->id, 404);

        $afterId = (int) $request->query('after', 0);

        $scans = $session->scans()
            ->with('variant.product')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'active' => $session->isActive(),
            'scans' => $scans->map(fn (ScannerScan $scan): array => [
                'id' => $scan->id,
                'status' => $scan->status,
                'barcode' => $scan->barcode,
                'variant_id' => $scan->product_variant_id,
                'name' => $scan->variant?->product?->name,
                'label' => $scan->variant?->displayLabel(),
                'price' => $scan->variant?->product?->sale_price,
                'stock' => $scan->variant?->stock_on_hand,
            ])->all(),
        ]);
    }

    /**
     * Sessiyani yopish.
     */
    public function close(Request $request, ScannerSession $session): JsonResponse
    {
        abort_unless($session->cashier_id === $request->user()->id, 404);

        $session->update(['closed_at' => now()]);

        return response()->json(['success' => true]);
    }
}
