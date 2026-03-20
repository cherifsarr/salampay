<?php

namespace App\Console\Commands;

use App\Modules\Treasury\Services\ReconciliationService;
use App\Modules\Treasury\Services\TreasuryService;
use Illuminate\Console\Command;

class TreasuryReconcileCommand extends Command
{
    protected $signature = 'treasury:reconcile
                            {--date= : Date to reconcile (YYYY-MM-DD), defaults to yesterday}
                            {--snapshot : Only create balance snapshots}
                            {--verify-ledger : Only verify ledger integrity}';

    protected $description = 'Perform treasury reconciliation and verify book balance';

    public function handle(
        ReconciliationService $reconciliationService,
        TreasuryService $treasuryService
    ): int {
        $date = $this->option('date') ?? now()->subDay()->format('Y-m-d');

        $this->info("Treasury Reconciliation: {$date}");
        $this->info(str_repeat('=', 50));

        try {
            // Snapshot only mode
            if ($this->option('snapshot')) {
                $this->info('Creating balance snapshots...');
                $reconciliationService->createBalanceSnapshots('manual');
                $this->info('Snapshots created successfully.');
                return self::SUCCESS;
            }

            // Verify ledger only mode
            if ($this->option('verify-ledger')) {
                $this->info('Verifying ledger integrity...');
                $issues = $reconciliationService->verifyLedgerIntegrity($date);

                if (empty($issues)) {
                    $this->info('Ledger integrity verified - no issues found.');
                    return self::SUCCESS;
                }

                $this->error('Ledger integrity issues found:');
                foreach ($issues as $issue) {
                    $this->line("  - [{$issue['type']}] " . json_encode($issue));
                }
                return self::FAILURE;
            }

            // Full reconciliation
            $this->info('Syncing provider balances...');
            $balances = $treasuryService->syncAllProviderBalances();

            $this->info('');
            $this->info('Account Balances:');

            foreach ($balances as $provider => $result) {
                if ($result['status'] === 'success') {
                    $this->line("  {$provider}: " . number_format($result['balance']) . ' XOF');
                } else {
                    $this->warn("  {$provider}: Could not sync - {$result['error']}");
                }
            }

            $this->info('');
            $this->info('Performing reconciliation...');

            $report = $reconciliationService->performDailyReconciliation($date);

            $this->info('');
            $this->info('Reconciliation Report:');
            $this->info(str_repeat('-', 40));

            $this->line('ASSETS:');
            $this->line("  Custodian (Bank):    " . number_format($report->total_custodian_balance) . ' XOF');
            $this->line("  Provider (Mobile):   " . number_format($report->total_provider_balance) . ' XOF');
            $this->line("  Total Assets:        " . number_format($report->actual_total) . ' XOF');

            $this->info('');
            $this->line('LIABILITIES:');
            $this->line("  Customer Wallets:    " . number_format($report->total_customer_wallets) . ' XOF');
            $this->line("  Merchant Wallets:    " . number_format($report->total_merchant_wallets) . ' XOF');
            $this->line("  Platform Fees:       " . number_format($report->total_platform_fees) . ' XOF');
            $this->line("  Pending Tx:          " . number_format($report->total_pending_transactions) . ' XOF');
            $this->line("  Total Liabilities:   " . number_format($report->expected_total) . ' XOF');

            $this->info('');
            $this->info(str_repeat('-', 40));

            if ($report->status === 'balanced') {
                $this->info("STATUS: BALANCED");
                if ($report->discrepancy > 0) {
                    $this->line("  (Discrepancy: " . number_format($report->discrepancy) . " XOF - within tolerance)");
                }
            } else {
                $this->error("STATUS: DISCREPANCY DETECTED");
                $this->error("  Amount: " . number_format($report->discrepancy) . " XOF");
            }

            $this->info('');
            $this->line("Report ID: {$report->uuid}");

            return $report->status === 'balanced' ? self::SUCCESS : self::FAILURE;

        } catch (\Exception $e) {
            $this->error('Reconciliation failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
