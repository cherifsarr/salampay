<?php

namespace App\Modules\Treasury\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProviderAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'provider',
        'name',
        'account_id',
        'phone',
        'currency',
        'balance',
        'available_balance',
        'pending_balance',
        'minimum_balance',
        'target_balance',
        'maximum_balance',
        'daily_limit',
        'monthly_limit',
        'daily_volume',
        'monthly_volume',
        'auto_sweep_enabled',
        'auto_fund_enabled',
        'status',
        'balance_updated_at',
        'last_sweep_at',
        'last_fund_at',
        'api_credentials',
        'metadata',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'available_balance' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'minimum_balance' => 'decimal:2',
        'target_balance' => 'decimal:2',
        'maximum_balance' => 'decimal:2',
        'daily_limit' => 'decimal:2',
        'monthly_limit' => 'decimal:2',
        'daily_volume' => 'decimal:2',
        'monthly_volume' => 'decimal:2',
        'auto_sweep_enabled' => 'boolean',
        'auto_fund_enabled' => 'boolean',
        'balance_updated_at' => 'datetime',
        'last_sweep_at' => 'datetime',
        'last_fund_at' => 'datetime',
        'api_credentials' => 'encrypted:array',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'api_credentials',
    ];

    /**
     * Provider balance caps (in XOF)
     * These are typical limits - actual limits may vary by merchant tier
     */
    public const PROVIDER_CAPS = [
        'wave' => 10000000,        // 10M XOF
        'orange_money' => 5000000, // 5M XOF
        'free_money' => 5000000,   // 5M XOF
        'wizall' => 3000000,       // 3M XOF
        'emoney' => 3000000,       // 3M XOF
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
            // Set default maximum based on provider if not set
            if (!$model->maximum_balance && isset(self::PROVIDER_CAPS[$model->provider])) {
                $model->maximum_balance = self::PROVIDER_CAPS[$model->provider];
            }
        });
    }

    // Relationships

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(TreasuryLedger::class, 'account_id')
            ->where('account_type', 'provider');
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(TreasuryTransfer::class, 'source_id')
            ->where('source_type', 'provider');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(TreasuryTransfer::class, 'destination_id')
            ->where('destination_type', 'provider');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(BalanceSnapshot::class, 'account_id')
            ->where('account_type', 'provider');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeNeedsSweep($query)
    {
        return $query->where('auto_sweep_enabled', true)
            ->where('status', 'active')
            ->whereRaw('balance > maximum_balance');
    }

    public function scopeNeedsFunding($query)
    {
        return $query->where('auto_fund_enabled', true)
            ->where('status', 'active')
            ->whereRaw('balance < minimum_balance');
    }

    // Helpers

    public function isOverMaximum(): bool
    {
        return $this->balance > $this->maximum_balance;
    }

    public function isBelowMinimum(): bool
    {
        return $this->balance < $this->minimum_balance;
    }

    public function isNearMaximum(float $threshold = 0.9): bool
    {
        return $this->balance >= ($this->maximum_balance * $threshold);
    }

    public function isNearMinimum(float $threshold = 1.2): bool
    {
        return $this->balance <= ($this->minimum_balance * $threshold);
    }

    public function excessAmount(): float
    {
        if ($this->balance <= $this->maximum_balance) {
            return 0;
        }
        // Sweep down to target, not just maximum
        $target = $this->target_balance ?? ($this->maximum_balance * 0.7);
        return $this->balance - $target;
    }

    public function deficitAmount(): float
    {
        if ($this->balance >= $this->minimum_balance) {
            return 0;
        }
        // Fund up to target, not just minimum
        $target = $this->target_balance ?? ($this->minimum_balance * 2);
        return $target - $this->balance;
    }

    public function availableForWithdrawal(): float
    {
        // Cannot withdraw below minimum
        return max(0, $this->available_balance - $this->minimum_balance);
    }

    public function canAcceptDeposit(float $amount): bool
    {
        return ($this->balance + $amount) <= $this->maximum_balance;
    }

    public function remainingDailyLimit(): float
    {
        if (!$this->daily_limit) {
            return PHP_FLOAT_MAX;
        }
        return max(0, $this->daily_limit - $this->daily_volume);
    }

    public function remainingMonthlyLimit(): float
    {
        if (!$this->monthly_limit) {
            return PHP_FLOAT_MAX;
        }
        return max(0, $this->monthly_limit - $this->monthly_volume);
    }

    public function getAccountIdentifier(): string
    {
        return 'provider:' . $this->id;
    }

    public function getProviderName(): string
    {
        return match ($this->provider) {
            'wave' => 'Wave',
            'orange_money' => 'Orange Money',
            'free_money' => 'Free Money',
            'wizall' => 'Wizall',
            'emoney' => 'E-Money',
            default => ucfirst($this->provider),
        };
    }
}
