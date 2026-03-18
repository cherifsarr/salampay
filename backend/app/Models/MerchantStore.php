<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MerchantStore extends Model
{
    protected $fillable = [
        'uuid',
        'merchant_id',
        'store_name',
        'store_code',
        'address_line1',
        'city',
        'region',
        'country',
        'latitude',
        'longitude',
        'contact_phone',
        'contact_email',
        'operating_hours',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'operating_hours' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($store) {
            if (empty($store->uuid)) {
                $store->uuid = Str::uuid();
            }
        });
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class, 'store_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'store_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getFullAddressAttribute(): string
    {
        return implode(', ', array_filter([
            $this->address_line1,
            $this->city,
            $this->region,
            $this->country,
        ]));
    }
}
