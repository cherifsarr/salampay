<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use App\Modules\Accounting\Models\WalletLimit;
use App\Modules\Accounting\Models\WalletTier;
use App\Modules\Accounting\Services\WalletLimitService;
use Illuminate\Console\Command;

class AccountingLimitsCommand extends Command
{
    protected $signature = 'accounting:limits
                            {action=status : Action: status, reset, check-upgrades, assign-defaults}
                            {--wallet= : Specific wallet ID}
                            {--type= : Account type (customer, merchant)}';

    protected $description = 'Manage wallet limits and tiers';

    public function handle(WalletLimitService $limitService): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'status' => $this->showStatus($limitService),
            'reset' => $this->resetLimits($limitService),
            'check-upgrades' => $this->checkUpgrades($limitService),
            'assign-defaults' => $this->assignDefaults($limitService),
            default => $this->error("Unknown action: {$action}") ?? self::FAILURE,
        };
    }

    protected function showStatus(WalletLimitService $limitService): int
    {
        if ($walletId = $this->option('wallet')) {
            return $this->showWalletStatus($walletId, $limitService);
        }

        $this->info('Wallet Limits Overview');
        $this->info('======================');

        // Summary by tier
        $tierStats = WalletLimit::selectRaw('tier_id, COUNT(*) as count')
            ->groupBy('tier_id')
            ->get()
            ->keyBy('tier_id');

        $tiers = WalletTier::where('is_active', true)->orderBy('account_type')->orderBy('level')->get();

        $this->newLine();
        $this->info('Distribution by Tier:');

        $tableData = [];
        foreach ($tiers as $tier) {
            $count = $tierStats[$tier->id]->count ?? 0;
            $tableData[] = [
                $tier->account_type,
                $tier->name,
                $tier->level,
                $count,
                number_format($tier->max_balance, 0) . ' XOF',
            ];
        }

        $this->table(
            ['Type', 'Tier', 'Level', 'Wallets', 'Max Balance'],
            $tableData
        );

        // Wallets eligible for upgrade
        $eligibleCount = WalletLimit::where('eligible_for_upgrade', true)->count();
        $this->newLine();
        $this->line("Wallets eligible for upgrade: {$eligibleCount}");

        // Wallets with overrides
        $overrideCount = WalletLimit::whereNotNull('override_max_balance')
            ->orWhereNotNull('override_daily_limit')
            ->orWhereNotNull('override_monthly_limit')
            ->count();
        $this->line("Wallets with active overrides: {$overrideCount}");

        // Wallets approaching limits
        $approachingDaily = WalletLimit::whereRaw('daily_transaction_used > (SELECT daily_transaction_limit FROM wallet_tiers WHERE wallet_tiers.id = wallet_limits.tier_id) * 0.8')
            ->count();
        $this->line("Wallets at >80% daily limit: {$approachingDaily}");

        return self::SUCCESS;
    }

    protected function showWalletStatus(int $walletId, WalletLimitService $limitService): int
    {
        $wallet = Wallet::find($walletId);

        if (!$wallet) {
            $this->error("Wallet not found: {$walletId}");
            return self::FAILURE;
        }

        $summary = $limitService->getUsageSummary($wallet);

        $this->info("Wallet #{$walletId} - {$wallet->holder_type}");
        $this->info(str_repeat('=', 40));

        $this->newLine();
        $this->info('Tier: ' . $summary['tier']['name'] . ' (Level ' . $summary['tier']['level'] . ')');
        $this->line('Max Balance: ' . number_format($summary['max_balance'], 0) . ' XOF');

        $this->newLine();
        $this->info('Daily Limits:');
        $this->table(
            ['Type', 'Used', 'Limit', 'Remaining', '%'],
            [
                [
                    'Transaction',
                    number_format($summary['daily']['transaction']['used'], 0),
                    number_format($summary['daily']['transaction']['limit'], 0),
                    number_format($summary['daily']['transaction']['remaining'], 0),
                    number_format(($summary['daily']['transaction']['used'] / max(1, $summary['daily']['transaction']['limit'])) * 100, 1) . '%',
                ],
                [
                    'Deposit',
                    number_format($summary['daily']['deposit']['used'], 0),
                    number_format($summary['daily']['deposit']['limit'], 0),
                    number_format($summary['daily']['deposit']['remaining'], 0),
                    number_format(($summary['daily']['deposit']['used'] / max(1, $summary['daily']['deposit']['limit'])) * 100, 1) . '%',
                ],
                [
                    'Withdrawal',
                    number_format($summary['daily']['withdrawal']['used'], 0),
                    number_format($summary['daily']['withdrawal']['limit'], 0),
                    number_format($summary['daily']['withdrawal']['remaining'], 0),
                    number_format(($summary['daily']['withdrawal']['used'] / max(1, $summary['daily']['withdrawal']['limit'])) * 100, 1) . '%',
                ],
            ]
        );

        $this->newLine();
        $this->info('Monthly Transaction:');
        $this->line('  Used: ' . number_format($summary['monthly']['transaction']['used'], 0) . ' XOF');
        $this->line('  Limit: ' . number_format($summary['monthly']['transaction']['limit'], 0) . ' XOF');
        $this->line('  Remaining: ' . number_format($summary['monthly']['transaction']['remaining'], 0) . ' XOF');

        $this->newLine();
        if ($summary['eligible_for_upgrade']) {
            $this->info('✓ Eligible for tier upgrade');
        } else {
            $eligibility = $limitService->checkUpgradeEligibility($wallet);
            if ($eligibility['next_tier']) {
                $this->warn('Next tier: ' . $eligibility['next_tier']);
                foreach ($eligibility['requirements'] as $req => $data) {
                    $status = $data['met'] ? '✓' : '✗';
                    $this->line("  {$status} {$req}: " . json_encode($data));
                }
            } else {
                $this->line('Already at highest tier');
            }
        }

        return self::SUCCESS;
    }

    protected function resetLimits(WalletLimitService $limitService): int
    {
        $this->info('Resetting expired limits...');

        $dailyReset = $limitService->resetDailyLimits();
        $this->line("  Daily limits reset: {$dailyReset} wallets");

        $weeklyReset = $limitService->resetWeeklyLimits();
        $this->line("  Weekly limits reset: {$weeklyReset} wallets");

        $monthlyReset = $limitService->resetMonthlyLimits();
        $this->line("  Monthly limits reset: {$monthlyReset} wallets");

        $this->info('Done.');
        return self::SUCCESS;
    }

    protected function checkUpgrades(WalletLimitService $limitService): int
    {
        $this->info('Checking upgrade eligibility for all wallets...');

        $results = $limitService->checkAllUpgradeEligibility();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Wallets checked', $results['checked']],
                ['Newly eligible', $results['eligible']],
            ]
        );

        return self::SUCCESS;
    }

    protected function assignDefaults(WalletLimitService $limitService): int
    {
        $this->info('Assigning default tiers to wallets without limits...');

        $walletsWithoutLimits = Wallet::whereNotIn('id', WalletLimit::pluck('wallet_id'))->get();

        $count = 0;
        foreach ($walletsWithoutLimits as $wallet) {
            $limitService->assignDefaultTier($wallet);
            $count++;
        }

        $this->info("Assigned default tiers to {$count} wallets.");
        return self::SUCCESS;
    }
}
