<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScannerScan extends Model
{
    public const STATUS_FOUND = 'found';

    public const STATUS_NOT_FOUND = 'not_found';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'scanner_session_id',
        'product_variant_id',
        'barcode',
        'status',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ScannerSession::class, 'scanner_session_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
