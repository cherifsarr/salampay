<?php

namespace App\Modules\Treasury\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CustodianAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'bank_name',
        'bank_code',
        'account_number',
        'iban',
        'swift_code',
        'account_type',
        'currency',
        'balance',
        'available_balance',
        'minimum_balance',
        'target_balance',
        'maximum_balance',
        'is_primary',
        'is_sweep_target',
        'is_funding_source',
        'status',
        'balance_updated_at',
        'metadata',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'available_balance' => 'decimal:2',
        'minimum_balance' => 'decimal:2',
        'target_balance' => 'decimal:2',
        'maximum_balance' => 'decimal:2',
        'is_primary' => 'boolean',
        'is_sweep_target' => 'boolean',
        'is_funding_source' => 'boolean',
        'balance_updated_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'account_number',
        'iban',
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

    // Relationships

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(TreasuryLedger::class, 'account_id')
            ->where('account_type', 'custodian');
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(TreasuryTransfer::class, 'source_id')
            ->where('source_type', 'custodian');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(TreasuryTransfer::class, 'destination_id')
            ->where('destination_type', 'custodian');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(BalanceSnapshot::class, 'account_id')
            ->where('account_type', 'custodian');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeSweepTargets($query)
    {
        return $query->where('is_sweep_target', true)->where('status', 'active');
    }

    public function scopeFundingSources($query)
    {
        return $query->where('is_funding_source', true)->where('status', 'active');
    }

    // Helpers

    public function isOverMaximum(): bool
    {
        return $this->maximum_balance && $this->balance > $this->maximum_balance;
    }

    public function isBelowMinimum(): bool
    {
        return $this->balance < $this->minimum_balance;
    }

    public function excessAmount(): float
    {
        if (!$this->maximum_balance || $this->balance <= $this->maximum_balance) {
            return 0;
        }
        return $this->balance - ($this->target_balance ?? $this->maximum_balance);
    }

    public function deficitAmount(): float
    {
        if ($this->balance >= $this->minimum_balance) {
            return 0;
        }
        return ($this->target_balance ?? $this->minimum_balance) - $this->balance;
    }

    public function getAccountIdentifier(): string
    {
        return 'custodian:' . $this->id;
    }

    public function getMaskedAccountNumber(): string
    {
        $number = decrypt($this->account_number);
        return '****' . substr($number, -4);
    }
}
