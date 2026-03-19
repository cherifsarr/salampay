<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentLink extends Model
{
    protected $fillable = [
        'uuid',
        'merchant_id',
        'short_code',
        'title',
        'description',
        'amount',
        'currency',
        'allow_tip',
        'max_uses',
        'use_count',
        'valid_until',
        'redirect_url',
        'metadata',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'allow_tip' => 'boolean',
            'max_uses' => 'integer',
            'use_count' => 'integer',
            'valid_until' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->valid_until && $this->valid_until->isPast()) {
            return false;
        }

        if ($this->max_uses && $this->use_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function incrementUseCount(): void
    {
        $this->increment('use_count');
    }

    public function getUrl(): string
    {
        return config('app.url') . '/pay/' . $this->short_code;
    }
}
