<?php

namespace App\Modules\Accounting\Services;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Modules\Accounting\Models\DailyBalanceSheet;
use App\Modules\Accounting\Models\FeeConfiguration;
use App\Modules\Accounting\Models\GeneralLedger;
use App\Modules\Accounting\Models\PlatformEarnings;
use App\Modules\Treasury\Models\CustodianAccount;
use App\Modules\Treasury\Models\ProviderAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AccountingService
{
    /**
     * Chart of Account codes
     */
    public const ACCOUNTS = [
        // Assets (1xxx)
        'BANK_MAIN' => '1100',
        'BANK_SWEEP' => '1110',
        'BANK_RESERVE' => '1120',
        'MOBILE_WAVE' => '1200',
        'MOBILE_ORANGE' => '1210',
        'MOBILE_FREE' => '1220',
        'MOBILE_WIZALL' => '1230',
        'MOBILE_EMONEY' => '1240',
        'FLOAT_IN_TRANSIT' => '1300',

        // Liabilities (2xxx)
        'CUSTOMER_WALLETS' => '2100',
        'MERCHANT_WALLETS' => '2200',
        'PENDING_SETTLEMENTS' => '2300',
        'PENDING_REFUNDS' => '2310',

        // Equity (3xxx)
        'PLATFORM_EARNINGS' => '3100',
        'RETAINED_EARNINGS' => '3200',
        'RESERVES' => '3300',

        // Revenue (4xxx)
        'FEE_DEPOSIT' => '4100',
        'FEE_WITHDRAWAL' => '4110',
        'FEE_TRANSFER' => '4120',
        'FEE_PAYMENT' => '4130',
        'FEE_PAYOUT' => '4140',
        'INTEREST_INCOME' => '4200',

        // Expenses (5xxx)
        'PROVIDER_FEES' => '5100',
        'BANK_CHARGES' => '5200',
        'REFUND_COSTS' => '5300',
    ];

    /**
     * Calculate fee for a transaction
     */
    public function calculateFee(
        string $transactionType,
        float $amount,
        ?string $provider = null,
        string $accountType = 'customer'
    ): array {
        $config = FeeConfiguration::getForTransaction($transactionType, $provider, $accountType);

        if (!$config) {
            return [
                'fee' => 0,
                'rate' => 0,
                'type' => 'none',
                'config_id' => null,
            ];
        }

        return $config->calculateFee($amount);
    }

    /**
     * Record platform earnings from a transaction
     */
    public function recordEarnings(
        Transaction $transaction,
        float $feeAmount,
        array $feeBreakdown
    ): PlatformEarnings {
        return PlatformEarnings::create([
            'uuid' => Str::uuid(),
            'reference' => 'EARN-' . $transaction->reference,
            'type' => 'transaction_fee',
            'amount' => $feeAmount,
            'currency' => $transaction->currency,
            'source_type' => 'transaction',
            'source_id' => $transaction->id,
            'source_reference' => $transaction->reference,
            'customer_id' => $transaction->user_id,
            'merchant_id' => $transaction->merchant_id,
            'provider' => $transaction->provider,
            'description' => "Fee from {$transaction->type} transaction",
            'breakdown' => $feeBreakdown,
        ]);
    }

    /**
     * Record a journal entry in the general ledger
     */
    public function recordJournalEntry(
        string $journalId,
        array $entries,
        string $referenceType,
        int $referenceId,
        ?string $description = null
    ): array {
        $today = today()->format('Y-m-d');
        $createdEntries = [];

        DB::transaction(function () use (
            $journalId, $entries, $referenceType, $referenceId,
            $description, $today, &$createdEntries
        ) {
            foreach ($entries as $entry) {
                // Get current balance for account
                $lastEntry = GeneralLedger::where('account_code', $entry['account_code'])
                    ->orderBy('id', 'desc')
                    ->first();

                $runningBalance = $lastEntry ? (float) $lastEntry->running_balance : 0;
                $newBalance = $runningBalance + ($entry['credit'] ?? 0) - ($entry['debit'] ?? 0);

                $ledgerEntry = GeneralLedger::create([
                    'uuid' => Str::uuid(),
                    'journal_id' => $journalId,
                    'entry_date' => $today,
                    'account_code' => $entry['account_code'],
                    'debit' => $entry['debit'] ?? 0,
                    'credit' => $entry['credit'] ?? 0,
                    'running_balance' => $newBalance,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'description' => $entry['description'] ?? $description,
                    'status' => 'posted',
                    'posted_at' => now(),
                ]);

                $createdEntries[] = $ledgerEntry;
            }
        });

        return $createdEntries;
    }

    /**
     * Record a complete transaction with all ledger entries
     * Ensures double-entry accounting (debits = credits)
     */
    public function recordTransaction(
        Transaction $transaction,
        float $feeAmount = 0,
        ?float $providerFee = 0
    ): array {
        $journalId = 'TXN-' . $transaction->reference;
        $entries = [];

        switch ($transaction->type) {
            case 'deposit':
                // Customer deposits money
                // Debit: Mobile Money (asset increases)
                // Credit: Customer Wallet (liability increases)
                // Credit: Platform Earnings (if fee)
                $entries = $this->buildDepositEntries($transaction, $feeAmount, $providerFee);
                break;

            case 'withdrawal':
                // Customer withdraws money
                // Debit: Customer Wallet (liability decreases)
                // Credit: Mobile Money (asset decreases)
                $entries = $this->buildWithdrawalEntries($transaction, $feeAmount, $providerFee);
                break;

            case 'transfer':
                // P2P transfer
                // Debit: Sender Wallet (liability decreases)
                // Credit: Receiver Wallet (liability increases)
                // Credit: Platform Earnings (if fee)
                $entries = $this->buildTransferEntries($transaction, $feeAmount);
                break;

            case 'payment':
            case 'qr_payment':
            case 'payment_link':
                // Customer pays merchant
                // Debit: Customer Wallet or Mobile Money (if guest)
                // Credit: Merchant Wallet
                // Credit: Platform Earnings (fee)
                $entries = $this->buildPaymentEntries($transaction, $feeAmount, $providerFee);
                break;

            case 'payout':
                // Merchant settlement
                // Debit: Merchant Wallet (liability decreases)
                // Credit: Mobile Money (asset decreases)
                $entries = $this->buildPayoutEntries($transaction, $feeAmount, $providerFee);
                break;

            case 'refund':
                // Refund to customer
                // Debit: Merchant Wallet or Platform
                // Credit: Customer Wallet
                $entries = $this->buildRefundEntries($transaction, $feeAmount);
                break;
        }

        // Validate double-entry (debits must equal credits)
        $totalDebits = array_sum(array_column($entries, 'debit'));
        $totalCredits = array_sum(array_column($entries, 'credit'));

        if (abs($totalDebits - $totalCredits) > 0.01) {
            throw new \Exception(
                "Accounting imbalance: Debits ({$totalDebits}) != Credits ({$totalCredits})"
            );
        }

        return $this->recordJournalEntry(
            $journalId,
            $entries,
            'transaction',
            $transaction->id,
            "Transaction: {$transaction->type}"
        );
    }

    /**
     * Build ledger entries for a deposit
     */
    protected function buildDepositEntries(
        Transaction $transaction,
        float $feeAmount,
        float $providerFee
    ): array {
        $amount = $transaction->amount;
        $netAmount = $amount - $feeAmount;
        $providerAccount = $this->getProviderAccountCode($transaction->provider);

        $entries = [
            // Asset increase: Mobile money received
            [
                'account_code' => $providerAccount,
                'debit' => $amount,
                'credit' => 0,
                'description' => 'Deposit received via ' . $transaction->provider,
            ],
            // Liability increase: Customer wallet credited
            [
                'account_code' => self::ACCOUNTS['CUSTOMER_WALLETS'],
                'debit' => 0,
                'credit' => $netAmount,
                'description' => 'Customer wallet credited',
            ],
        ];

        // Platform fee
        if ($feeAmount > 0) {
            $entries[] = [
                'account_code' => self::ACCOUNTS['FEE_DEPOSIT'],
                'debit' => 0,
                'credit' => $feeAmount - $providerFee,
                'description' => 'Deposit fee earned',
            ];
        }

        // Provider fee (expense)
        if ($providerFee > 0) {
            $entries[] = [
                'account_code' => self::ACCOUNTS['PROVIDER_FEES'],
                'debit' => $providerFee,
                'credit' => 0,
                'description' => 'Provider fee paid',
            ];
        }

        return $entries;
    }

    /**
     * Build ledger entries for a withdrawal
     */
    protected function buildWithdrawalEntries(
        Transaction $transaction,
        float $feeAmount,
        float $providerFee
    ): array {
        $amount = $transaction->amount;
        $providerAccount = $this->getProviderAccountCode($transaction->provider);

        $entries = [
            // Liability decrease: Customer wallet debited
            [
                'account_code' => self::ACCOUNTS['CUSTOMER_WALLETS'],
                'debit' => $amount,
                'credit' => 0,
                'description' => 'Customer wallet debited for withdrawal',
            ],
            // Asset decrease: Mobile money sent out
            [
                'account_code' => $providerAccount,
                'debit' => 0,
                'credit' => $amount - $feeAmount,
                'description' => 'Withdrawal sent via ' . $transaction->provider,
            ],
        ];

        // Platform fee
        if ($feeAmount > 0) {
            $entries[] = [
                'account_code' => self::ACCOUNTS['FEE_WITHDRAWAL'],
                'debit' => 0,
                'credit' => $feeAmount - $providerFee,
                'description' => 'Withdrawal fee earned',
            ];
        }

        // Provider fee
        if ($providerFee > 0) {
            $entries[] = [
                'account_code' => self::ACCOUNTS['PROVIDER_FEES'],
                'debit' => $providerFee,
                'credit' => 0,
                'description' => 'Provider fee paid',
            ];
        }

        return $entries;
    }

    /**
     * Build ledger entries for a P2P transfer
     */
    protected function buildTransferEntries(Transaction $transaction, float $feeAmount): array
    {
        $amount = $transaction->amount;
        $netAmount = $amount - $feeAmount;

        $entries = [
            // Sender wallet debited
            [
                'account_code' => self::ACCOUNTS['CUSTOMER_WALLETS'],
                'debit' => $amount,
                'credit' => 0,
                'description' => 'Transfer sent',
            ],
            // Receiver wallet credited
            [
                'account_code' => self::ACCOUNTS['CUSTOMER_WALLETS'],
                'debit' => 0,
                'credit' => $netAmount,
                'description' => 'Transfer received',
            ],
        ];

        // Platform fee
        if ($feeAmount > 0) {
            $entries[] = [
                'account_code' => self::ACCOUNTS['FEE_TRANSFER'],
                'debit' => 0,
                'credit' => $feeAmount,
                'description' => 'Transfer fee earned',
            ];
        }

        return $entries;
    }

    /**
     * Build ledger entries for a merchant payment
     */
    protected function buildPaymentEntries(
        Transaction $transaction,
        float $feeAmount,
        float $providerFee
    ): array {
        $amount = $transaction->amount;
        $merchantNet = $amount - $feeAmount;
        $isGuestPayment = !empty($transaction->metadata['is_guest'] ?? false);

        $entries = [];

        if ($isGuestPayment) {
            // Guest payment - money comes from provider
            $providerAccount = $this->getProviderAccountCode($transaction->provider);
            $entries[] = [
                'account_code' => $providerAccount,
                'debit' => $amount,
                'credit' => 0,
                'description' => 'Payment received from ' . $transaction->provider,
            ];
        } else {
            // SalamPay customer payment - from customer wallet
            $entries[] = [
                'account_code' => self::ACCOUNTS['CUSTOMER_WALLETS'],
                'debit' => $amount,
                'credit' => 0,
                'description' => 'Customer wallet debited for payment',
            ];
        }

        // Merchant wallet credited
        $entries[] = [
            'account_code' => self::ACCOUNTS['MERCHANT_WALLETS'],
            'debit' => 0,
            'credit' => $merchantNet,
            'description' => 'Merchant wallet credited',
        ];

        // Platform fee
        if ($feeAmount > 0) {
            $entries[] = [
                'account_code' => self::ACCOUNTS['FEE_PAYMENT'],
                'debit' => 0,
                'credit' => $feeAmount - $providerFee,
                'description' => 'Payment processing fee earned',
            ];
        }

        // Provider fee
        if ($providerFee > 0) {
            $entries[] = [
                'account_code' => self::ACCOUNTS['PROVIDER_FEES'],
                'debit' => $providerFee,
                'credit' => 0,
                'description' => 'Provider fee paid',
            ];
        }

        return $entries;
    }

    /**
     * Build ledger entries for a merchant payout
     */
    protected function buildPayoutEntries(
        Transaction $transaction,
        float $feeAmount,
        float $providerFee
    ): array {
        $amount = $transaction->amount;
        $netPayout = $amount - $feeAmount;
        $providerAccount = $this->getProviderAccountCode($transaction->provider);

        $entries = [
            // Merchant wallet debited
            [
                'account_code' => self::ACCOUNTS['MERCHANT_WALLETS'],
                'debit' => $amount,
                'credit' => 0,
                'description' => 'Merchant payout',
            ],
            // Mobile money sent
            [
                'account_code' => $providerAccount,
                'debit' => 0,
                'credit' => $netPayout,
                'description' => 'Payout sent via ' . $transaction->provider,
            ],
        ];

        // Platform fee
        if ($feeAmount > 0) {
            $entries[] = [
                'account_code' => self::ACCOUNTS['FEE_PAYOUT'],
                'debit' => 0,
                'credit' => $feeAmount - $providerFee,
                'description' => 'Payout fee earned',
            ];
        }

        // Provider fee
        if ($providerFee > 0) {
            $entries[] = [
                'account_code' => self::ACCOUNTS['PROVIDER_FEES'],
                'debit' => $providerFee,
                'credit' => 0,
                'description' => 'Provider fee paid',
            ];
        }

        return $entries;
    }

    /**
     * Build ledger entries for a refund
     */
    protected function buildRefundEntries(Transaction $transaction, float $feeAmount): array
    {
        $amount = $transaction->amount;

        return [
            // Merchant/Platform debited (who bears the refund)
            [
                'account_code' => self::ACCOUNTS['MERCHANT_WALLETS'],
                'debit' => $amount,
                'credit' => 0,
                'description' => 'Refund to customer',
            ],
            // Customer wallet credited
            [
                'account_code' => self::ACCOUNTS['CUSTOMER_WALLETS'],
                'debit' => 0,
                'credit' => $amount,
                'description' => 'Refund received',
            ],
        ];
    }

    /**
     * Get the account code for a provider
     */
    protected function getProviderAccountCode(string $provider): string
    {
        return match ($provider) {
            'wave' => self::ACCOUNTS['MOBILE_WAVE'],
            'orange_money' => self::ACCOUNTS['MOBILE_ORANGE'],
            'free_money' => self::ACCOUNTS['MOBILE_FREE'],
            'wizall' => self::ACCOUNTS['MOBILE_WIZALL'],
            'emoney' => self::ACCOUNTS['MOBILE_EMONEY'],
            default => self::ACCOUNTS['MOBILE_WAVE'],
        };
    }

    /**
     * Verify perfect balance of the platform
     * Assets = Liabilities + Equity
     */
    public function verifyPlatformBalance(): array
    {
        // Calculate total assets
        $totalBankAccounts = CustodianAccount::active()->sum('balance');
        $totalMobileMoney = ProviderAccount::active()->sum('balance');
        $totalAssets = $totalBankAccounts + $totalMobileMoney;

        // Calculate total liabilities
        $totalCustomerWallets = Wallet::where('holder_type', 'user')->sum('balance');
        $totalMerchantWallets = Wallet::where('holder_type', 'merchant')->sum('balance');
        $totalPendingPayouts = Transaction::whereIn('status', ['pending', 'processing'])
            ->where('type', 'payout')
            ->sum('amount');
        $totalLiabilities = $totalCustomerWallets + $totalMerchantWallets + $totalPendingPayouts;

        // Calculate total equity (platform earnings)
        $totalEarnings = PlatformEarnings::where('is_withdrawn', false)->sum('amount');

        // The equation: Assets = Liabilities + Equity
        $calculatedBalance = $totalAssets - $totalLiabilities - $totalEarnings;
        $isBalanced = abs($calculatedBalance) < 0.01;

        return [
            'is_balanced' => $isBalanced,
            'assets' => [
                'bank_accounts' => (float) $totalBankAccounts,
                'mobile_money' => (float) $totalMobileMoney,
                'total' => (float) $totalAssets,
            ],
            'liabilities' => [
                'customer_wallets' => (float) $totalCustomerWallets,
                'merchant_wallets' => (float) $totalMerchantWallets,
                'pending_payouts' => (float) $totalPendingPayouts,
                'total' => (float) $totalLiabilities,
            ],
            'equity' => [
                'platform_earnings' => (float) $totalEarnings,
                'total' => (float) $totalEarnings,
            ],
            'balance_check' => [
                'assets_minus_liabilities_minus_equity' => (float) $calculatedBalance,
                'should_be_zero' => $isBalanced,
            ],
        ];
    }

    /**
     * Generate daily balance sheet
     */
    public function generateDailyBalanceSheet(?string $date = null): DailyBalanceSheet
    {
        $date = $date ?? today()->format('Y-m-d');
        $balance = $this->verifyPlatformBalance();

        // Calculate day's activity
        $dayStart = $date . ' 00:00:00';
        $dayEnd = $date . ' 23:59:59';

        $transactionCount = Transaction::whereBetween('created_at', [$dayStart, $dayEnd])
            ->where('status', 'completed')
            ->count();

        $transactionVolume = Transaction::whereBetween('created_at', [$dayStart, $dayEnd])
            ->where('status', 'completed')
            ->sum('amount');

        $feesCollected = PlatformEarnings::whereBetween('created_at', [$dayStart, $dayEnd])
            ->sum('amount');

        return DailyBalanceSheet::updateOrCreate(
            ['sheet_date' => $date],
            [
                'total_bank_accounts' => $balance['assets']['bank_accounts'],
                'total_mobile_money' => $balance['assets']['mobile_money'],
                'total_float' => 0,
                'total_assets' => $balance['assets']['total'],
                'total_customer_wallets' => $balance['liabilities']['customer_wallets'],
                'total_merchant_wallets' => $balance['liabilities']['merchant_wallets'],
                'total_pending_payouts' => $balance['liabilities']['pending_payouts'],
                'total_liabilities' => $balance['liabilities']['total'],
                'total_platform_earnings' => $balance['equity']['platform_earnings'],
                'total_reserves' => 0,
                'total_equity' => $balance['equity']['total'],
                'calculated_balance' => $balance['balance_check']['assets_minus_liabilities_minus_equity'],
                'is_balanced' => $balance['is_balanced'],
                'transaction_count' => $transactionCount,
                'transaction_volume' => $transactionVolume,
                'fees_collected' => $feesCollected,
                'fees_paid' => 0,
                'breakdown' => $balance,
            ]
        );
    }
}
