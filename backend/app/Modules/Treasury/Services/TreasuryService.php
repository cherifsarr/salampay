<?php

namespace App\Modules\Treasury\Services;

use App\Modules\Provider\ProviderFactory;
use App\Modules\Treasury\Models\CustodianAccount;
use App\Modules\Treasury\Models\ProviderAccount;
use App\Modules\Treasury\Models\TreasuryLedger;
use App\Modules\Treasury\Models\TreasuryTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TreasuryService
{
    public function __construct(
        private ProviderFactory $providerFactory
    ) {}

    /**
     * Sweep excess funds from provider accounts to bank
     * Called by scheduler when provider balance > maximum
     */
    public function sweepExcessFunds(): array
    {
        $results = [];
        $sweepTarget = CustodianAccount::sweepTargets()->first();

        if (!$sweepTarget) {
            Log::warning('Treasury: No sweep target account configured');
            return ['error' => 'No sweep target configured'];
        }

        // Find provider accounts that need sweeping
        $accounts = ProviderAccount::needsSweep()->get();

        foreach ($accounts as $account) {
            try {
                $excessAmount = $account->excessAmount();

                if ($excessAmount <= 0) {
                    continue;
                }

                $result = $this->createSweepTransfer($account, $sweepTarget, $excessAmount);
                $results[] = $result;

                Log::info("Treasury: Initiated sweep from {$account->provider}", [
                    'amount' => $excessAmount,
                    'transfer_id' => $result['transfer_id'] ?? null,
                ]);

            } catch (\Exception $e) {
                Log::error("Treasury: Sweep failed for {$account->provider}", [
                    'error' => $e->getMessage(),
                ]);
                $results[] = [
                    'provider' => $account->provider,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Fund provider accounts that are below minimum balance
     */
    public function fundLowAccounts(): array
    {
        $results = [];
        $fundingSource = CustodianAccount::fundingSources()->first();

        if (!$fundingSource) {
            Log::warning('Treasury: No funding source account configured');
            return ['error' => 'No funding source configured'];
        }

        // Find provider accounts that need funding
        $accounts = ProviderAccount::needsFunding()->get();

        foreach ($accounts as $account) {
            try {
                $deficitAmount = $account->deficitAmount();

                if ($deficitAmount <= 0) {
                    continue;
                }

                // Check if funding source has enough funds
                $availableFunds = $fundingSource->available_balance - $fundingSource->minimum_balance;
                if ($availableFunds < $deficitAmount) {
                    Log::warning("Treasury: Insufficient funds to fund {$account->provider}", [
                        'needed' => $deficitAmount,
                        'available' => $availableFunds,
                    ]);
                    continue;
                }

                $result = $this->createFundingTransfer($fundingSource, $account, $deficitAmount);
                $results[] = $result;

                Log::info("Treasury: Initiated funding to {$account->provider}", [
                    'amount' => $deficitAmount,
                    'transfer_id' => $result['transfer_id'] ?? null,
                ]);

            } catch (\Exception $e) {
                Log::error("Treasury: Funding failed for {$account->provider}", [
                    'error' => $e->getMessage(),
                ]);
                $results[] = [
                    'provider' => $account->provider,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Create a sweep transfer (Provider → Bank)
     */
    public function createSweepTransfer(
        ProviderAccount $source,
        CustodianAccount $destination,
        float $amount,
        string $initiatedBy = 'system',
        ?int $userId = null
    ): array {
        return DB::transaction(function () use ($source, $destination, $amount, $initiatedBy, $userId) {
            // Lock accounts
            $source = ProviderAccount::lockForUpdate()->find($source->id);
            $destination = CustodianAccount::lockForUpdate()->find($destination->id);

            // Calculate fee (bank transfer fee)
            $fee = $this->calculateTransferFee('sweep', $amount);
            $netAmount = $amount - $fee;

            // Validate
            if ($source->available_balance < $amount) {
                throw new \Exception('Insufficient balance for sweep');
            }

            // Create transfer record
            $transfer = TreasuryTransfer::create([
                'type' => 'sweep',
                'direction' => 'outbound',
                'source_type' => 'provider',
                'source_id' => $source->id,
                'source_balance_before' => $source->balance,
                'source_balance_after' => $source->balance - $amount,
                'destination_type' => 'custodian',
                'destination_id' => $destination->id,
                'destination_balance_before' => $destination->balance,
                'destination_balance_after' => $destination->balance + $netAmount,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'currency' => 'XOF',
                'status' => 'pending',
                'initiated_by' => $initiatedBy,
                'initiated_by_user_id' => $userId,
                'description' => "Sweep from {$source->getProviderName()} to {$destination->name}",
            ]);

            // Initiate withdrawal from provider
            try {
                $provider = $this->providerFactory->make($source->provider);
                $payoutResult = $provider->payout([
                    'amount' => $amount,
                    'currency' => 'XOF',
                    'client_reference' => $transfer->reference,
                    'recipient_phone' => config("services.treasury.bank_phone.{$destination->id}"),
                ]);

                $transfer->update([
                    'external_reference' => $payoutResult['id'] ?? $payoutResult['payout_id'] ?? null,
                    'status' => 'processing',
                ]);

            } catch (\Exception $e) {
                $transfer->markAsFailed($e->getMessage());
                throw $e;
            }

            // Update source balance (pessimistic - deduct immediately)
            $source->update([
                'balance' => $source->balance - $amount,
                'available_balance' => $source->available_balance - $amount,
                'last_sweep_at' => now(),
            ]);

            // Record ledger entries (double-entry)
            TreasuryLedger::recordTransfer(
                'provider',
                $source->id,
                $source->balance - $amount,
                'custodian',
                $destination->id,
                $destination->balance + $netAmount,
                $amount,
                'sweep',
                'transfer',
                $transfer->id,
                "Sweep: {$source->getProviderName()} → {$destination->name}"
            );

            // Record fee if any
            if ($fee > 0) {
                TreasuryLedger::recordDebit(
                    'provider',
                    $source->id,
                    $fee,
                    $source->balance - $amount,
                    'fee_paid',
                    'transfer',
                    $transfer->id,
                    "Transfer fee for sweep"
                );
            }

            return [
                'provider' => $source->provider,
                'transfer_id' => $transfer->uuid,
                'reference' => $transfer->reference,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'status' => $transfer->status,
            ];
        });
    }

    /**
     * Create a funding transfer (Bank → Provider)
     */
    public function createFundingTransfer(
        CustodianAccount $source,
        ProviderAccount $destination,
        float $amount,
        string $initiatedBy = 'system',
        ?int $userId = null
    ): array {
        return DB::transaction(function () use ($source, $destination, $amount, $initiatedBy, $userId) {
            // Lock accounts
            $source = CustodianAccount::lockForUpdate()->find($source->id);
            $destination = ProviderAccount::lockForUpdate()->find($destination->id);

            // Calculate fee
            $fee = $this->calculateTransferFee('fund', $amount);
            $netAmount = $amount - $fee;

            // Validate
            if ($source->available_balance - $source->minimum_balance < $amount) {
                throw new \Exception('Insufficient funds in bank account');
            }

            if (!$destination->canAcceptDeposit($netAmount)) {
                throw new \Exception('Amount would exceed provider maximum balance');
            }

            // Create transfer record
            $transfer = TreasuryTransfer::create([
                'type' => 'fund',
                'direction' => 'inbound',
                'source_type' => 'custodian',
                'source_id' => $source->id,
                'source_balance_before' => $source->balance,
                'source_balance_after' => $source->balance - $amount,
                'destination_type' => 'provider',
                'destination_id' => $destination->id,
                'destination_balance_before' => $destination->balance,
                'destination_balance_after' => $destination->balance + $netAmount,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'currency' => 'XOF',
                'status' => 'pending',
                'initiated_by' => $initiatedBy,
                'initiated_by_user_id' => $userId,
                'description' => "Fund {$destination->getProviderName()} from {$source->name}",
            ]);

            // For funding, we need to initiate bank transfer
            // This would integrate with bank API or be manual approval
            // For now, mark as processing (requires manual completion)
            $transfer->update(['status' => 'processing']);

            // Update source balance (pessimistic)
            $source->update([
                'balance' => $source->balance - $amount,
                'available_balance' => $source->available_balance - $amount,
            ]);

            // Update destination pending balance
            $destination->update([
                'pending_balance' => $destination->pending_balance + $netAmount,
                'last_fund_at' => now(),
            ]);

            // Record ledger entries
            TreasuryLedger::recordTransfer(
                'custodian',
                $source->id,
                $source->balance - $amount,
                'provider',
                $destination->id,
                $destination->balance, // Not credited yet
                $amount,
                'funding',
                'transfer',
                $transfer->id,
                "Funding: {$source->name} → {$destination->getProviderName()}"
            );

            return [
                'provider' => $destination->provider,
                'transfer_id' => $transfer->uuid,
                'reference' => $transfer->reference,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'status' => $transfer->status,
            ];
        });
    }

    /**
     * Complete a funding transfer (when funds arrive at provider)
     */
    public function completeFundingTransfer(TreasuryTransfer $transfer): void
    {
        if ($transfer->type !== 'fund' || $transfer->status !== 'processing') {
            throw new \Exception('Invalid transfer for completion');
        }

        DB::transaction(function () use ($transfer) {
            $destination = ProviderAccount::lockForUpdate()->find($transfer->destination_id);

            // Credit the provider account
            $destination->update([
                'balance' => $destination->balance + $transfer->net_amount,
                'available_balance' => $destination->available_balance + $transfer->net_amount,
                'pending_balance' => max(0, $destination->pending_balance - $transfer->net_amount),
                'balance_updated_at' => now(),
            ]);

            $transfer->markAsCompleted();

            Log::info("Treasury: Funding transfer completed", [
                'transfer_id' => $transfer->uuid,
                'provider' => $destination->provider,
                'amount' => $transfer->net_amount,
            ]);
        });
    }

    /**
     * Complete a sweep transfer (when funds arrive at bank)
     */
    public function completeSweepTransfer(TreasuryTransfer $transfer): void
    {
        if ($transfer->type !== 'sweep' || $transfer->status !== 'processing') {
            throw new \Exception('Invalid transfer for completion');
        }

        DB::transaction(function () use ($transfer) {
            $destination = CustodianAccount::lockForUpdate()->find($transfer->destination_id);

            // Credit the bank account
            $destination->update([
                'balance' => $destination->balance + $transfer->net_amount,
                'available_balance' => $destination->available_balance + $transfer->net_amount,
                'balance_updated_at' => now(),
            ]);

            $transfer->markAsCompleted();

            Log::info("Treasury: Sweep transfer completed", [
                'transfer_id' => $transfer->uuid,
                'bank' => $destination->name,
                'amount' => $transfer->net_amount,
            ]);
        });
    }

    /**
     * Sync provider account balance from external API
     */
    public function syncProviderBalance(ProviderAccount $account): float
    {
        try {
            $provider = $this->providerFactory->make($account->provider);
            $balance = $provider->getBalance();

            $account->update([
                'balance' => $balance['amount'] ?? $balance['balance'] ?? $account->balance,
                'available_balance' => $balance['available'] ?? $balance['amount'] ?? $account->balance,
                'balance_updated_at' => now(),
            ]);

            return $account->balance;

        } catch (\Exception $e) {
            Log::error("Treasury: Failed to sync balance for {$account->provider}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Sync all provider account balances
     */
    public function syncAllProviderBalances(): array
    {
        $results = [];
        $accounts = ProviderAccount::active()->get();

        foreach ($accounts as $account) {
            try {
                $balance = $this->syncProviderBalance($account);
                $results[$account->provider] = [
                    'status' => 'success',
                    'balance' => $balance,
                ];
            } catch (\Exception $e) {
                $results[$account->provider] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get treasury overview
     */
    public function getOverview(): array
    {
        $custodianAccounts = CustodianAccount::active()->get();
        $providerAccounts = ProviderAccount::active()->get();

        $totalCustodian = $custodianAccounts->sum('balance');
        $totalProvider = $providerAccounts->sum('balance');
        $totalPending = $providerAccounts->sum('pending_balance');

        return [
            'custodian_accounts' => $custodianAccounts->map(fn($a) => [
                'id' => $a->uuid,
                'name' => $a->name,
                'bank' => $a->bank_name,
                'type' => $a->account_type,
                'balance' => (float) $a->balance,
                'minimum' => (float) $a->minimum_balance,
                'status' => $this->getAccountHealthStatus($a),
            ]),
            'provider_accounts' => $providerAccounts->map(fn($a) => [
                'id' => $a->uuid,
                'provider' => $a->provider,
                'name' => $a->name,
                'balance' => (float) $a->balance,
                'pending' => (float) $a->pending_balance,
                'minimum' => (float) $a->minimum_balance,
                'maximum' => (float) $a->maximum_balance,
                'utilization' => $a->maximum_balance > 0
                    ? round(($a->balance / $a->maximum_balance) * 100, 1)
                    : 0,
                'status' => $this->getAccountHealthStatus($a),
            ]),
            'totals' => [
                'custodian_balance' => $totalCustodian,
                'provider_balance' => $totalProvider,
                'pending_balance' => $totalPending,
                'total_assets' => $totalCustodian + $totalProvider,
            ],
            'alerts' => $this->getActiveAlerts(),
        ];
    }

    /**
     * Calculate transfer fee based on type
     */
    private function calculateTransferFee(string $type, float $amount): float
    {
        // Fee structure can be configured
        return match ($type) {
            'sweep' => min($amount * 0.005, 5000), // 0.5% max 5000 XOF
            'fund' => min($amount * 0.01, 10000),   // 1% max 10000 XOF
            default => 0,
        };
    }

    /**
     * Get account health status
     */
    private function getAccountHealthStatus($account): string
    {
        if ($account instanceof ProviderAccount) {
            if ($account->isOverMaximum()) return 'critical_high';
            if ($account->isBelowMinimum()) return 'critical_low';
            if ($account->isNearMaximum()) return 'warning_high';
            if ($account->isNearMinimum()) return 'warning_low';
        } else {
            if ($account->isBelowMinimum()) return 'critical_low';
            if ($account->isOverMaximum()) return 'warning_high';
        }
        return 'healthy';
    }

    /**
     * Get active alerts
     */
    private function getActiveAlerts(): array
    {
        $alerts = [];

        // Check provider accounts
        $overMax = ProviderAccount::active()
            ->whereRaw('balance > maximum_balance')
            ->get();

        foreach ($overMax as $account) {
            $alerts[] = [
                'type' => 'sweep_required',
                'severity' => 'high',
                'account' => $account->provider,
                'message' => "{$account->getProviderName()} balance exceeds maximum",
                'amount' => $account->excessAmount(),
            ];
        }

        // Check for low balances
        $belowMin = ProviderAccount::active()
            ->whereRaw('balance < minimum_balance')
            ->get();

        foreach ($belowMin as $account) {
            $alerts[] = [
                'type' => 'funding_required',
                'severity' => 'high',
                'account' => $account->provider,
                'message' => "{$account->getProviderName()} balance below minimum",
                'amount' => $account->deficitAmount(),
            ];
        }

        return $alerts;
    }
}
