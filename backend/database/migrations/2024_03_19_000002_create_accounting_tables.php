<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comprehensive Accounting System
     *
     * Maintains perfect balance:
     * ASSETS = LIABILITIES + EQUITY
     *
     * Where:
     * - Assets: Bank + Mobile Money accounts
     * - Liabilities: Customer + Merchant wallets
     * - Equity: Platform fees + Revenue
     */
    public function up(): void
    {
        // Platform fee configuration
        Schema::create('fee_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');                          // "Standard Deposit Fee"
            $table->string('code')->unique();                // deposit_fee, withdrawal_fee, etc.
            $table->enum('transaction_type', [
                'deposit',
                'withdrawal',
                'transfer_internal',    // SalamPay to SalamPay
                'transfer_external',    // To external account
                'payment',              // Merchant payment
                'payout',               // Merchant payout
                'refund',
            ]);
            $table->enum('fee_type', [
                'percentage',           // X% of amount
                'fixed',                // Fixed amount
                'tiered',               // Based on amount tiers
                'mixed',                // Percentage + fixed
            ]);
            $table->decimal('percentage_rate', 5, 4)->default(0);  // e.g., 0.0150 = 1.5%
            $table->decimal('fixed_amount', 10, 2)->default(0);
            $table->decimal('minimum_fee', 10, 2)->default(0);     // Min fee to charge
            $table->decimal('maximum_fee', 10, 2)->nullable();      // Max fee cap
            $table->json('tiers')->nullable();                      // For tiered pricing
            $table->enum('payer', [
                'sender',               // Customer pays fee
                'receiver',             // Merchant pays fee
                'split',                // Split between both
                'platform',             // Absorbed by platform (promo)
            ])->default('sender');
            $table->string('applies_to')->default('all');          // 'all', 'customer', 'merchant'
            $table->string('provider')->nullable();                 // Specific provider or null
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['transaction_type', 'is_active']);
            $table->index(['code', 'is_active']);
        });

        // Platform earnings/revenue tracking
        Schema::create('platform_earnings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference')->unique();
            $table->enum('type', [
                'transaction_fee',      // Fee from transaction
                'subscription',         // Merchant subscription
                'premium_feature',      // Premium feature usage
                'interest',             // Interest on float
                'penalty',              // Late payment penalty
                'other',
            ]);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('XOF');

            // Source transaction
            $table->string('source_type')->nullable();       // 'transaction', 'subscription'
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_reference')->nullable();

            // Related accounts
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->string('provider')->nullable();

            $table->text('description')->nullable();
            $table->json('breakdown')->nullable();           // Fee calculation details
            $table->json('metadata')->nullable();

            $table->boolean('is_withdrawn')->default(false); // Has been withdrawn to bank
            $table->timestamp('withdrawn_at')->nullable();

            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index(['source_type', 'source_id']);
            $table->index('is_withdrawn');
        });

        // Master accounting ledger (Chart of Accounts)
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                // 1000, 2000, 3000, etc.
            $table->string('name');
            $table->enum('type', [
                'asset',                // Bank, Mobile Money, Receivables
                'liability',            // Customer Wallets, Merchant Wallets, Payables
                'equity',               // Platform Earnings, Reserves
                'revenue',              // Transaction Fees
                'expense',              // Provider Fees, Bank Charges
            ]);
            $table->enum('subtype', [
                // Assets
                'cash',                 // Bank accounts
                'mobile_money',         // Provider accounts
                'receivable',           // Money owed to us
                'float',                // In-transit funds
                // Liabilities
                'customer_wallet',      // Customer balances
                'merchant_wallet',      // Merchant balances
                'payable',              // Money we owe
                'pending',              // Pending settlements
                // Equity
                'retained_earnings',    // Accumulated profits
                'reserves',             // Set aside funds
                // Revenue
                'fee_income',           // Transaction fees
                'interest_income',      // Interest earned
                // Expense
                'provider_cost',        // Provider fees
                'bank_cost',            // Bank charges
                'operational',          // Other expenses
            ]);
            $table->string('parent_code')->nullable();
            $table->integer('level')->default(1);
            $table->decimal('normal_balance', 20, 2)->default(0);  // Expected balance
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        // General ledger entries (double-entry)
        Schema::create('general_ledger', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('journal_id');                    // Groups related entries
            $table->date('entry_date');
            $table->string('account_code');                  // From chart_of_accounts
            $table->decimal('debit', 20, 2)->default(0);
            $table->decimal('credit', 20, 2)->default(0);
            $table->decimal('running_balance', 20, 2);       // Account balance after entry

            // Reference to source
            $table->string('reference_type');                // transaction, transfer, fee, adjustment
            $table->unsignedBigInteger('reference_id');
            $table->string('external_reference')->nullable();

            $table->text('description')->nullable();
            $table->json('metadata')->nullable();

            $table->enum('status', ['pending', 'posted', 'reversed'])->default('posted');
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();

            $table->timestamps();

            $table->index(['journal_id']);
            $table->index(['account_code', 'entry_date']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['entry_date', 'status']);

            $table->foreign('account_code')->references('code')->on('chart_of_accounts');
        });

        // Wallet tier/cap configuration
        Schema::create('wallet_tiers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');                          // "Basic", "Standard", "Premium"
            $table->string('code')->unique();                // basic, standard, premium
            $table->enum('account_type', [
                'customer',
                'merchant',
            ]);
            $table->integer('level')->default(1);            // 1, 2, 3 (for ordering)

            // Balance limits
            $table->decimal('max_balance', 15, 2);           // Maximum wallet balance
            $table->decimal('min_balance', 15, 2)->default(0);

            // Transaction limits
            $table->decimal('daily_transaction_limit', 15, 2);
            $table->decimal('weekly_transaction_limit', 15, 2)->nullable();
            $table->decimal('monthly_transaction_limit', 15, 2);
            $table->decimal('single_transaction_limit', 15, 2);

            // Deposit limits
            $table->decimal('daily_deposit_limit', 15, 2);
            $table->decimal('monthly_deposit_limit', 15, 2);

            // Withdrawal limits
            $table->decimal('daily_withdrawal_limit', 15, 2);
            $table->decimal('monthly_withdrawal_limit', 15, 2);

            // Transfer limits
            $table->decimal('daily_transfer_limit', 15, 2);
            $table->decimal('monthly_transfer_limit', 15, 2);

            // Requirements
            $table->json('kyc_requirements')->nullable();    // Required KYC level
            $table->integer('min_account_age_days')->default(0);
            $table->decimal('min_monthly_volume', 15, 2)->default(0);
            $table->integer('min_successful_transactions')->default(0);

            // Features
            $table->json('allowed_features')->nullable();    // Features enabled at this tier
            $table->decimal('fee_discount_percent', 5, 2)->default(0);

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_type', 'level']);
            $table->index(['account_type', 'is_default']);
        });

        // User wallet tier assignment and limits tracking
        Schema::create('wallet_limits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('wallet_id');
            $table->unsignedBigInteger('tier_id');

            // Current usage tracking
            $table->decimal('daily_transaction_used', 15, 2)->default(0);
            $table->decimal('weekly_transaction_used', 15, 2)->default(0);
            $table->decimal('monthly_transaction_used', 15, 2)->default(0);
            $table->decimal('daily_deposit_used', 15, 2)->default(0);
            $table->decimal('monthly_deposit_used', 15, 2)->default(0);
            $table->decimal('daily_withdrawal_used', 15, 2)->default(0);
            $table->decimal('monthly_withdrawal_used', 15, 2)->default(0);
            $table->decimal('daily_transfer_used', 15, 2)->default(0);
            $table->decimal('monthly_transfer_used', 15, 2)->default(0);

            // Period tracking
            $table->date('daily_reset_date');
            $table->date('weekly_reset_date');
            $table->date('monthly_reset_date');

            // Override limits (for special cases)
            $table->decimal('override_max_balance', 15, 2)->nullable();
            $table->decimal('override_daily_limit', 15, 2)->nullable();
            $table->decimal('override_monthly_limit', 15, 2)->nullable();
            $table->timestamp('override_expires_at')->nullable();
            $table->string('override_reason')->nullable();
            $table->unsignedBigInteger('override_by')->nullable();

            // Tier upgrade tracking
            $table->timestamp('tier_assigned_at');
            $table->timestamp('last_tier_review_at')->nullable();
            $table->boolean('eligible_for_upgrade')->default(false);

            $table->timestamps();

            $table->unique('wallet_id');
            $table->foreign('tier_id')->references('id')->on('wallet_tiers');
            $table->index(['tier_id', 'eligible_for_upgrade']);
        });

        // Daily balance snapshots for perfect balance verification
        Schema::create('daily_balance_sheets', function (Blueprint $table) {
            $table->id();
            $table->date('sheet_date')->unique();

            // Assets
            $table->decimal('total_bank_accounts', 20, 2);
            $table->decimal('total_mobile_money', 20, 2);
            $table->decimal('total_float', 20, 2)->default(0);
            $table->decimal('total_assets', 20, 2);

            // Liabilities
            $table->decimal('total_customer_wallets', 20, 2);
            $table->decimal('total_merchant_wallets', 20, 2);
            $table->decimal('total_pending_payouts', 20, 2)->default(0);
            $table->decimal('total_liabilities', 20, 2);

            // Equity
            $table->decimal('total_platform_earnings', 20, 2);
            $table->decimal('total_reserves', 20, 2)->default(0);
            $table->decimal('total_equity', 20, 2);

            // Balance verification
            $table->decimal('calculated_balance', 20, 2);    // Assets - Liabilities - Equity
            $table->boolean('is_balanced')->default(false);  // Should be 0

            // Period activity
            $table->integer('transaction_count')->default(0);
            $table->decimal('transaction_volume', 20, 2)->default(0);
            $table->decimal('fees_collected', 20, 2)->default(0);
            $table->decimal('fees_paid', 20, 2)->default(0);

            $table->json('breakdown')->nullable();           // Detailed breakdown by account
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('is_balanced');
        });

        // Add columns to existing wallets table if exists
        // This is typically done via a separate migration
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_balance_sheets');
        Schema::dropIfExists('wallet_limits');
        Schema::dropIfExists('wallet_tiers');
        Schema::dropIfExists('general_ledger');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('platform_earnings');
        Schema::dropIfExists('fee_configurations');
    }
};
