<?php

namespace App\Console\Commands;

use App\Modules\Accounting\Services\AccountingService;
use Illuminate\Console\Command;

class AccountingBalanceCommand extends Command
{
    protected $signature = 'accounting:balance
                            {--detailed : Show detailed breakdown}
                            {--json : Output as JSON}';

    protected $description = 'Verify platform balance (Assets = Liabilities + Equity)';

    public function handle(AccountingService $accountingService): int
    {
        $this->info('Platform Balance Verification');
        $this->info('=============================');

        try {
            $balance = $accountingService->verifyPlatformBalance();

            if ($this->option('json')) {
                $this->line(json_encode($balance, JSON_PRETTY_PRINT));
                return self::SUCCESS;
            }

            // Status indicator
            if ($balance['is_balanced']) {
                $this->info('✓ BALANCED');
            } else {
                $this->error('✗ IMBALANCE DETECTED');
            }

            $this->newLine();

            // Assets
            $this->info('ASSETS:');
            $this->table(
                ['Account', 'Balance (XOF)'],
                [
                    ['Bank Accounts', number_format($balance['assets']['bank_accounts'], 2)],
                    ['Mobile Money', number_format($balance['assets']['mobile_money'], 2)],
                    ['TOTAL ASSETS', number_format($balance['assets']['total'], 2)],
                ]
            );

            // Liabilities
            $this->info('LIABILITIES:');
            $this->table(
                ['Account', 'Balance (XOF)'],
                [
                    ['Customer Wallets', number_format($balance['liabilities']['customer_wallets'], 2)],
                    ['Merchant Wallets', number_format($balance['liabilities']['merchant_wallets'], 2)],
                    ['Pending Payouts', number_format($balance['liabilities']['pending_payouts'], 2)],
                    ['TOTAL LIABILITIES', number_format($balance['liabilities']['total'], 2)],
                ]
            );

            // Equity
            $this->info('EQUITY:');
            $this->table(
                ['Account', 'Balance (XOF)'],
                [
                    ['Platform Earnings', number_format($balance['equity']['platform_earnings'], 2)],
                    ['TOTAL EQUITY', number_format($balance['equity']['total'], 2)],
                ]
            );

            // Balance equation
            $this->newLine();
            $this->info('BALANCE EQUATION:');
            $this->line('  Assets - Liabilities - Equity = ' .
                number_format($balance['balance_check']['assets_minus_liabilities_minus_equity'], 2));
            $this->line('  Expected: 0.00');

            if (!$balance['is_balanced']) {
                $this->newLine();
                $this->error('WARNING: Platform is not balanced!');
                $this->error('Difference: ' .
                    number_format($balance['balance_check']['assets_minus_liabilities_minus_equity'], 2) . ' XOF');
                $this->warn('Please investigate immediately and run reconciliation.');
                return self::FAILURE;
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Balance check failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
