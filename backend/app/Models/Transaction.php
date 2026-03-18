<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Transaction extends Model
{
    protected $fillable = [
        'uuid',
        'reference',
        'external_reference',
        'idempotency_key',
        'type',
        'amount',
        'currency',
        'fee_amount',
        'net_amount',
        'exchange_rate',
        'source_wallet_id',
        'destination_wallet_id',
        'source_user_id',
        'destination_user_id',
        'merchant_id',
        'store_id',
        'provider',
        'provider_transaction_id',
        'provider_response',
        'status',
        'status_reason',
        'completed_at',
        'ip_address',
        'user_agent',
        'device_fingerprint',
        'risk_score',
        'flagged_at',
        'flagged_reason',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'provider_response' => 'array',
            'metadata' => 'array',
            'completed_at' => 'datetime',
            'flagged_at' => 'datetime',
            'risk_score' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (empty($transaction->uuid)) {
                $transaction->uuid = Str::uuid();
            }
            if (empty($transaction->reference)) {
                $transaction->reference = self::generateReference();
            }
            if (empty($transaction->net_amount)) {
                $transaction->net_amount = $transaction->amount - ($transaction->fee_amount ?? 0);
            }
        });
    }

    public static function generateReference(): string
    {
        return 'SP-' . date('Ymd') . '-' . strtoupper(Str::random(6));
    }

    // Relationships
    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'source_wallet_id');
    }

    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'destination_wallet_id');
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function destinationUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destination_user_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MerchantStore::class, 'store_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    // Status helpers
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function isFlagged(): bool
    {
        return $this->flagged_at !== null;
    }

    // Actions
    public function markAsProcessing(): void
    {
        $this->status = 'processing';
        $this->save();
    }

    public function markAsCompleted(): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
    }

    public function markAsFailed(string $reason): void
    {
        $this->status = 'failed';
        $this->status_reason = $reason;
        $this->save();
    }

    public function markAsCancelled(string $reason = null): void
    {
        $this->status = 'cancelled';
        $this->status_reason = $reason;
        $this->save();
    }

    public function flag(string $reason): void
    {
        $this->flagged_at = now();
        $this->flagged_reason = $reason;
        $this->save();
    }

    // Type helpers
    public function isDeposit(): bool
    {
        return $this->type === 'deposit';
    }

    public function isWithdrawal(): bool
    {
        return $this->type === 'withdrawal';
    }

    public function isTransfer(): bool
    {
        return in_array($this->type, ['transfer_p2p', 'transfer_merchant']);
    }

    public function isPayment(): bool
    {
        return str_starts_with($this->type, 'payment_');
    }

    public function isRefund(): bool
    {
        return $this->type === 'refund';
    }
}
