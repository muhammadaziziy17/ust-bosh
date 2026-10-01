<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Single Database Multi-Tenancy himoyasi.
 *
 * Har bir biznes-model so'roviga joriy foydalanuvchining shop_id filtri
 * qo'shiladi. Tenant konteksti bo'lmasa (CLI, login qilmagan) — bo'sh
 * natija qaytariladi (fail closed).
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if ($user && $user->shop_id) {
            $builder->where($model->getTable().'.shop_id', $user->shop_id);

            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
