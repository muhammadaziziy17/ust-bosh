<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Database\Factories\StockReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockReceipt extends Model
{
    /** @use HasFactory<StockReceiptFactory> */
    use HasFactory;

    public const STATUS_POSTED = 'posted';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'shop_id',
        'receipt_no',
        'idempotency_key',
        'status',
        'created_by',
        'posted_by',
        'posted_at',
        'received_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'notes',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReceiptItem::class);
    }
}
