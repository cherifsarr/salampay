<?php

namespace App\Modules\Accounting\Models;

use App\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WalletLimit extends Model
{
    protected $fillable = [
        'uuid',
        'wallet_id',
        'tier_id',
        'daily_transaction_used',
        'weekly_transaction_used',
        'monthly_transaction_used',
        'daily_deposit_used',
        'monthly_deposit_used',
        'daily_withdrawal_used',
        'monthly_withdrawal_used',
        'daily_transfer_used',
        'monthly_transfer_used',
        'daily_reset_date',
        'weekly_reset_date',
        'monthly_reset_date',
        'override_max_balance',
        'override_daily_limit',
        'override_monthly_limit',
        'override_expires_at',
        'override_reason',
        'override_by',
        'tier_assigned_at',
        'last_tier_review_at',
        'eligible_for_upgrade',
    ];

    protected $casts = [
        'daily_transaction_used' => 'decimal:2',
        'weekly_transaction_used' => 'decimal:2',
        'monthly_transaction_used' => 'decimal:2',
        'daily_deposit_used' => 'decimal:2',
        'monthly_deposit_used' => 'decimal:2',
        'daily_withdrawal_used' => 'decimal:2',
        'monthly_withdrawal_used' => 'decimal:2',
        'daily_transfer_used' => 'decimal:2',
        'monthly_transfer_used' => 'decimal:2',
        'daily_reset_date' => 'date',
        'weekly_reset_date' => 'date',
        'monthly_reset_date' => 'date',
        'override_max_balance' => 'decimal:2',
        'override_daily_limit' => 'decimal:2',
        'override_monthly_limit' => 'decimal:2',
        'override_expires_at' => 'datetime',
        'tier_assigned_at' => 'datetime',
        'last_tier_review_at' => 'datetime',
        'eligible_for_upgrade' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
            if (!$model->daily_reset_date) {
                $model->daily_reset_date = today();
            }
            if (!$model->weekly_reset_date) {
                $model->weekly_reset_date = today()->startOfWeek();
            }
            if (!$model->monthly_reset_date) {
                $model->monthly_reset_date = today()->startOfMonth();
            }
            if (!$model->tier_assigned_at) {
                $model->tier_assigned_at = now();
            }
        });
    }

    // Relationships

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(WalletTier::class);
    }

    // Reset period checks

    public function resetIfNeeded(): void
    {
        $today = today();

        // Daily reset
        if ($this->daily_reset_date < $today) {
            $this->daily_transaction_used = 0;
            $this->daily_deposit_used = 0;
            $this->daily_withdrawal_used = 0;
            $this->daily_transfer_used = 0;
            $this->daily_reset_date = $today;
        }

        // Weekly reset
        $startOfWeek = $today->startOfWeek();
        if ($this->weekly_reset_date < $startOfWeek) {
            $this->weekly_transaction_used = 0;
            $this->weekly_reset_date = $startOfWeek;
        }

        // Monthly reset
        $startOfMonth = $today->startOfMonth();
        if ($this->monthly_reset_date < $startOfMonth) {
            $this->monthly_transaction_used = 0;
            $this->monthly_deposit_used = 0;
            $this->monthly_withdrawal_used = 0;
            $this->monthly_transfer_used = 0;
            $this->monthly_reset_date = $startOfMonth;
        }

        if ($this->isDirty()) {
            $this->save();
        }
    }

    // Limit checks

    /**
     * Get the effective max balance (with override if applicable)
     */
    public function getEffectiveMaxBalance(): float
    {
        if ($this->override_max_balance &&
            (!$this->override_expires_at || $this->override_expires_at > now())) {
            return $this->override_max_balance;
        }
        return $this->tier->max_balance;
    }

    /**
     * Get the effective daily limit (with override if applicable)
     */
    public function getEffectiveDailyLimit(): float
    {
        if ($this->override_daily_limit &&
            (!$this->override_expires_at || $this->override_expires_at > now())) {
            return $this->override_daily_limit;
        }
        return $this->tier->daily_transaction_limit;
    }

    /**
     * Check if a transaction amount would exceed limits
     */
    public function canTransact(float $amount, string $type = 'transaction'): array
    {
        $this->resetIfNeeded();
        $tier = $this->tier;

        $errors = [];

        // Check single transaction limit
        if ($amount > $tier->single_transaction_limit) {
            $errors[] = [
                'code' => 'single_limit_exceeded',
                'message' => 'Amount exceeds single transaction limit',
                'limit' => $tier->single_transaction_limit,
                'requested' => $amount,
            ];
        }

        // Check daily limit
        $dailyUsed = $this->getDailyUsed($type);
        $dailyLimit = $this->getDailyLimit($type);
        if (($dailyUsed + $amount) > $dailyLimit) {
            $errors[] = [
                'code' => 'daily_limit_exceeded',
                'message' => 'Daily limit would be exceeded',
                'limit' => $dailyLimit,
                'used' => $dailyUsed,
                'remaining' => max(0, $dailyLimit - $dailyUsed),
                'requested' => $amount,
            ];
        }

        // Check monthly limit
        $monthlyUsed = $this->getMonthlyUsed($type);
        $monthlyLimit = $this->getMonthlyLimit($type);
        if (($monthlyUsed + $amount) > $monthlyLimit) {
            $errors[] = [
                'code' => 'monthly_limit_exceeded',
                'message' => 'Monthly limit would be exceeded',
                'limit' => $monthlyLimit,
                'used' => $monthlyUsed,
                'remaining' => max(0, $monthlyLimit - $monthlyUsed),
                'requested' => $amount,
            ];
        }

        return [
            'allowed' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check if deposit would exceed max balance
     */
    public function canDeposit(float $amount, float $currentBalance): array
    {
        $errors = [];
        $maxBalance = $this->getEffectiveMaxBalance();

        if (($currentBalance + $amount) > $maxBalance) {
            $errors[] = [
                'code' => 'max_balance_exceeded',
                'message' => 'Deposit would exceed maximum wallet balance',
                'max_balance' => $maxBalance,
                'current_balance' => $currentBalance,
                'max_deposit' => max(0, $maxBalance - $currentBalance),
                'requested' => $amount,
            ];
        }

        // Also check deposit limits
        $depositCheck = $this->canTransact($amount, 'deposit');
        if (!$depositCheck['allowed']) {
            $errors = array_merge($errors, $depositCheck['errors']);
        }

        return [
            'allowed' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Record usage after a transaction
     */
    public function recordUsage(float $amount, string $type): void
    {
        $this->resetIfNeeded();

        switch ($type) {
            case 'deposit':
                $this->daily_deposit_used += $amount;
                $this->monthly_deposit_used += $amount;
                break;
            case 'withdrawal':
                $this->daily_withdrawal_used += $amount;
                $this->monthly_withdrawal_used += $amount;
                break;
            case 'transfer':
                $this->daily_transfer_used += $amount;
                $this->monthly_transfer_used += $amount;
                break;
        }

        // Always record to general transaction tracking
        $this->daily_transaction_used += $amount;
        $this->weekly_transaction_used += $amount;
        $this->monthly_transaction_used += $amount;

        $this->save();
    }

    // Helper methods for getting limits by type

    protected function getDailyUsed(string $type): float
    {
        return match ($type) {
            'deposit' => $this->daily_deposit_used,
            'withdrawal' => $this->daily_withdrawal_used,
            'transfer' => $this->daily_transfer_used,
            default => $this->daily_transaction_used,
        };
    }

    protected function getDailyLimit(string $type): float
    {
        $tier = $this->tier;
        return match ($type) {
            'deposit' => $tier->daily_deposit_limit,
            'withdrawal' => $tier->daily_withdrawal_limit,
            'transfer' => $tier->daily_transfer_limit,
            default => $this->getEffectiveDailyLimit(),
        };
    }

    protected function getMonthlyUsed(string $type): float
    {
        return match ($type) {
            'deposit' => $this->monthly_deposit_used,
            'withdrawal' => $this->monthly_withdrawal_used,
            'transfer' => $this->monthly_transfer_used,
            default => $this->monthly_transaction_used,
        };
    }

    protected function getMonthlyLimit(string $type): float
    {
        $tier = $this->tier;
        return match ($type) {
            'deposit' => $tier->monthly_deposit_limit,
            'withdrawal' => $tier->monthly_withdrawal_limit,
            'transfer' => $tier->monthly_transfer_limit,
            default => $tier->monthly_transaction_limit,
        };
    }

    /**
     * Get current usage summary
     */
    public function getUsageSummary(): array
    {
        $this->resetIfNeeded();
        $tier = $this->tier;

        return [
            'tier' => [
                'name' => $tier->name,
                'level' => $tier->level,
            ],
            'max_balance' => $this->getEffectiveMaxBalance(),
            'daily' => [
                'transaction' => [
                    'used' => $this->daily_transaction_used,
                    'limit' => $this->getEffectiveDailyLimit(),
                    'remaining' => max(0, $this->getEffectiveDailyLimit() - $this->daily_transaction_used),
                ],
                'deposit' => [
                    'used' => $this->daily_deposit_used,
                    'limit' => $tier->daily_deposit_limit,
                    'remaining' => max(0, $tier->daily_deposit_limit - $this->daily_deposit_used),
                ],
                'withdrawal' => [
                    'used' => $this->daily_withdrawal_used,
                    'limit' => $tier->daily_withdrawal_limit,
                    'remaining' => max(0, $tier->daily_withdrawal_limit - $this->daily_withdrawal_used),
                ],
            ],
            'monthly' => [
                'transaction' => [
                    'used' => $this->monthly_transaction_used,
                    'limit' => $tier->monthly_transaction_limit,
                    'remaining' => max(0, $tier->monthly_transaction_limit - $this->monthly_transaction_used),
                ],
            ],
            'eligible_for_upgrade' => $this->eligible_for_upgrade,
        ];
    }
}
