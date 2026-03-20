<?php

namespace App\Modules\Accounting\Services;

use App\Models\Wallet;
use App\Modules\Accounting\Models\WalletLimit;
use App\Modules\Accounting\Models\WalletTier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletLimitService
{
    /**
     * Assign default tier to a wallet
     */
    public function assignDefaultTier(Wallet $wallet): WalletLimit
    {
        $accountType = $wallet->holder_type === 'merchant' ? 'merchant' : 'customer';

        $defaultTier = WalletTier::where('account_type', $accountType)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if (!$defaultTier) {
            throw new \Exception("No default tier found for account type: {$accountType}");
        }

        return WalletLimit::updateOrCreate(
            ['wallet_id' => $wallet->id],
            ['tier_id' => $defaultTier->id]
        );
    }

    /**
     * Get or create wallet limit
     */
    public function getOrCreateLimit(Wallet $wallet): WalletLimit
    {
        $limit = WalletLimit::where('wallet_id', $wallet->id)->first();

        if (!$limit) {
            $limit = $this->assignDefaultTier($wallet);
        }

        return $limit;
    }

    /**
     * Check if transaction is allowed
     */
    public function canTransact(Wallet $wallet, float $amount, string $type = 'transaction'): array
    {
        $limit = $this->getOrCreateLimit($wallet);
        return $limit->canTransact($amount, $type);
    }

    /**
     * Check if deposit is allowed
     */
    public function canDeposit(Wallet $wallet, float $amount): array
    {
        $limit = $this->getOrCreateLimit($wallet);
        return $limit->canDeposit($amount, $wallet->balance);
    }

    /**
     * Record transaction usage
     */
    public function recordUsage(Wallet $wallet, float $amount, string $type): void
    {
        $limit = $this->getOrCreateLimit($wallet);
        $limit->recordUsage($amount, $type);
    }

    /**
     * Upgrade wallet to a new tier
     */
    public function upgradeTier(Wallet $wallet, string $tierCode, ?string $reason = null): WalletLimit
    {
        $accountType = $wallet->holder_type === 'merchant' ? 'merchant' : 'customer';

        $newTier = WalletTier::where('code', $tierCode)
            ->where('account_type', $accountType)
            ->where('is_active', true)
            ->first();

        if (!$newTier) {
            throw new \Exception("Tier not found: {$tierCode}");
        }

        $limit = $this->getOrCreateLimit($wallet);

        $limit->update([
            'tier_id' => $newTier->id,
            'tier_assigned_at' => now(),
            'eligible_for_upgrade' => false,
        ]);

        Log::info('Wallet tier upgraded', [
            'wallet_id' => $wallet->id,
            'new_tier' => $tierCode,
            'reason' => $reason,
        ]);

        return $limit->fresh();
    }

    /**
     * Set temporary limit override
     */
    public function setOverride(
        Wallet $wallet,
        ?float $maxBalance = null,
        ?float $dailyLimit = null,
        ?float $monthlyLimit = null,
        ?\DateTimeInterface $expiresAt = null,
        ?string $reason = null,
        ?int $overrideBy = null
    ): WalletLimit {
        $limit = $this->getOrCreateLimit($wallet);

        $limit->update([
            'override_max_balance' => $maxBalance,
            'override_daily_limit' => $dailyLimit,
            'override_monthly_limit' => $monthlyLimit,
            'override_expires_at' => $expiresAt ?? now()->addDays(30),
            'override_reason' => $reason,
            'override_by' => $overrideBy,
        ]);

        Log::info('Wallet limit override set', [
            'wallet_id' => $wallet->id,
            'max_balance' => $maxBalance,
            'daily_limit' => $dailyLimit,
            'monthly_limit' => $monthlyLimit,
            'expires_at' => $expiresAt,
            'reason' => $reason,
        ]);

        return $limit->fresh();
    }

    /**
     * Remove limit override
     */
    public function removeOverride(Wallet $wallet): WalletLimit
    {
        $limit = $this->getOrCreateLimit($wallet);

        $limit->update([
            'override_max_balance' => null,
            'override_daily_limit' => null,
            'override_monthly_limit' => null,
            'override_expires_at' => null,
            'override_reason' => null,
            'override_by' => null,
        ]);

        return $limit->fresh();
    }

    /**
     * Check eligibility for tier upgrade
     */
    public function checkUpgradeEligibility(Wallet $wallet): array
    {
        $limit = $this->getOrCreateLimit($wallet);
        $currentTier = $limit->tier;
        $accountType = $wallet->holder_type === 'merchant' ? 'merchant' : 'customer';

        // Get next tier
        $nextTier = WalletTier::where('account_type', $accountType)
            ->where('level', '>', $currentTier->level)
            ->where('is_active', true)
            ->orderBy('level')
            ->first();

        if (!$nextTier) {
            return [
                'eligible' => false,
                'current_tier' => $currentTier->code,
                'next_tier' => null,
                'reason' => 'Already at highest tier',
                'requirements' => [],
            ];
        }

        $requirements = [];
        $eligible = true;

        // Check KYC requirements
        if ($nextTier->kyc_requirements) {
            // This would check actual KYC status
            $requirements['kyc'] = [
                'required' => $nextTier->kyc_requirements,
                'met' => false, // Placeholder - implement actual KYC check
            ];
            $eligible = false;
        }

        // Check account age
        if ($nextTier->min_account_age_days > 0) {
            $accountAge = $wallet->created_at->diffInDays(now());
            $met = $accountAge >= $nextTier->min_account_age_days;
            $requirements['account_age'] = [
                'required' => $nextTier->min_account_age_days,
                'current' => $accountAge,
                'met' => $met,
            ];
            if (!$met) $eligible = false;
        }

        // Check monthly volume
        if ($nextTier->min_monthly_volume > 0) {
            $monthlyVolume = $limit->monthly_transaction_used;
            $met = $monthlyVolume >= $nextTier->min_monthly_volume;
            $requirements['monthly_volume'] = [
                'required' => $nextTier->min_monthly_volume,
                'current' => $monthlyVolume,
                'met' => $met,
            ];
            if (!$met) $eligible = false;
        }

        // Update eligibility flag
        if ($limit->eligible_for_upgrade !== $eligible) {
            $limit->update(['eligible_for_upgrade' => $eligible]);
        }

        return [
            'eligible' => $eligible,
            'current_tier' => $currentTier->code,
            'next_tier' => $nextTier->code,
            'requirements' => $requirements,
        ];
    }

    /**
     * Get usage summary for a wallet
     */
    public function getUsageSummary(Wallet $wallet): array
    {
        $limit = $this->getOrCreateLimit($wallet);
        return $limit->getUsageSummary();
    }

    /**
     * Reset daily limits for all wallets (scheduled task)
     */
    public function resetDailyLimits(): int
    {
        return WalletLimit::where('daily_reset_date', '<', today())
            ->update([
                'daily_transaction_used' => 0,
                'daily_deposit_used' => 0,
                'daily_withdrawal_used' => 0,
                'daily_transfer_used' => 0,
                'daily_reset_date' => today(),
            ]);
    }

    /**
     * Reset weekly limits for all wallets (scheduled task)
     */
    public function resetWeeklyLimits(): int
    {
        $startOfWeek = today()->startOfWeek();

        return WalletLimit::where('weekly_reset_date', '<', $startOfWeek)
            ->update([
                'weekly_transaction_used' => 0,
                'weekly_reset_date' => $startOfWeek,
            ]);
    }

    /**
     * Reset monthly limits for all wallets (scheduled task)
     */
    public function resetMonthlyLimits(): int
    {
        $startOfMonth = today()->startOfMonth();

        return WalletLimit::where('monthly_reset_date', '<', $startOfMonth)
            ->update([
                'monthly_transaction_used' => 0,
                'monthly_deposit_used' => 0,
                'monthly_withdrawal_used' => 0,
                'monthly_transfer_used' => 0,
                'monthly_reset_date' => $startOfMonth,
            ]);
    }

    /**
     * Batch check for eligible upgrades
     */
    public function checkAllUpgradeEligibility(): array
    {
        $results = [
            'checked' => 0,
            'eligible' => 0,
            'upgraded' => 0,
        ];

        WalletLimit::with(['wallet', 'tier'])
            ->where('eligible_for_upgrade', false)
            ->chunk(100, function ($limits) use (&$results) {
                foreach ($limits as $limit) {
                    $results['checked']++;
                    $eligibility = $this->checkUpgradeEligibility($limit->wallet);

                    if ($eligibility['eligible']) {
                        $results['eligible']++;
                    }
                }
            });

        return $results;
    }
}
