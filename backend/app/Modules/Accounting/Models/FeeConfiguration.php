<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FeeConfiguration extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'transaction_type',
        'fee_type',
        'percentage_rate',
        'fixed_amount',
        'minimum_fee',
        'maximum_fee',
        'tiers',
        'payer',
        'applies_to',
        'provider',
        'is_active',
        'effective_from',
        'effective_until',
        'metadata',
    ];

    protected $casts = [
        'percentage_rate' => 'decimal:4',
        'fixed_amount' => 'decimal:2',
        'minimum_fee' => 'decimal:2',
        'maximum_fee' => 'decimal:2',
        'tiers' => 'array',
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Default fee tiers for tiered pricing
     * Example: Lower fees for higher volumes
     */
    public const DEFAULT_TIERS = [
        ['min' => 0, 'max' => 50000, 'rate' => 0.02],        // 2% for 0-50K
        ['min' => 50000, 'max' => 200000, 'rate' => 0.015],  // 1.5% for 50K-200K
        ['min' => 200000, 'max' => 500000, 'rate' => 0.01],  // 1% for 200K-500K
        ['min' => 500000, 'max' => null, 'rate' => 0.005],   // 0.5% for 500K+
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

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>', now());
            });
    }

    public function scopeForType($query, string $transactionType)
    {
        return $query->where('transaction_type', $transactionType);
    }

    public function scopeForProvider($query, ?string $provider)
    {
        return $query->where(function ($q) use ($provider) {
            $q->whereNull('provider')
                ->orWhere('provider', $provider);
        });
    }

    public function scopeForAccountType($query, string $accountType)
    {
        return $query->where(function ($q) use ($accountType) {
            $q->where('applies_to', 'all')
                ->orWhere('applies_to', $accountType);
        });
    }

    // Fee calculation methods

    /**
     * Calculate fee for a given amount
     */
    public function calculateFee(float $amount): array
    {
        $fee = 0;

        switch ($this->fee_type) {
            case 'percentage':
                $fee = $amount * $this->percentage_rate;
                break;

            case 'fixed':
                $fee = $this->fixed_amount;
                break;

            case 'tiered':
                $fee = $this->calculateTieredFee($amount);
                break;

            case 'mixed':
                $fee = ($amount * $this->percentage_rate) + $this->fixed_amount;
                break;
        }

        // Apply minimum
        if ($this->minimum_fee && $fee < $this->minimum_fee) {
            $fee = $this->minimum_fee;
        }

        // Apply maximum cap
        if ($this->maximum_fee && $fee > $this->maximum_fee) {
            $fee = $this->maximum_fee;
        }

        return [
            'fee' => round($fee, 2),
            'rate' => $this->percentage_rate,
            'fixed' => $this->fixed_amount,
            'type' => $this->fee_type,
            'config_id' => $this->id,
        ];
    }

    /**
     * Calculate tiered fee based on amount brackets
     */
    protected function calculateTieredFee(float $amount): float
    {
        $tiers = $this->tiers ?? self::DEFAULT_TIERS;
        $fee = 0;

        foreach ($tiers as $tier) {
            if ($amount > ($tier['min'] ?? 0)) {
                $tierMax = $tier['max'] ?? PHP_FLOAT_MAX;
                $tierAmount = min($amount, $tierMax) - ($tier['min'] ?? 0);

                if ($tierAmount > 0) {
                    $fee += $tierAmount * ($tier['rate'] ?? 0);
                }
            }
        }

        return $fee;
    }

    /**
     * Get the effective fee configuration for a transaction
     */
    public static function getForTransaction(
        string $transactionType,
        ?string $provider = null,
        string $accountType = 'customer'
    ): ?self {
        return self::active()
            ->forType($transactionType)
            ->forProvider($provider)
            ->forAccountType($accountType)
            ->orderBy('provider', 'desc') // Specific provider first
            ->orderBy('applies_to', 'desc') // Specific account type first
            ->first();
    }

    // Display helpers

    public function getFeeDescription(): string
    {
        return match ($this->fee_type) {
            'percentage' => number_format($this->percentage_rate * 100, 2) . '%',
            'fixed' => number_format($this->fixed_amount) . ' XOF',
            'tiered' => 'Tiered (volume-based)',
            'mixed' => number_format($this->percentage_rate * 100, 2) . '% + ' .
                       number_format($this->fixed_amount) . ' XOF',
            default => 'Unknown',
        };
    }
}
