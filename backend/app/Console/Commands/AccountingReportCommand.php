<?php

namespace App\Console\Commands;

use App\Modules\Accounting\Models\DailyBalanceSheet;
use App\Modules\Accounting\Models\FeeConfiguration;
use App\Modules\Accounting\Models\GeneralLedger;
use App\Modules\Accounting\Models\PlatformEarnings;
use App\Modules\Accounting\Models\WalletTier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AccountingReportCommand extends Command
{
    protected $signature = 'accounting:report
                            {type=summary : Report type: summary, fees, earnings, ledger, tiers}
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--export= : Export to file (csv, json)}';

    protected $description = 'Generate accounting reports';

    public function handle(): int
    {
        $type = $this->argument('type');
        $from = $this->option('from') ?? now()->startOfMonth()->format('Y-m-d');
        $to = $this->option('to') ?? now()->format('Y-m-d');

        $this->info("Accounting Report: " . strtoupper($type));
        $this->info("Period: {$from} to {$to}");
        $this->info(str_repeat('=', 50));

        try {
            return match ($type) {
                'summary' => $this->summaryReport($from, $to),
                'fees' => $this->feesReport(),
                'earnings' => $this->earningsReport($from, $to),
                'ledger' => $this->ledgerReport($from, $to),
                'tiers' => $this->tiersReport(),
                default => $this->error("Unknown report type: {$type}") ?? self::FAILURE,
            };
        } catch (\Exception $e) {
            $this->error('Report generation failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function summaryReport(string $from, string $to): int
    {
        $sheets = DailyBalanceSheet::whereBetween('sheet_date', [$from, $to])
            ->orderBy('sheet_date')
            ->get();

        if ($sheets->isEmpty()) {
            $this->warn('No balance sheets found for the period.');
            $this->line('Run: php artisan accounting:snapshot --range=7');
            return self::SUCCESS;
        }

        $this->info('Period Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Transactions', number_format($sheets->sum('transaction_count'))],
                ['Total Volume', number_format($sheets->sum('transaction_volume'), 2) . ' XOF'],
                ['Total Fees Collected', number_format($sheets->sum('fees_collected'), 2) . ' XOF'],
                ['Total Fees Paid', number_format($sheets->sum('fees_paid'), 2) . ' XOF'],
                ['Net Platform Revenue', number_format($sheets->sum('fees_collected') - $sheets->sum('fees_paid'), 2) . ' XOF'],
                ['Balanced Days', $sheets->where('is_balanced', true)->count() . ' / ' . $sheets->count()],
            ]
        );

        $this->newLine();
        $this->info('Latest Balances:');
        $latest = $sheets->last();
        $this->table(
            ['Category', 'Amount (XOF)'],
            [
                ['Total Assets', number_format($latest->total_assets, 2)],
                ['  - Bank Accounts', number_format($latest->total_bank_accounts, 2)],
                ['  - Mobile Money', number_format($latest->total_mobile_money, 2)],
                ['Total Liabilities', number_format($latest->total_liabilities, 2)],
                ['  - Customer Wallets', number_format($latest->total_customer_wallets, 2)],
                ['  - Merchant Wallets', number_format($latest->total_merchant_wallets, 2)],
                ['Total Equity', number_format($latest->total_equity, 2)],
            ]
        );

        return self::SUCCESS;
    }

    protected function feesReport(): int
    {
        $this->info('Active Fee Configurations:');

        $fees = FeeConfiguration::where('is_active', true)
            ->orderBy('transaction_type')
            ->get();

        $tableData = [];
        foreach ($fees as $fee) {
            $rate = match ($fee->fee_type) {
                'percentage' => number_format($fee->percentage_rate * 100, 2) . '%',
                'fixed' => number_format($fee->fixed_amount, 0) . ' XOF',
                'mixed' => number_format($fee->percentage_rate * 100, 2) . '% + ' . number_format($fee->fixed_amount, 0) . ' XOF',
                'tiered' => 'Tiered (see details)',
                default => '-',
            };

            $limits = '';
            if ($fee->minimum_fee) {
                $limits .= 'Min: ' . number_format($fee->minimum_fee, 0);
            }
            if ($fee->maximum_fee) {
                $limits .= ($limits ? ' / ' : '') . 'Max: ' . number_format($fee->maximum_fee, 0);
            }

            $tableData[] = [
                $fee->code,
                $fee->transaction_type,
                $fee->fee_type,
                $rate,
                $fee->payer,
                $limits ?: '-',
            ];
        }

        $this->table(
            ['Code', 'Txn Type', 'Fee Type', 'Rate', 'Payer', 'Limits'],
            $tableData
        );

        // Show tiered details
        $tieredFees = $fees->where('fee_type', 'tiered');
        if ($tieredFees->isNotEmpty()) {
            $this->newLine();
            $this->info('Tiered Fee Details:');
            foreach ($tieredFees as $fee) {
                $this->line("  {$fee->code}:");
                foreach ($fee->tiers ?? [] as $tier) {
                    $max = $tier['max'] ? number_format($tier['max'], 0) : '∞';
                    $this->line("    " . number_format($tier['min'], 0) . " - {$max}: " .
                        number_format($tier['rate'] * 100, 2) . '%');
                }
            }
        }

        return self::SUCCESS;
    }

    protected function earningsReport(string $from, string $to): int
    {
        $this->info('Platform Earnings:');

        // Summary by type
        $earningsByType = PlatformEarnings::whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->select('type', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('type')
            ->get();

        $tableData = [];
        foreach ($earningsByType as $earning) {
            $tableData[] = [
                $earning->type,
                number_format($earning->count),
                number_format($earning->total, 2) . ' XOF',
            ];
        }

        $this->table(
            ['Type', 'Count', 'Total'],
            $tableData
        );

        // Daily breakdown
        $this->newLine();
        $this->info('Daily Earnings:');

        $dailyEarnings = PlatformEarnings::whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $tableData = [];
        foreach ($dailyEarnings as $daily) {
            $tableData[] = [
                $daily->date,
                number_format($daily->count),
                number_format($daily->total, 2) . ' XOF',
            ];
        }

        if (!empty($tableData)) {
            $this->table(['Date', 'Transactions', 'Earnings'], $tableData);
        } else {
            $this->warn('No earnings recorded for this period.');
        }

        // Withdrawn vs unwithrawn
        $this->newLine();
        $withdrawn = PlatformEarnings::where('is_withdrawn', true)->sum('amount');
        $unwithrawn = PlatformEarnings::where('is_withdrawn', false)->sum('amount');
        $this->info('Earnings Status:');
        $this->line("  Withdrawn:   " . number_format($withdrawn, 2) . ' XOF');
        $this->line("  Unwithrawn:  " . number_format($unwithrawn, 2) . ' XOF');
        $this->line("  Total:       " . number_format($withdrawn + $unwithrawn, 2) . ' XOF');

        return self::SUCCESS;
    }

    protected function ledgerReport(string $from, string $to): int
    {
        $this->info('General Ledger Summary:');

        // Summary by account
        $ledgerByAccount = GeneralLedger::whereBetween('entry_date', [$from, $to])
            ->where('status', 'posted')
            ->select(
                'account_code',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit'),
                DB::raw('COUNT(*) as entries')
            )
            ->groupBy('account_code')
            ->orderBy('account_code')
            ->get();

        $tableData = [];
        foreach ($ledgerByAccount as $account) {
            $tableData[] = [
                $account->account_code,
                number_format($account->entries),
                number_format($account->total_debit, 2),
                number_format($account->total_credit, 2),
                number_format($account->total_debit - $account->total_credit, 2),
            ];
        }

        if (!empty($tableData)) {
            $this->table(
                ['Account', 'Entries', 'Total Debit', 'Total Credit', 'Net'],
                $tableData
            );

            // Verify double-entry
            $totalDebits = $ledgerByAccount->sum('total_debit');
            $totalCredits = $ledgerByAccount->sum('total_credit');

            $this->newLine();
            $this->info('Double-Entry Verification:');
            $this->line("  Total Debits:  " . number_format($totalDebits, 2) . ' XOF');
            $this->line("  Total Credits: " . number_format($totalCredits, 2) . ' XOF');
            $this->line("  Difference:    " . number_format($totalDebits - $totalCredits, 2) . ' XOF');

            if (abs($totalDebits - $totalCredits) < 0.01) {
                $this->info('  ✓ Ledger is balanced');
            } else {
                $this->error('  ✗ Ledger imbalance detected!');
            }
        } else {
            $this->warn('No ledger entries found for this period.');
        }

        return self::SUCCESS;
    }

    protected function tiersReport(): int
    {
        $this->info('Customer Wallet Tiers:');

        $customerTiers = WalletTier::where('account_type', 'customer')
            ->where('is_active', true)
            ->orderBy('level')
            ->get();

        $tableData = [];
        foreach ($customerTiers as $tier) {
            $tableData[] = [
                $tier->name,
                $tier->level,
                number_format($tier->max_balance, 0),
                number_format($tier->daily_transaction_limit, 0),
                number_format($tier->monthly_transaction_limit, 0),
                number_format($tier->single_transaction_limit, 0),
                $tier->fee_discount_percent . '%',
                $tier->is_default ? '✓' : '',
            ];
        }

        $this->table(
            ['Name', 'Level', 'Max Balance', 'Daily Limit', 'Monthly Limit', 'Single Limit', 'Discount', 'Default'],
            $tableData
        );

        $this->newLine();
        $this->info('Merchant Wallet Tiers:');

        $merchantTiers = WalletTier::where('account_type', 'merchant')
            ->where('is_active', true)
            ->orderBy('level')
            ->get();

        $tableData = [];
        foreach ($merchantTiers as $tier) {
            $tableData[] = [
                $tier->name,
                $tier->level,
                number_format($tier->max_balance, 0),
                number_format($tier->daily_transaction_limit, 0),
                number_format($tier->monthly_transaction_limit, 0),
                number_format($tier->single_transaction_limit, 0),
                $tier->fee_discount_percent . '%',
                $tier->is_default ? '✓' : '',
            ];
        }

        $this->table(
            ['Name', 'Level', 'Max Balance', 'Daily Limit', 'Monthly Limit', 'Single Limit', 'Discount', 'Default'],
            $tableData
        );

        return self::SUCCESS;
    }
}
