<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Super admin amallarining audit jurnali. Maxfiy ma'lumotlar (parol, token)
 * bu yerga hech qachon yozilmaydi.
 */
class AdminAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'shop_id',
        'action',
        'subject_type',
        'subject_id',
        'reason',
        'before',
        'after',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function record(
        Request $request,
        string $action,
        ?Shop $shop = null,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        ?string $reason = null,
    ): self {
        return static::create([
            'actor_id' => $request->user()?->id,
            'shop_id' => $shop?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'reason' => $reason,
            'before' => $before !== [] ? $before : null,
            'after' => $after !== [] ? $after : null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'created_at' => now(),
        ]);
    }
}
