<?php

namespace App\Modules\Treasury\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReconciliationReport extends Model
{
    protected $fillable = [
        'uuid',
        'report_type',
        'period_start',
        'period_end',
        'total_custodian_balance',
        'total_provider_balance',
        'total_customer_wallets',
        'total_merchant_wallets',
        'total_pending_transactions',
        'total_platform_fees',
        'expected_total',
        'actual_total',
        'discrepancy',
        'status',
        'notes',
        'details',
        'created_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_custodian_balance' => 'decimal:2',
        'total_provider_balance' => 'decimal:2',
        'total_customer_wallets' => 'decimal:2',
        'total_merchant_wallets' => 'decimal:2',
        'total_pending_transactions' => 'decimal:2',
        'total_platform_fees' => 'decimal:2',
        'expected_total' => 'decimal:2',
        'actual_total' => 'decimal:2',
        'discrepancy' => 'decimal:2',
        'details' => 'array',
        'reviewed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
        });
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeBalanced($query)
    {
        return $query->where('status', 'balanced');
    }

    public function scopeWithDiscrepancy($query)
    {
        return $query->where('status', 'discrepancy');
    }

    public function scopeForPeriod($query, string $start, string $end)
    {
        return $query->whereBetween('period_start', [$start, $end]);
    }

    public function isBalanced(): bool
    {
        return $this->status === 'balanced';
    }

    public function hasDiscrepancy(): bool
    {
        return $this->status === 'discrepancy';
    }

    public function markAsReviewed(int $userId, ?string $notes = null): void
    {
        $this->update([
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'notes' => $notes,
            'status' => 'resolved',
        ]);
    }

    public function getTotalAssets(): float
    {
        return $this->total_custodian_balance + $this->total_provider_balance;
    }

    public function getTotalLiabilities(): float
    {
        return $this->total_customer_wallets
            + $this->total_merchant_wallets
            + $this->total_platform_fees;
    }
}
