<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public $timestamps = false;

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_RECEIPT_CANCEL = 'receipt_cancel';

    public const TYPE_SALE = 'sale';

    public const TYPE_SALE_VOID = 'sale_void';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'shop_id',
        'product_variant_id',
        'movement_type',
        'quantity_delta',
        'stock_after',
        'reference_type',
        'reference_id',
        'reference_line_id',
        'movement_key',
        'actor_id',
        'created_at',
    ];

    protected $casts = [
        'quantity_delta' => 'integer',
        'stock_after' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
