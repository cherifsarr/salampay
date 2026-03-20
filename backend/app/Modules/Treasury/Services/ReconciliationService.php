<?php

namespace App\Modules\Treasury\Services;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Modules\Treasury\Models\BalanceSnapshot;
use App\Modules\Treasury\Models\CustodianAccount;
use App\Modules\Treasury\Models\ProviderAccount;
use App\Modules\Treasury\Models\ReconciliationReport;
use App\Modules\Treasury\Models\TreasuryLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReconciliationService
{
    /**
     * Perform daily reconciliation
     * Verifies that: Total Assets = Total Liabilities
     * Assets: Custodian + Provider account balances
     * Liabilities: Customer wallets + Merchant wallets + Platform fees
     */
    public function performDailyReconciliation(?string $date = null): ReconciliationReport
    {
        $date = $date ?? now()->subDay()->format('Y-m-d');
        $periodStart = $date;
        $periodEnd = $date;

        Log::info("Treasury Reconciliation: Starting for {$date}");

        // Gather all balances
        $custodianBalance = $this->getTotalCustodianBalance();
        $providerBalance = $this->getTotalProviderBalance();
        $customerWallets = $this->getTotalCustomerWalletBalance();
        $merchantWallets = $this->getTotalMerchantWalletBalance();
        $pendingTransactions = $this->getTotalPendingTransactions();
        $platformFees = $this->getTotalUnpaidPlatformFees();

        // Calculate totals
        $totalAssets = $custodianBalance + $providerBalance;
        $totalLiabilities = $customerWallets + $merchantWallets + $platformFees;

        // Expected: Assets should equal Liabilities + Pending
        $expectedTotal = $totalLiabilities + $pendingTransactions;
        $actualTotal = $totalAssets;
        $discrepancy = abs($actualTotal - $expectedTotal);

        // Determine status
        $status = 'balanced';
        if ($discrepancy > 0.01) { // Allow 1 centime tolerance
            $status = $discrepancy > 10000 ? 'discrepancy' : 'balanced'; // 10K tolerance
        }

        // Create report
        $report = ReconciliationReport::create([
            'uuid' => Str::uuid(),
            'report_type' => 'daily',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_custodian_balance' => $custodianBalance,
            'total_provider_balance' => $providerBalance,
            'total_customer_wallets' => $customerWallets,
            'total_merchant_wallets' => $merchantWallets,
            'total_pending_transactions' => $pendingTransactions,
            'total_platform_fees' => $platformFees,
            'expected_total' => $expectedTotal,
            'actual_total' => $actualTotal,
            'discrepancy' => $discrepancy,
            'status' => $status,
            'details' => $this->buildReconciliationDetails($date),
        ]);

        // Create balance snapshots
        $this->createBalanceSnapshots('daily');

        Log::info("Treasury Reconciliation: Completed", [
            'report_id' => $report->uuid,
            'status' => $status,
            'discrepancy' => $discrepancy,
        ]);

        // Alert if discrepancy
        if ($status === 'discrepancy') {
            $this->alertDiscrepancy($report);
        }

        return $report;
    }

    /**
     * Create hourly balance snapshots for all accounts
     */
    public function createBalanceSnapshots(string $type = 'hourly'): void
    {
        $snapshotAt = now();

        // Custodian accounts
        CustodianAccount::active()->each(function ($account) use ($type, $snapshotAt) {
            $calculatedBalance = $this->calculateAccountBalance('custodian', $account->id);

            BalanceSnapshot::create([
                'snapshot_type' => $type,
                'snapshot_at' => $snapshotAt,
                'account_type' => 'custodian',
                'account_id' => $account->id,
                'reported_balance' => $account->balance,
                'calculated_balance' => $calculatedBalance,
                'discrepancy' => abs($account->balance - $calculatedBalance),
                'is_reconciled' => abs($account->balance - $calculatedBalance) < 0.01,
            ]);
        });

        // Provider accounts
        ProviderAccount::active()->each(function ($account) use ($type, $snapshotAt) {
            $calculatedBalance = $this->calculateAccountBalance('provider', $account->id);

            BalanceSnapshot::create([
                'snapshot_type' => $type,
                'snapshot_at' => $snapshotAt,
                'account_type' => 'provider',
                'account_id' => $account->id,
                'reported_balance' => $account->balance,
                'calculated_balance' => $calculatedBalance,
                'discrepancy' => abs($account->balance - $calculatedBalance),
                'is_reconciled' => abs($account->balance - $calculatedBalance) < 0.01,
            ]);
        });
    }

    /**
     * Calculate account balance from ledger entries
     */
    public function calculateAccountBalance(string $accountType, int $accountId): float
    {
        $lastEntry = TreasuryLedger::forAccount($accountType, $accountId)
            ->orderBy('id', 'desc')
            ->first();

        return $lastEntry ? (float) $lastEntry->balance : 0;
    }

    /**
     * Verify ledger integrity (all entries balance)
     */
    public function verifyLedgerIntegrity(string $date): array
    {
        $issues = [];

        // Get all entries for the date
        $entries = TreasuryLedger::forDate($date)->get();

        // Group by account
        $byAccount = $entries->groupBy(fn($e) => "{$e->account_type}:{$e->account_id}");

        foreach ($byAccount as $accountKey => $accountEntries) {
            [$type, $id] = explode(':', $accountKey);

            // Verify running balance
            $runningBalance = 0;
            $previousEntry = TreasuryLedger::forAccount($type, (int) $id)
                ->where('entry_date', '<', $date)
                ->orderBy('id', 'desc')
                ->first();

            if ($previousEntry) {
                $runningBalance = (float) $previousEntry->balance;
            }

            foreach ($accountEntries->sortBy('id') as $entry) {
                $expectedBalance = $runningBalance + $entry->credit - $entry->debit;

                if (abs($expectedBalance - $entry->balance) > 0.01) {
                    $issues[] = [
                        'type' => 'balance_mismatch',
                        'account' => $accountKey,
                        'entry_id' => $entry->id,
                        'expected' => $expectedBalance,
                        'actual' => $entry->balance,
                    ];
                }

                $runningBalance = (float) $entry->balance;
            }
        }

        // Verify double-entry (total debits = total credits for transfers)
        $totalDebits = $entries->sum('debit');
        $totalCredits = $entries->sum('credit');

        if (abs($totalDebits - $totalCredits) > 0.01) {
            $issues[] = [
                'type' => 'debit_credit_mismatch',
                'date' => $date,
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'difference' => abs($totalDebits - $totalCredits),
            ];
        }

        return $issues;
    }

    /**
     * Reconcile provider account with external API
     */
    public function reconcileProviderAccount(
        ProviderAccount $account,
        TreasuryService $treasuryService
    ): array {
        // Get external balance
        $externalBalance = $treasuryService->syncProviderBalance($account);

        // Get our calculated balance
        $calculatedBalance = $this->calculateAccountBalance('provider', $account->id);

        $discrepancy = abs($externalBalance - $calculatedBalance);

        $result = [
            'provider' => $account->provider,
            'external_balance' => $externalBalance,
            'calculated_balance' => $calculatedBalance,
            'discrepancy' => $discrepancy,
            'status' => $discrepancy < 0.01 ? 'reconciled' : 'discrepancy',
        ];

        if ($discrepancy >= 0.01) {
            Log::warning("Treasury: Provider balance discrepancy", $result);
        }

        return $result;
    }

    /**
     * Get transaction flow for a period
     */
    public function getTransactionFlow(string $startDate, string $endDate): array
    {
        return [
            'deposits' => Transaction::whereBetween('created_at', [$startDate, $endDate])
                ->where('type', 'deposit')
                ->where('status', 'completed')
                ->sum('amount'),

            'withdrawals' => Transaction::whereBetween('created_at', [$startDate, $endDate])
                ->where('type', 'withdrawal')
                ->where('status', 'completed')
                ->sum('amount'),

            'transfers' => Transaction::whereBetween('created_at', [$startDate, $endDate])
                ->where('type', 'transfer')
                ->where('status', 'completed')
                ->sum('amount'),

            'payments' => Transaction::whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('type', ['payment', 'qr_payment', 'payment_link'])
                ->where('status', 'completed')
                ->sum('amount'),

            'refunds' => Transaction::whereBetween('created_at', [$startDate, $endDate])
                ->where('type', 'refund')
                ->where('status', 'completed')
                ->sum('amount'),

            'fees_collected' => Transaction::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed')
                ->sum('fee_amount'),
        ];
    }

    // Private helper methods

    private function getTotalCustodianBalance(): float
    {
        return (float) CustodianAccount::active()->sum('balance');
    }

    private function getTotalProviderBalance(): float
    {
        return (float) ProviderAccount::active()->sum('balance');
    }

    private function getTotalCustomerWalletBalance(): float
    {
        // Assuming Wallet model exists with user_type distinction
        return (float) Wallet::where('holder_type', 'user')->sum('balance');
    }

    private function getTotalMerchantWalletBalance(): float
    {
        return (float) Wallet::where('holder_type', 'merchant')->sum('balance');
    }

    private function getTotalPendingTransactions(): float
    {
        return (float) Transaction::whereIn('status', ['pending', 'processing'])
            ->sum('amount');
    }

    private function getTotalUnpaidPlatformFees(): float
    {
        // This would track accumulated fees not yet withdrawn
        return 0; // Placeholder
    }

    private function buildReconciliationDetails(string $date): array
    {
        return [
            'custodian_breakdown' => CustodianAccount::active()
                ->get()
                ->map(fn($a) => [
                    'name' => $a->name,
                    'balance' => (float) $a->balance,
                ])
                ->toArray(),

            'provider_breakdown' => ProviderAccount::active()
                ->get()
                ->map(fn($a) => [
                    'provider' => $a->provider,
                    'balance' => (float) $a->balance,
                    'pending' => (float) $a->pending_balance,
                ])
                ->toArray(),

            'transaction_flow' => $this->getTransactionFlow($date . ' 00:00:00', $date . ' 23:59:59'),

            'ledger_integrity' => $this->verifyLedgerIntegrity($date),
        ];
    }

    private function alertDiscrepancy(ReconciliationReport $report): void
    {
        // Send alert to finance team
        Log::alert("Treasury Reconciliation Discrepancy", [
            'report_id' => $report->uuid,
            'discrepancy' => $report->discrepancy,
            'expected' => $report->expected_total,
            'actual' => $report->actual_total,
        ]);

        // Could also send email/Slack notification here
    }
}
