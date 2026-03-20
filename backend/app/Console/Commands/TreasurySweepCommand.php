<?php

namespace App\Console\Commands;

use App\Modules\Treasury\Services\TreasuryService;
use Illuminate\Console\Command;

class TreasurySweepCommand extends Command
{
    protected $signature = 'treasury:sweep
                            {--dry-run : Show what would be swept without executing}
                            {--provider= : Only sweep specific provider}';

    protected $description = 'Sweep excess funds from provider accounts to bank';

    public function handle(TreasuryService $treasuryService): int
    {
        $this->info('Treasury Sweep: Starting...');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No transfers will be executed');
        }

        try {
            // Sync balances first
            $this->info('Syncing provider balances...');
            $balances = $treasuryService->syncAllProviderBalances();

            foreach ($balances as $provider => $result) {
                if ($result['status'] === 'success') {
                    $this->line("  {$provider}: " . number_format($result['balance']) . ' XOF');
                } else {
                    $this->error("  {$provider}: Failed - {$result['error']}");
                }
            }

            if ($this->option('dry-run')) {
                $this->info('');
                $this->info('Accounts that would be swept:');

                $overview = $treasuryService->getOverview();
                foreach ($overview['provider_accounts'] as $account) {
                    if ($account['status'] === 'critical_high' || $account['status'] === 'warning_high') {
                        $excess = $account['balance'] - ($account['maximum'] * 0.7);
                        $this->line("  {$account['provider']}: " . number_format($excess) . ' XOF');
                    }
                }

                return self::SUCCESS;
            }

            // Execute sweeps
            $results = $treasuryService->sweepExcessFunds();

            if (empty($results)) {
                $this->info('No accounts require sweeping.');
                return self::SUCCESS;
            }

            if (isset($results['error'])) {
                $this->error($results['error']);
                return self::FAILURE;
            }

            $this->info('');
            $this->info('Sweep Results:');

            foreach ($results as $result) {
                if ($result['status'] === 'failed') {
                    $this->error("  {$result['provider']}: FAILED - {$result['error']}");
                } else {
                    $this->info("  {$result['provider']}: " . number_format($result['amount']) . " XOF → Bank");
                    $this->line("    Reference: {$result['reference']}");
                    $this->line("    Status: {$result['status']}");
                }
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Sweep failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
