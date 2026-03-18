<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletHold extends Model
{
    protected $fillable = [
        'wallet_id',
        'transaction_id',
        'amount',
        'reason',
        'expires_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isActive(): bool
    {
        return $this->released_at === null && $this->expires_at > now();
    }

    public function isExpired(): bool
    {
        return $this->released_at === null && $this->expires_at <= now();
    }

    public function isReleased(): bool
    {
        return $this->released_at !== null;
    }
}
