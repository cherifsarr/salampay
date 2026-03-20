<?php

namespace App\Console\Commands;

use App\Modules\Treasury\Services\TreasuryService;
use Illuminate\Console\Command;

class TreasuryFundCommand extends Command
{
    protected $signature = 'treasury:fund
                            {--dry-run : Show what would be funded without executing}
                            {--provider= : Only fund specific provider}';

    protected $description = 'Fund provider accounts that are below minimum balance';

    public function handle(TreasuryService $treasuryService): int
    {
        $this->info('Treasury Fund: Starting...');

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
                $this->info('Accounts that would be funded:');

                $overview = $treasuryService->getOverview();
                foreach ($overview['provider_accounts'] as $account) {
                    if ($account['status'] === 'critical_low' || $account['status'] === 'warning_low') {
                        $deficit = $account['minimum'] * 2 - $account['balance'];
                        $this->line("  {$account['provider']}: " . number_format($deficit) . ' XOF needed');
                    }
                }

                return self::SUCCESS;
            }

            // Execute funding
            $results = $treasuryService->fundLowAccounts();

            if (empty($results)) {
                $this->info('No accounts require funding.');
                return self::SUCCESS;
            }

            if (isset($results['error'])) {
                $this->error($results['error']);
                return self::FAILURE;
            }

            $this->info('');
            $this->info('Funding Results:');

            foreach ($results as $result) {
                if ($result['status'] === 'failed') {
                    $this->error("  {$result['provider']}: FAILED - {$result['error']}");
                } else {
                    $this->info("  {$result['provider']}: Bank → " . number_format($result['amount']) . " XOF");
                    $this->line("    Reference: {$result['reference']}");
                    $this->line("    Status: {$result['status']}");
                }
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Funding failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
