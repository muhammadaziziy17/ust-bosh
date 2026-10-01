<?php

namespace App\Models;

use Database\Factories\ScannerSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScannerSession extends Model
{
    /** @use HasFactory<ScannerSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'cashier_id',
        'token_hash',
        'expires_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function scans(): HasMany
    {
        return $this->hasMany(ScannerScan::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->closed_at === null && ! $this->isExpired();
    }
}
