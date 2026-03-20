<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WalletTier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'account_type',
        'level',
        'max_balance',
        'min_balance',
        'daily_transaction_limit',
        'weekly_transaction_limit',
        'monthly_transaction_limit',
        'single_transaction_limit',
        'daily_deposit_limit',
        'monthly_deposit_limit',
        'daily_withdrawal_limit',
        'monthly_withdrawal_limit',
        'daily_transfer_limit',
        'monthly_transfer_limit',
        'kyc_requirements',
        'min_account_age_days',
        'min_monthly_volume',
        'min_successful_transactions',
        'allowed_features',
        'fee_discount_percent',
        'is_default',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'max_balance' => 'decimal:2',
        'min_balance' => 'decimal:2',
        'daily_transaction_limit' => 'decimal:2',
        'weekly_transaction_limit' => 'decimal:2',
        'monthly_transaction_limit' => 'decimal:2',
        'single_transaction_limit' => 'decimal:2',
        'daily_deposit_limit' => 'decimal:2',
        'monthly_deposit_limit' => 'decimal:2',
        'daily_withdrawal_limit' => 'decimal:2',
        'monthly_withdrawal_limit' => 'decimal:2',
        'daily_transfer_limit' => 'decimal:2',
        'monthly_transfer_limit' => 'decimal:2',
        'kyc_requirements' => 'array',
        'allowed_features' => 'array',
        'fee_discount_percent' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Default tier configurations
     * Based on common mobile money tier structures
     */
    public const CUSTOMER_TIERS = [
        'basic' => [
            'name' => 'Basic',
            'level' => 1,
            'max_balance' => 200000,           // 200K XOF
            'daily_transaction_limit' => 100000,
            'monthly_transaction_limit' => 500000,
            'single_transaction_limit' => 50000,
            'daily_deposit_limit' => 100000,
            'monthly_deposit_limit' => 300000,
            'daily_withdrawal_limit' => 50000,
            'monthly_withdrawal_limit' => 200000,
            'daily_transfer_limit' => 50000,
            'monthly_transfer_limit' => 200000,
            'kyc_requirements' => ['phone'],
        ],
        'standard' => [
            'name' => 'Standard',
            'level' => 2,
            'max_balance' => 2000000,          // 2M XOF
            'daily_transaction_limit' => 500000,
            'monthly_transaction_limit' => 5000000,
            'single_transaction_limit' => 200000,
            'daily_deposit_limit' => 500000,
            'monthly_deposit_limit' => 2000000,
            'daily_withdrawal_limit' => 300000,
            'monthly_withdrawal_limit' => 1500000,
            'daily_transfer_limit' => 300000,
            'monthly_transfer_limit' => 1500000,
            'kyc_requirements' => ['phone', 'id_document'],
        ],
        'premium' => [
            'name' => 'Premium',
            'level' => 3,
            'max_balance' => 10000000,         // 10M XOF
            'daily_transaction_limit' => 2000000,
            'monthly_transaction_limit' => 20000000,
            'single_transaction_limit' => 1000000,
            'daily_deposit_limit' => 2000000,
            'monthly_deposit_limit' => 10000000,
            'daily_withdrawal_limit' => 1000000,
            'monthly_withdrawal_limit' => 5000000,
            'daily_transfer_limit' => 1000000,
            'monthly_transfer_limit' => 5000000,
            'kyc_requirements' => ['phone', 'id_document', 'selfie', 'proof_of_address'],
            'fee_discount_percent' => 10,
        ],
    ];

    public const MERCHANT_TIERS = [
        'starter' => [
            'name' => 'Starter',
            'level' => 1,
            'max_balance' => 5000000,          // 5M XOF
            'daily_transaction_limit' => 1000000,
            'monthly_transaction_limit' => 10000000,
            'single_transaction_limit' => 500000,
            'daily_deposit_limit' => 2000000,
            'monthly_deposit_limit' => 10000000,
            'daily_withdrawal_limit' => 500000,
            'monthly_withdrawal_limit' => 5000000,
            'daily_transfer_limit' => 500000,
            'monthly_transfer_limit' => 5000000,
            'kyc_requirements' => ['business_registration'],
        ],
        'business' => [
            'name' => 'Business',
            'level' => 2,
            'max_balance' => 50000000,         // 50M XOF
            'daily_transaction_limit' => 10000000,
            'monthly_transaction_limit' => 100000000,
            'single_transaction_limit' => 5000000,
            'daily_deposit_limit' => 20000000,
            'monthly_deposit_limit' => 100000000,
            'daily_withdrawal_limit' => 5000000,
            'monthly_withdrawal_limit' => 50000000,
            'daily_transfer_limit' => 5000000,
            'monthly_transfer_limit' => 50000000,
            'kyc_requirements' => ['business_registration', 'tax_id', 'bank_statement'],
            'fee_discount_percent' => 15,
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'level' => 3,
            'max_balance' => 500000000,        // 500M XOF
            'daily_transaction_limit' => 100000000,
            'monthly_transaction_limit' => 1000000000,
            'single_transaction_limit' => 50000000,
            'daily_deposit_limit' => 200000000,
            'monthly_deposit_limit' => 1000000000,
            'daily_withdrawal_limit' => 50000000,
            'monthly_withdrawal_limit' => 500000000,
            'daily_transfer_limit' => 50000000,
            'monthly_transfer_limit' => 500000000,
            'kyc_requirements' => ['business_registration', 'tax_id', 'bank_statement', 'audited_financials'],
            'fee_discount_percent' => 25,
        ],
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

    public function walletLimits(): HasMany
    {
        return $this->hasMany(WalletLimit::class, 'tier_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForAccountType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Helpers

    public static function getDefaultTier(string $accountType): ?self
    {
        return self::active()
            ->forAccountType($accountType)
            ->default()
            ->first() ?? self::active()
            ->forAccountType($accountType)
            ->orderBy('level')
            ->first();
    }

    public static function getNextTier(string $accountType, int $currentLevel): ?self
    {
        return self::active()
            ->forAccountType($accountType)
            ->where('level', '>', $currentLevel)
            ->orderBy('level')
            ->first();
    }

    public function canUpgradeTo(self $targetTier): bool
    {
        return $targetTier->account_type === $this->account_type
            && $targetTier->level > $this->level;
    }

    /**
     * Check if a wallet meets the requirements for this tier
     */
    public function meetsRequirements(
        array $kycStatus,
        int $accountAgeDays,
        float $monthlyVolume,
        int $successfulTransactions
    ): bool {
        // Check KYC requirements
        if ($this->kyc_requirements) {
            foreach ($this->kyc_requirements as $requirement) {
                if (!in_array($requirement, $kycStatus)) {
                    return false;
                }
            }
        }

        // Check account age
        if ($this->min_account_age_days > 0 && $accountAgeDays < $this->min_account_age_days) {
            return false;
        }

        // Check monthly volume
        if ($this->min_monthly_volume > 0 && $monthlyVolume < $this->min_monthly_volume) {
            return false;
        }

        // Check transaction count
        if ($this->min_successful_transactions > 0 &&
            $successfulTransactions < $this->min_successful_transactions) {
            return false;
        }

        return true;
    }

    public function getLimitSummary(): array
    {
        return [
            'max_balance' => $this->max_balance,
            'daily' => [
                'transaction' => $this->daily_transaction_limit,
                'deposit' => $this->daily_deposit_limit,
                'withdrawal' => $this->daily_withdrawal_limit,
                'transfer' => $this->daily_transfer_limit,
            ],
            'monthly' => [
                'transaction' => $this->monthly_transaction_limit,
                'deposit' => $this->monthly_deposit_limit,
                'withdrawal' => $this->monthly_withdrawal_limit,
                'transfer' => $this->monthly_transfer_limit,
            ],
            'single' => $this->single_transaction_limit,
        ];
    }
}
