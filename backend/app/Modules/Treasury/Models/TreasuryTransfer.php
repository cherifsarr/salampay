<?php

namespace App\Modules\Treasury\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class TreasuryTransfer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'reference',
        'external_reference',
        'type',
        'direction',
        'source_type',
        'source_id',
        'source_balance_before',
        'source_balance_after',
        'destination_type',
        'destination_id',
        'destination_balance_before',
        'destination_balance_after',
        'amount',
        'fee',
        'net_amount',
        'currency',
        'status',
        'status_reason',
        'initiated_by',
        'initiated_by_user_id',
        'description',
        'metadata',
        'initiated_at',
        'completed_at',
    ];

    protected $casts = [
        'source_balance_before' => 'decimal:2',
        'source_balance_after' => 'decimal:2',
        'destination_balance_before' => 'decimal:2',
        'destination_balance_after' => 'decimal:2',
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'metadata' => 'array',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
            if (!$model->reference) {
                $model->reference = self::generateReference();
            }
            if (!$model->initiated_at) {
                $model->initiated_at = now();
            }
            if (!$model->net_amount) {
                $model->net_amount = $model->amount - ($model->fee ?? 0);
            }
        });
    }

    public static function generateReference(): string
    {
        $prefix = 'TRF';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(6));
        return "{$prefix}{$date}{$random}";
    }

    // Relationships

    public function sourceAccount()
    {
        return $this->source_type === 'custodian'
            ? $this->belongsTo(CustodianAccount::class, 'source_id')
            : $this->belongsTo(ProviderAccount::class, 'source_id');
    }

    public function destinationAccount()
    {
        return $this->destination_type === 'custodian'
            ? $this->belongsTo(CustodianAccount::class, 'destination_id')
            : $this->belongsTo(ProviderAccount::class, 'destination_id');
    }

    public function initiatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeSweeps($query)
    {
        return $query->where('type', 'sweep');
    }

    public function scopeFunds($query)
    {
        return $query->where('type', 'fund');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('initiated_at', today());
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

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending']);
    }

    // Actions

    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'status_reason' => $reason,
        ]);
    }

    public function cancel(string $reason = 'Cancelled'): void
    {
        if (!$this->canBeCancelled()) {
            throw new \Exception('Transfer cannot be cancelled');
        }
        $this->update([
            'status' => 'cancelled',
            'status_reason' => $reason,
        ]);
    }

    // Display helpers

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'sweep' => 'Sweep to Bank',
            'fund' => 'Fund from Bank',
            'rebalance' => 'Rebalance',
            'bank_transfer' => 'Bank Transfer',
            'manual' => 'Manual Transfer',
            'fee' => 'Fee Payment',
            'interest' => 'Interest Credit',
            default => ucfirst($this->type),
        };
    }

    public function getSourceName(): string
    {
        $account = $this->sourceAccount;
        return $account ? ($account->name ?? $account->provider) : 'Unknown';
    }

    public function getDestinationName(): string
    {
        $account = $this->destinationAccount;
        return $account ? ($account->name ?? $account->provider) : 'Unknown';
    }
}
