<?php

namespace App\Modules\Treasury\Models;

use Illuminate\Database\Eloquent\Model;

class BalanceSnapshot extends Model
{
    protected $fillable = [
        'snapshot_type',
        'snapshot_at',
        'account_type',
        'account_id',
        'reported_balance',
        'calculated_balance',
        'discrepancy',
        'is_reconciled',
        'metadata',
    ];

    protected $casts = [
        'snapshot_at' => 'datetime',
        'reported_balance' => 'decimal:2',
        'calculated_balance' => 'decimal:2',
        'discrepancy' => 'decimal:2',
        'is_reconciled' => 'boolean',
        'metadata' => 'array',
    ];

    public function account()
    {
        return $this->account_type === 'custodian'
            ? $this->belongsTo(CustodianAccount::class, 'account_id')
            : $this->belongsTo(ProviderAccount::class, 'account_id');
    }

    public function scopeForAccount($query, string $type, int $id)
    {
        return $query->where('account_type', $type)->where('account_id', $id);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('snapshot_at', $date);
    }

    public function scopeWithDiscrepancy($query)
    {
        return $query->where('discrepancy', '>', 0);
    }

    public function hasDiscrepancy(): bool
    {
        return $this->discrepancy > 0.01;
    }
}
