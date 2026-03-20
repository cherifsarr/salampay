<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class DailyBalanceSheet extends Model
{
    protected $fillable = [
        'sheet_date',
        'total_bank_accounts',
        'total_mobile_money',
        'total_float',
        'total_assets',
        'total_customer_wallets',
        'total_merchant_wallets',
        'total_pending_payouts',
        'total_liabilities',
        'total_platform_earnings',
        'total_reserves',
        'total_equity',
        'calculated_balance',
        'is_balanced',
        'transaction_count',
        'transaction_volume',
        'fees_collected',
        'fees_paid',
        'breakdown',
        'notes',
    ];

    protected $casts = [
        'sheet_date' => 'date',
        'total_bank_accounts' => 'decimal:2',
        'total_mobile_money' => 'decimal:2',
        'total_float' => 'decimal:2',
        'total_assets' => 'decimal:2',
        'total_customer_wallets' => 'decimal:2',
        'total_merchant_wallets' => 'decimal:2',
        'total_pending_payouts' => 'decimal:2',
        'total_liabilities' => 'decimal:2',
        'total_platform_earnings' => 'decimal:2',
        'total_reserves' => 'decimal:2',
        'total_equity' => 'decimal:2',
        'calculated_balance' => 'decimal:2',
        'is_balanced' => 'boolean',
        'transaction_volume' => 'decimal:2',
        'fees_collected' => 'decimal:2',
        'fees_paid' => 'decimal:2',
        'breakdown' => 'array',
    ];

    // Scopes

    public function scopeBalanced($query)
    {
        return $query->where('is_balanced', true);
    }

    public function scopeUnbalanced($query)
    {
        return $query->where('is_balanced', false);
    }

    public function scopeForPeriod($query, string $start, string $end)
    {
        return $query->whereBetween('sheet_date', [$start, $end]);
    }

    // Helpers

    public function isHealthy(): bool
    {
        return $this->is_balanced && abs($this->calculated_balance) < 0.01;
    }

    public function getDiscrepancy(): float
    {
        return abs($this->calculated_balance);
    }

    public function getSummary(): array
    {
        return [
            'date' => $this->sheet_date->format('Y-m-d'),
            'assets' => $this->total_assets,
            'liabilities' => $this->total_liabilities,
            'equity' => $this->total_equity,
            'is_balanced' => $this->is_balanced,
            'transactions' => $this->transaction_count,
            'volume' => $this->transaction_volume,
            'fees' => $this->fees_collected,
        ];
    }

    /**
     * Get trend data for dashboard charts
     */
    public static function getTrend(int $days = 30): array
    {
        return self::where('sheet_date', '>=', now()->subDays($days))
            ->orderBy('sheet_date')
            ->get()
            ->map(fn($sheet) => $sheet->getSummary())
            ->toArray();
    }
}
