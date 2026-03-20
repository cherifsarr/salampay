<?php

namespace Database\Seeders;

use App\Modules\Accounting\Models\ChartOfAccounts;
use App\Modules\Accounting\Models\FeeConfiguration;
use App\Modules\Accounting\Models\WalletTier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedChartOfAccounts();
        $this->seedFeeConfigurations();
        $this->seedWalletTiers();
    }

    protected function seedChartOfAccounts(): void
    {
        ChartOfAccounts::seedDefaults();
        $this->command->info('Chart of Accounts seeded.');
    }

    protected function seedFeeConfigurations(): void
    {
        $fees = [
            // Deposit fees
            [
                'name' => 'Standard Deposit Fee',
                'code' => 'deposit_standard',
                'transaction_type' => 'deposit',
                'fee_type' => 'percentage',
                'percentage_rate' => 0.01,  // 1%
                'minimum_fee' => 50,         // 50 XOF minimum
                'maximum_fee' => 5000,       // 5000 XOF maximum
                'payer' => 'sender',
                'applies_to' => 'all',
            ],

            // Withdrawal fees
            [
                'name' => 'Standard Withdrawal Fee',
                'code' => 'withdrawal_standard',
                'transaction_type' => 'withdrawal',
                'fee_type' => 'mixed',
                'percentage_rate' => 0.015,  // 1.5%
                'fixed_amount' => 100,       // + 100 XOF
                'minimum_fee' => 150,
                'maximum_fee' => 10000,
                'payer' => 'sender',
                'applies_to' => 'all',
            ],

            // Internal transfer fees (SalamPay to SalamPay)
            [
                'name' => 'Internal Transfer Fee',
                'code' => 'transfer_internal',
                'transaction_type' => 'transfer_internal',
                'fee_type' => 'percentage',
                'percentage_rate' => 0.005,  // 0.5%
                'minimum_fee' => 25,
                'maximum_fee' => 2500,
                'payer' => 'sender',
                'applies_to' => 'all',
            ],

            // External transfer fees
            [
                'name' => 'External Transfer Fee',
                'code' => 'transfer_external',
                'transaction_type' => 'transfer_external',
                'fee_type' => 'mixed',
                'percentage_rate' => 0.02,   // 2%
                'fixed_amount' => 100,
                'minimum_fee' => 200,
                'maximum_fee' => 15000,
                'payer' => 'sender',
                'applies_to' => 'all',
            ],

            // Merchant payment fees (customer pays)
            [
                'name' => 'Payment Processing Fee (Customer)',
                'code' => 'payment_customer',
                'transaction_type' => 'payment',
                'fee_type' => 'percentage',
                'percentage_rate' => 0.00,   // Free for customers
                'payer' => 'sender',
                'applies_to' => 'customer',
            ],

            // Merchant payment fees (merchant pays)
            [
                'name' => 'Payment Processing Fee (Merchant)',
                'code' => 'payment_merchant',
                'transaction_type' => 'payment',
                'fee_type' => 'tiered',
                'tiers' => [
                    ['min' => 0, 'max' => 50000, 'rate' => 0.025],       // 2.5% for 0-50K
                    ['min' => 50000, 'max' => 200000, 'rate' => 0.02],   // 2% for 50K-200K
                    ['min' => 200000, 'max' => 500000, 'rate' => 0.015], // 1.5% for 200K-500K
                    ['min' => 500000, 'max' => null, 'rate' => 0.01],    // 1% for 500K+
                ],
                'minimum_fee' => 50,
                'payer' => 'receiver',
                'applies_to' => 'merchant',
            ],

            // Merchant payout fees
            [
                'name' => 'Merchant Payout Fee',
                'code' => 'payout_merchant',
                'transaction_type' => 'payout',
                'fee_type' => 'mixed',
                'percentage_rate' => 0.005,  // 0.5%
                'fixed_amount' => 500,       // + 500 XOF
                'minimum_fee' => 1000,
                'maximum_fee' => 25000,
                'payer' => 'sender',
                'applies_to' => 'merchant',
            ],

            // Refund (no fee)
            [
                'name' => 'Refund Processing',
                'code' => 'refund',
                'transaction_type' => 'refund',
                'fee_type' => 'fixed',
                'fixed_amount' => 0,
                'payer' => 'platform',
                'applies_to' => 'all',
            ],
        ];

        foreach ($fees as $fee) {
            FeeConfiguration::updateOrCreate(
                ['code' => $fee['code']],
                array_merge($fee, [
                    'uuid' => Str::uuid(),
                    'is_active' => true,
                ])
            );
        }

        $this->command->info('Fee configurations seeded.');
    }

    protected function seedWalletTiers(): void
    {
        // Customer tiers
        foreach (WalletTier::CUSTOMER_TIERS as $code => $config) {
            WalletTier::updateOrCreate(
                ['code' => $code, 'account_type' => 'customer'],
                array_merge($config, [
                    'uuid' => Str::uuid(),
                    'code' => $code,
                    'account_type' => 'customer',
                    'is_default' => $code === 'basic',
                    'is_active' => true,
                ])
            );
        }

        // Merchant tiers
        foreach (WalletTier::MERCHANT_TIERS as $code => $config) {
            WalletTier::updateOrCreate(
                ['code' => $code, 'account_type' => 'merchant'],
                array_merge($config, [
                    'uuid' => Str::uuid(),
                    'code' => $code,
                    'account_type' => 'merchant',
                    'is_default' => $code === 'starter',
                    'is_active' => true,
                ])
            );
        }

        $this->command->info('Wallet tiers seeded.');
    }
}
