<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VOIDED = 'voided';

    public const PAYMENT_CASH = 'cash';

    public const PAYMENT_CARD = 'card';

    protected $fillable = [
        'shop_id',
        'sale_no',
        'status',
        'cashier_id',
        'sold_at',
        'payment_method',
        'subtotal',
        'discount_amount',
        'total',
        'amount_received',
        'change_amount',
        'idempotency_key',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'subtotal' => 'float',
        'discount_amount' => 'float',
        'total' => 'float',
        'amount_received' => 'float',
        'change_amount' => 'float',
        'sold_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * Sotuvni bekor qiladi: qoldiqni omborga qaytaradi va harakat yozadi.
     * Chaqiruvchi tomon avval ruxsatni tekshirgan bo'lishi shart.
     */
    public function voidBy(User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($actor, $reason): void {
            // Super admin kontekstida ham ishlashi uchun scope'siz qayta o'qiymiz;
            // chaqiruvchi sotuvni allaqachon ruxsat bilan topgan bo'ladi.
            $sale = static::query()
                ->withoutGlobalScope(TenantScope::class)
                ->lockForUpdate()
                ->findOrFail($this->id);

            if ($sale->status !== self::STATUS_COMPLETED) {
                abort(409, __('pos.void_already'));
            }

            $items = $sale->items()
                ->withoutGlobalScope(TenantScope::class)
                ->orderBy('id')
                ->get();

            $variants = ProductVariant::query()
                ->withoutGlobalScope(TenantScope::class)
                ->whereIn('id', $items->pluck('product_variant_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $variant = $variants[$item->product_variant_id] ?? null;

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'items' => __('pos.variant_not_found'),
                    ]);
                }

                $variant->stock_on_hand += $item->quantity;
                $variant->save();

                InventoryMovement::create([
                    'shop_id' => $sale->shop_id,
                    'product_variant_id' => $variant->id,
                    'movement_type' => InventoryMovement::TYPE_SALE_VOID,
                    'quantity_delta' => $item->quantity,
                    'stock_after' => $variant->stock_on_hand,
                    'reference_type' => self::class,
                    'reference_id' => $sale->id,
                    'reference_line_id' => $item->id,
                    'movement_key' => 'sale-void-'.$sale->id.'-'.$variant->id,
                    'actor_id' => $actor->id,
                    'created_at' => now(),
                ]);
            }

            $sale->update([
                'status' => self::STATUS_VOIDED,
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ]);
        });
    }
}
