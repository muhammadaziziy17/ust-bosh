<?php

namespace App\Models;

use App\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_OWNER = 'owner';

    public const ROLE_WAREHOUSE = 'warehouse';

    public const ROLE_CASHIER = 'cashier';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'shop_id',
        'must_change_password',
        'is_active',
        'auth_version',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
            'auth_version' => 'integer',
            'last_login_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function loginEvents(): HasMany
    {
        return $this->hasMany(LoginEvent::class);
    }

    public function canReceivePasswordReset(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_WAREHOUSE, self::ROLE_CASHIER], true);
    }

    /**
     * Barcha sessiyalarni bekor qiladi (driver'dan qat'i nazar):
     * auth_version oshiriladi, remember token aylantiriladi.
     */
    public function revokeAllSessions(): void
    {
        $this->auth_version++;
        $this->setRememberToken(Str::random(60));
        $this->save();
    }

    public function sendPasswordResetNotification($token): void
    {
        if (! $this->canReceivePasswordReset()) {
            return;
        }

        $this->notify(new ResetPassword($token));
    }

    /**
     * Rolga qarab foydalanuvchi landig' sahifasi.
     */
    public function dashboardRoute(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => route('admin.dashboard'),
            self::ROLE_WAREHOUSE => route('warehouse.dashboard'),
            self::ROLE_CASHIER => route('pos.index'),
            default => route('owner.dashboard'),
        };
    }
}
