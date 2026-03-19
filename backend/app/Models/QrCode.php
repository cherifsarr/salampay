<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCode extends Model
{
    protected $fillable = [
        'uuid',
        'merchant_id',
        'store_id',
        'qr_type',
        'amount',
        'description',
        'qr_data',
        'qr_image_url',
        'valid_until',
        'scan_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'valid_until' => 'datetime',
            'scan_count' => 'integer',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MerchantStore::class, 'store_id');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->valid_until && $this->valid_until->isPast()) {
            return false;
        }

        return true;
    }

    public function incrementScanCount(): void
    {
        $this->increment('scan_count');
    }
}
