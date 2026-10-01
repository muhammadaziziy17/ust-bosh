<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'shop_name',
        'contact_name',
        'email',
        'phone',
        'phone_normalized',
        'contact_key',
        'message',
        'status',
        'locale',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /** Tasdiqlangan arizalar */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /** Kutilayotgan arizalar */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /** Rad etilgan arizalar */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }
}
