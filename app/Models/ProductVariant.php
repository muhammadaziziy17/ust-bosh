<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    public const SIZE_OPTIONS = ['S', 'M', 'L', 'XL', 'XXL'];

    protected $fillable = [
        'shop_id',
        'product_id',
        'size',
        'color',
        'barcode',
        'stock_on_hand',
        'reorder_level',
        'is_active',
    ];

    protected $casts = [
        'stock_on_hand' => 'integer',
        'reorder_level' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock_on_hand', 0);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_on_hand', '<=', 'reorder_level')
            ->where('stock_on_hand', '>', 0);
    }

    public function isLowStock(): bool
    {
        return $this->stock_on_hand > 0 && $this->stock_on_hand <= $this->reorder_level;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock_on_hand <= 0;
    }

    public function displayLabel(): string
    {
        $label = $this->size;

        if ($this->color !== null && $this->color !== '') {
            $label .= ' · '.$this->color;
        }

        return $label;
    }
}
