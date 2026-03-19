<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettlementBatch extends Model
{
    protected $fillable = [
        'uuid',
        'batch_number',
        'merchant_id',
        'period_start',
        'period_end',
        'gross_amount',
        'fee_amount',
        'refund_amount',
        'chargeback_amount',
        'adjustment_amount',
        'net_amount',
        'currency',
        'settlement_account_id',
        'settlement_method',
        'settled_at',
        'settlement_reference',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'gross_amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'chargeback_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SettlementTransaction::class, 'settlement_batch_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markAsCompleted(string $reference): void
    {
        $this->update([
            'status' => 'completed',
            'settled_at' => now(),
            'settlement_reference' => $reference,
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }
}
