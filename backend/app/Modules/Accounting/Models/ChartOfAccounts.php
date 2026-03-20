<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccounts extends Model
{
    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'code',
        'name',
        'type',
        'subtype',
        'parent_code',
        'level',
        'normal_balance',
        'is_active',
        'description',
    ];

    protected $casts = [
        'normal_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_code', 'code');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_code', 'code');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(GeneralLedger::class, 'account_code', 'code');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeAssets($query)
    {
        return $query->byType('asset');
    }

    public function scopeLiabilities($query)
    {
        return $query->byType('liability');
    }

    public function scopeEquity($query)
    {
        return $query->byType('equity');
    }

    public function scopeRevenue($query)
    {
        return $query->byType('revenue');
    }

    public function scopeExpenses($query)
    {
        return $query->byType('expense');
    }

    // Helpers

    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense']);
    }

    public function isCreditNormal(): bool
    {
        return in_array($this->type, ['liability', 'equity', 'revenue']);
    }

    public function getCurrentBalance(): float
    {
        return GeneralLedger::getAccountBalance($this->code);
    }

    /**
     * Seed default chart of accounts
     */
    public static function seedDefaults(): void
    {
        $accounts = [
            // Assets (1xxx)
            ['code' => '1000', 'name' => 'Assets', 'type' => 'asset', 'subtype' => 'cash', 'level' => 1],
            ['code' => '1100', 'name' => 'Bank - Main Operating', 'type' => 'asset', 'subtype' => 'cash', 'parent_code' => '1000', 'level' => 2],
            ['code' => '1110', 'name' => 'Bank - Sweep Account', 'type' => 'asset', 'subtype' => 'cash', 'parent_code' => '1000', 'level' => 2],
            ['code' => '1120', 'name' => 'Bank - Reserve Account', 'type' => 'asset', 'subtype' => 'cash', 'parent_code' => '1000', 'level' => 2],
            ['code' => '1200', 'name' => 'Mobile Money - Wave', 'type' => 'asset', 'subtype' => 'mobile_money', 'parent_code' => '1000', 'level' => 2],
            ['code' => '1210', 'name' => 'Mobile Money - Orange', 'type' => 'asset', 'subtype' => 'mobile_money', 'parent_code' => '1000', 'level' => 2],
            ['code' => '1220', 'name' => 'Mobile Money - Free', 'type' => 'asset', 'subtype' => 'mobile_money', 'parent_code' => '1000', 'level' => 2],
            ['code' => '1230', 'name' => 'Mobile Money - Wizall', 'type' => 'asset', 'subtype' => 'mobile_money', 'parent_code' => '1000', 'level' => 2],
            ['code' => '1240', 'name' => 'Mobile Money - E-Money', 'type' => 'asset', 'subtype' => 'mobile_money', 'parent_code' => '1000', 'level' => 2],
            ['code' => '1300', 'name' => 'Float In Transit', 'type' => 'asset', 'subtype' => 'float', 'parent_code' => '1000', 'level' => 2],

            // Liabilities (2xxx)
            ['code' => '2000', 'name' => 'Liabilities', 'type' => 'liability', 'subtype' => 'payable', 'level' => 1],
            ['code' => '2100', 'name' => 'Customer Wallets', 'type' => 'liability', 'subtype' => 'customer_wallet', 'parent_code' => '2000', 'level' => 2],
            ['code' => '2200', 'name' => 'Merchant Wallets', 'type' => 'liability', 'subtype' => 'merchant_wallet', 'parent_code' => '2000', 'level' => 2],
            ['code' => '2300', 'name' => 'Pending Settlements', 'type' => 'liability', 'subtype' => 'pending', 'parent_code' => '2000', 'level' => 2],
            ['code' => '2310', 'name' => 'Pending Refunds', 'type' => 'liability', 'subtype' => 'pending', 'parent_code' => '2000', 'level' => 2],

            // Equity (3xxx)
            ['code' => '3000', 'name' => 'Equity', 'type' => 'equity', 'subtype' => 'retained_earnings', 'level' => 1],
            ['code' => '3100', 'name' => 'Platform Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'parent_code' => '3000', 'level' => 2],
            ['code' => '3200', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'parent_code' => '3000', 'level' => 2],
            ['code' => '3300', 'name' => 'Reserves', 'type' => 'equity', 'subtype' => 'reserves', 'parent_code' => '3000', 'level' => 2],

            // Revenue (4xxx)
            ['code' => '4000', 'name' => 'Revenue', 'type' => 'revenue', 'subtype' => 'fee_income', 'level' => 1],
            ['code' => '4100', 'name' => 'Deposit Fees', 'type' => 'revenue', 'subtype' => 'fee_income', 'parent_code' => '4000', 'level' => 2],
            ['code' => '4110', 'name' => 'Withdrawal Fees', 'type' => 'revenue', 'subtype' => 'fee_income', 'parent_code' => '4000', 'level' => 2],
            ['code' => '4120', 'name' => 'Transfer Fees', 'type' => 'revenue', 'subtype' => 'fee_income', 'parent_code' => '4000', 'level' => 2],
            ['code' => '4130', 'name' => 'Payment Processing Fees', 'type' => 'revenue', 'subtype' => 'fee_income', 'parent_code' => '4000', 'level' => 2],
            ['code' => '4140', 'name' => 'Payout Fees', 'type' => 'revenue', 'subtype' => 'fee_income', 'parent_code' => '4000', 'level' => 2],
            ['code' => '4200', 'name' => 'Interest Income', 'type' => 'revenue', 'subtype' => 'interest_income', 'parent_code' => '4000', 'level' => 2],

            // Expenses (5xxx)
            ['code' => '5000', 'name' => 'Expenses', 'type' => 'expense', 'subtype' => 'operational', 'level' => 1],
            ['code' => '5100', 'name' => 'Provider Fees', 'type' => 'expense', 'subtype' => 'provider_cost', 'parent_code' => '5000', 'level' => 2],
            ['code' => '5200', 'name' => 'Bank Charges', 'type' => 'expense', 'subtype' => 'bank_cost', 'parent_code' => '5000', 'level' => 2],
            ['code' => '5300', 'name' => 'Refund Costs', 'type' => 'expense', 'subtype' => 'operational', 'parent_code' => '5000', 'level' => 2],
        ];

        foreach ($accounts as $account) {
            self::updateOrCreate(
                ['code' => $account['code']],
                $account
            );
        }
    }
}
