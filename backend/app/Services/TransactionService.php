<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Accounting\Models\FeeConfiguration;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Accounting\Services\WalletLimitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransactionService
{
    public function __construct(
        private AccountingService $accountingService,
        private WalletLimitService $walletLimitService
    ) {}

    /**
     * Calculate fee for a transaction type
     */
    public function calculateFee(
        string $transactionType,
        float $amount,
        ?string $provider = null,
        string $accountType = 'customer'
    ): array {
        return $this->accountingService->calculateFee(
            $transactionType,
            $amount,
            $provider,
            $accountType
        );
    }

    /**
     * Validate deposit against wallet limits
     */
    public function validateDeposit(Wallet $wallet, float $amount): array
    {
        // Check if deposit would exceed wallet limits
        $check = $this->walletLimitService->canDeposit($wallet, $amount);

        if (!$check['allowed']) {
            return [
                'valid' => false,
                'errors' => $check['errors'],
                'message' => $this->formatLimitError($check['errors'][0] ?? []),
            ];
        }

        return ['valid' => true, 'errors' => []];
    }

    /**
     * Validate withdrawal against wallet limits
     */
    public function validateWithdrawal(Wallet $wallet, float $amount): array
    {
        // Check balance
        if ($wallet->getAvailableBalance() < $amount) {
            return [
                'valid' => false,
                'errors' => [['code' => 'insufficient_balance', 'message' => 'Insufficient balance']],
                'message' => 'Insufficient balance',
            ];
        }

        // Check limits
        $check = $this->walletLimitService->canTransact($wallet, $amount, 'withdrawal');

        if (!$check['allowed']) {
            return [
                'valid' => false,
                'errors' => $check['errors'],
                'message' => $this->formatLimitError($check['errors'][0] ?? []),
            ];
        }

        return ['valid' => true, 'errors' => []];
    }

    /**
     * Validate transfer against wallet limits
     */
    public function validateTransfer(Wallet $sourceWallet, Wallet $destWallet, float $amount): array
    {
        // Check source balance
        if ($sourceWallet->getAvailableBalance() < $amount) {
            return [
                'valid' => false,
                'errors' => [['code' => 'insufficient_balance', 'message' => 'Insufficient balance']],
                'message' => 'Insufficient balance',
            ];
        }

        // Check source limits
        $sourceCheck = $this->walletLimitService->canTransact($sourceWallet, $amount, 'transfer');
        if (!$sourceCheck['allowed']) {
            return [
                'valid' => false,
                'errors' => $sourceCheck['errors'],
                'message' => $this->formatLimitError($sourceCheck['errors'][0] ?? []),
            ];
        }

        // Check destination deposit limits
        $destCheck = $this->walletLimitService->canDeposit($destWallet, $amount);
        if (!$destCheck['allowed']) {
            return [
                'valid' => false,
                'errors' => $destCheck['errors'],
                'message' => 'Recipient wallet limit exceeded',
            ];
        }

        return ['valid' => true, 'errors' => []];
    }

    /**
     * Validate payment from customer to merchant
     */
    public function validatePayment(
        ?Wallet $customerWallet,
        Wallet $merchantWallet,
        float $amount,
        bool $isGuestPayment = false
    ): array {
        if (!$isGuestPayment) {
            // Check customer balance
            if (!$customerWallet || $customerWallet->getAvailableBalance() < $amount) {
                return [
                    'valid' => false,
                    'errors' => [['code' => 'insufficient_balance', 'message' => 'Insufficient balance']],
                    'message' => 'Insufficient balance',
                ];
            }

            // Check customer transaction limits
            $customerCheck = $this->walletLimitService->canTransact($customerWallet, $amount, 'transaction');
            if (!$customerCheck['allowed']) {
                return [
                    'valid' => false,
                    'errors' => $customerCheck['errors'],
                    'message' => $this->formatLimitError($customerCheck['errors'][0] ?? []),
                ];
            }
        }

        // Check merchant can receive (deposit limits)
        $merchantCheck = $this->walletLimitService->canDeposit($merchantWallet, $amount);
        if (!$merchantCheck['allowed']) {
            return [
                'valid' => false,
                'errors' => $merchantCheck['errors'],
                'message' => 'Merchant cannot receive payment at this time',
            ];
        }

        return ['valid' => true, 'errors' => []];
    }

    /**
     * Create and process a deposit transaction
     */
    public function createDeposit(
        User $user,
        Wallet $wallet,
        float $amount,
        string $provider,
        array $metadata = []
    ): Transaction {
        // Calculate fee
        $feeResult = $this->calculateFee('deposit', $amount, $provider, 'customer');
        $fee = $feeResult['fee'];

        $transaction = Transaction::create([
            'uuid' => Str::uuid(),
            'reference' => Transaction::generateReference(),
            'type' => 'deposit',
            'amount' => $amount,
            'currency' => 'XOF',
            'fee_amount' => $fee,
            'net_amount' => $amount - $fee,
            'destination_wallet_id' => $wallet->id,
            'destination_user_id' => $user->id,
            'provider' => $provider,
            'status' => 'pending',
            'metadata' => array_merge($metadata, ['fee_config' => $feeResult]),
        ]);

        return $transaction;
    }

    /**
     * Complete a deposit (called from webhook)
     */
    public function completeDeposit(Transaction $transaction): void
    {
        if ($transaction->status === 'completed') {
            return;
        }

        DB::transaction(function () use ($transaction) {
            $transaction = Transaction::lockForUpdate()->find($transaction->id);

            if ($transaction->status === 'completed') {
                return;
            }

            // Credit destination wallet
            if ($transaction->destination_wallet_id) {
                $wallet = $transaction->destinationWallet;
                $wallet->credit($transaction->net_amount, $transaction);

                // Record usage
                $this->walletLimitService->recordUsage($wallet, $transaction->net_amount, 'deposit');
            }

            // Mark transaction complete
            $transaction->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Record in accounting ledger
            $this->recordAccountingEntry($transaction);

            // Record platform earnings
            if ($transaction->fee_amount > 0) {
                $this->accountingService->recordEarnings(
                    $transaction,
                    $transaction->fee_amount,
                    $transaction->metadata['fee_config'] ?? []
                );
            }

            Log::info('Deposit completed', [
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'fee' => $transaction->fee_amount,
            ]);
        });
    }

    /**
     * Create and process a withdrawal transaction
     */
    public function createWithdrawal(
        User $user,
        Wallet $wallet,
        float $amount,
        string $provider,
        string $recipientPhone,
        array $metadata = []
    ): Transaction {
        // Calculate fee
        $feeResult = $this->calculateFee('withdrawal', $amount, $provider, 'customer');
        $fee = $feeResult['fee'];

        return DB::transaction(function () use ($user, $wallet, $amount, $fee, $feeResult, $provider, $recipientPhone, $metadata) {
            $transaction = Transaction::create([
                'uuid' => Str::uuid(),
                'reference' => Transaction::generateReference(),
                'type' => 'withdrawal',
                'amount' => $amount,
                'currency' => 'XOF',
                'fee_amount' => $fee,
                'net_amount' => $amount - $fee,
                'source_wallet_id' => $wallet->id,
                'source_user_id' => $user->id,
                'provider' => $provider,
                'status' => 'pending',
                'metadata' => array_merge($metadata, [
                    'recipient_phone' => $recipientPhone,
                    'fee_config' => $feeResult,
                ]),
            ]);

            // Immediately debit wallet (will be refunded if payout fails)
            $wallet->debit($amount, $transaction);

            // Record usage
            $this->walletLimitService->recordUsage($wallet, $amount, 'withdrawal');

            return $transaction;
        });
    }

    /**
     * Complete a withdrawal (called from webhook)
     */
    public function completeWithdrawal(Transaction $transaction): void
    {
        if ($transaction->status === 'completed') {
            return;
        }

        DB::transaction(function () use ($transaction) {
            $transaction = Transaction::lockForUpdate()->find($transaction->id);

            if ($transaction->status === 'completed') {
                return;
            }

            // Mark transaction complete
            $transaction->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Record in accounting ledger
            $this->recordAccountingEntry($transaction);

            // Record platform earnings
            if ($transaction->fee_amount > 0) {
                $this->accountingService->recordEarnings(
                    $transaction,
                    $transaction->fee_amount,
                    $transaction->metadata['fee_config'] ?? []
                );
            }

            Log::info('Withdrawal completed', [
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
            ]);
        });
    }

    /**
     * Refund a failed withdrawal
     */
    public function refundFailedWithdrawal(Transaction $transaction, string $reason): void
    {
        if (!in_array($transaction->status, ['pending', 'processing'])) {
            return;
        }

        DB::transaction(function () use ($transaction, $reason) {
            $transaction = Transaction::lockForUpdate()->find($transaction->id);

            if (!in_array($transaction->status, ['pending', 'processing'])) {
                return;
            }

            // Refund the source wallet
            if ($transaction->source_wallet_id) {
                $transaction->sourceWallet->credit($transaction->amount, $transaction);
            }

            $transaction->markAsFailed($reason);

            Log::info('Withdrawal refunded', [
                'transaction_id' => $transaction->id,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Create and process a P2P transfer
     */
    public function createTransfer(
        User $sender,
        User $recipient,
        Wallet $sourceWallet,
        Wallet $destWallet,
        float $amount,
        ?string $description = null,
        array $metadata = []
    ): Transaction {
        // Calculate fee (P2P transfers may have a fee)
        $feeResult = $this->calculateFee('transfer_internal', $amount, null, 'customer');
        $fee = $feeResult['fee'];

        return DB::transaction(function () use (
            $sender, $recipient, $sourceWallet, $destWallet,
            $amount, $fee, $feeResult, $description, $metadata
        ) {
            $transaction = Transaction::create([
                'uuid' => Str::uuid(),
                'reference' => Transaction::generateReference(),
                'type' => 'transfer_p2p',
                'amount' => $amount,
                'currency' => 'XOF',
                'fee_amount' => $fee,
                'net_amount' => $amount - $fee,
                'source_wallet_id' => $sourceWallet->id,
                'source_user_id' => $sender->id,
                'destination_wallet_id' => $destWallet->id,
                'destination_user_id' => $recipient->id,
                'provider' => 'internal',
                'status' => 'completed',
                'completed_at' => now(),
                'description' => $description,
                'metadata' => array_merge($metadata, ['fee_config' => $feeResult]),
            ]);

            // Execute transfer
            $sourceWallet->debit($amount, $transaction);
            $destWallet->credit($amount - $fee, $transaction);

            // Record usage
            $this->walletLimitService->recordUsage($sourceWallet, $amount, 'transfer');
            $this->walletLimitService->recordUsage($destWallet, $amount - $fee, 'deposit');

            // Record in accounting ledger
            $this->recordAccountingEntry($transaction);

            // Record platform earnings
            if ($fee > 0) {
                $this->accountingService->recordEarnings(
                    $transaction,
                    $fee,
                    $feeResult
                );
            }

            Log::info('Transfer completed', [
                'transaction_id' => $transaction->id,
                'sender' => $sender->id,
                'recipient' => $recipient->id,
                'amount' => $amount,
            ]);

            return $transaction;
        });
    }

    /**
     * Create a merchant payment transaction
     */
    public function createMerchantPayment(
        Merchant $merchant,
        Wallet $merchantWallet,
        float $amount,
        string $type = 'payment_link',
        array $metadata = []
    ): Transaction {
        // Calculate merchant fee (merchant pays)
        $feeResult = $this->calculateFee('payment', $amount, null, 'merchant');
        $fee = $feeResult['fee'];

        $transaction = Transaction::create([
            'uuid' => Str::uuid(),
            'reference' => Transaction::generateReference(),
            'type' => $type,
            'amount' => $amount,
            'currency' => 'XOF',
            'fee_amount' => $fee,
            'net_amount' => $amount - $fee,
            'destination_wallet_id' => $merchantWallet->id,
            'merchant_id' => $merchant->id,
            'status' => 'pending',
            'metadata' => array_merge($metadata, ['fee_config' => $feeResult]),
        ]);

        return $transaction;
    }

    /**
     * Complete a merchant payment
     */
    public function completeMerchantPayment(
        Transaction $transaction,
        ?Wallet $customerWallet = null,
        bool $isGuestPayment = false,
        ?string $provider = null
    ): void {
        if ($transaction->status === 'completed') {
            return;
        }

        DB::transaction(function () use ($transaction, $customerWallet, $isGuestPayment, $provider) {
            $transaction = Transaction::lockForUpdate()->find($transaction->id);

            if ($transaction->status === 'completed') {
                return;
            }

            // If customer is paying from wallet, debit them
            if ($customerWallet && !$isGuestPayment) {
                $customerWallet->debit($transaction->amount, $transaction);
                $this->walletLimitService->recordUsage($customerWallet, $transaction->amount, 'transaction');

                $transaction->update([
                    'source_wallet_id' => $customerWallet->id,
                    'source_user_id' => $customerWallet->owner_id,
                ]);
            }

            // Credit merchant wallet
            if ($transaction->destination_wallet_id) {
                $merchantWallet = $transaction->destinationWallet;
                $merchantWallet->credit($transaction->net_amount, $transaction);
                $this->walletLimitService->recordUsage($merchantWallet, $transaction->net_amount, 'deposit');
            }

            // Update transaction
            $transaction->update([
                'status' => 'completed',
                'completed_at' => now(),
                'provider' => $provider ?? $transaction->provider,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'is_guest' => $isGuestPayment,
                ]),
            ]);

            // Record in accounting ledger
            $this->recordAccountingEntry($transaction);

            // Record platform earnings
            if ($transaction->fee_amount > 0) {
                $this->accountingService->recordEarnings(
                    $transaction,
                    $transaction->fee_amount,
                    $transaction->metadata['fee_config'] ?? []
                );
            }

            Log::info('Merchant payment completed', [
                'transaction_id' => $transaction->id,
                'merchant_id' => $transaction->merchant_id,
                'amount' => $transaction->amount,
                'is_guest' => $isGuestPayment,
            ]);
        });
    }

    /**
     * Record transaction in accounting ledger
     */
    protected function recordAccountingEntry(Transaction $transaction): void
    {
        try {
            // Estimate provider fee (if applicable)
            $providerFee = $this->estimateProviderFee($transaction);

            $this->accountingService->recordTransaction(
                $transaction,
                $transaction->fee_amount,
                $providerFee
            );
        } catch (\Exception $e) {
            // Log but don't fail the transaction
            Log::error('Failed to record accounting entry', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Estimate provider fee for a transaction
     */
    protected function estimateProviderFee(Transaction $transaction): float
    {
        // Provider fees vary - these are estimates
        $amount = $transaction->amount;

        return match ($transaction->provider) {
            'wave' => round($amount * 0.005, 2), // ~0.5%
            'orange_money' => round($amount * 0.008, 2), // ~0.8%
            'free_money' => 25, // Fixed
            default => 0,
        };
    }

    /**
     * Format limit error for user display
     */
    protected function formatLimitError(array $error): string
    {
        return match ($error['code'] ?? '') {
            'single_limit_exceeded' => sprintf(
                'Amount exceeds single transaction limit of %s XOF',
                number_format($error['limit'] ?? 0)
            ),
            'daily_limit_exceeded' => sprintf(
                'Daily limit exceeded. Remaining: %s XOF',
                number_format($error['remaining'] ?? 0)
            ),
            'monthly_limit_exceeded' => sprintf(
                'Monthly limit exceeded. Remaining: %s XOF',
                number_format($error['remaining'] ?? 0)
            ),
            'max_balance_exceeded' => sprintf(
                'Deposit would exceed maximum wallet balance. Max deposit: %s XOF',
                number_format($error['max_deposit'] ?? 0)
            ),
            default => $error['message'] ?? 'Transaction limit exceeded',
        };
    }

    /**
     * Get wallet usage summary
     */
    public function getWalletUsageSummary(Wallet $wallet): array
    {
        return $this->walletLimitService->getUsageSummary($wallet);
    }

    /**
     * Get fee preview for transaction
     */
    public function getFeePreview(
        string $transactionType,
        float $amount,
        ?string $provider = null,
        string $accountType = 'customer'
    ): array {
        $feeResult = $this->calculateFee($transactionType, $amount, $provider, $accountType);

        return [
            'amount' => $amount,
            'fee' => $feeResult['fee'],
            'net_amount' => $amount - $feeResult['fee'],
            'fee_type' => $feeResult['type'],
            'fee_rate' => $feeResult['rate'] ?? null,
        ];
    }
}
