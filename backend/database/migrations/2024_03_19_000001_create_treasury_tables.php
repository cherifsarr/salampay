<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Treasury Management System Tables
     *
     * Manages fund flow between:
     * - Fiat custodian accounts (bank accounts)
     * - Mobile merchant accounts (Wave, Orange Money, etc.)
     *
     * Ensures double-entry bookkeeping and full traceability.
     */
    public function up(): void
    {
        // Custodian accounts (bank accounts holding platform funds)
        Schema::create('custodian_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');                          // "CBAO Operating Account"
            $table->string('bank_name');                     // "CBAO"
            $table->string('bank_code')->nullable();         // Bank identifier code
            $table->string('account_number');                // Encrypted bank account number
            $table->string('iban')->nullable();              // International Bank Account Number
            $table->string('swift_code')->nullable();        // SWIFT/BIC code
            $table->enum('account_type', [
                'checking',      // Operating account for daily transactions
                'savings',       // Interest-bearing deposit account
                'sweep',         // Target account for excess funds
                'reserve',       // Emergency reserve account
            ])->default('checking');
            $table->string('currency', 3)->default('XOF');
            $table->decimal('balance', 20, 2)->default(0);   // Current known balance
            $table->decimal('available_balance', 20, 2)->default(0);
            $table->decimal('minimum_balance', 20, 2)->default(0);     // Keep at least this amount
            $table->decimal('target_balance', 20, 2)->nullable();      // Ideal balance to maintain
            $table->decimal('maximum_balance', 20, 2)->nullable();     // Trigger sweep above this
            $table->boolean('is_primary')->default(false);   // Primary operating account
            $table->boolean('is_sweep_target')->default(false);  // Receives swept funds
            $table->boolean('is_funding_source')->default(false); // Can fund mobile accounts
            $table->enum('status', ['active', 'inactive', 'frozen'])->default('active');
            $table->timestamp('balance_updated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'account_type']);
            $table->index('is_primary');
        });

        // Provider merchant accounts (mobile money accounts)
        Schema::create('provider_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('provider');                      // wave, orange_money, free_money, etc.
            $table->string('name');                          // "Wave Merchant Account"
            $table->string('account_id');                    // Provider's account identifier
            $table->string('phone')->nullable();             // Associated phone number
            $table->string('currency', 3)->default('XOF');
            $table->decimal('balance', 20, 2)->default(0);   // Current balance from provider
            $table->decimal('available_balance', 20, 2)->default(0);
            $table->decimal('pending_balance', 20, 2)->default(0);  // Funds in transit
            $table->decimal('minimum_balance', 20, 2)->default(500000);   // 500K XOF minimum
            $table->decimal('target_balance', 20, 2)->default(2000000);   // 2M XOF target
            $table->decimal('maximum_balance', 20, 2)->default(10000000); // 10M XOF cap (varies by provider)
            $table->decimal('daily_limit', 20, 2)->nullable();   // Daily transaction limit
            $table->decimal('monthly_limit', 20, 2)->nullable(); // Monthly transaction limit
            $table->decimal('daily_volume', 20, 2)->default(0);  // Today's transaction volume
            $table->decimal('monthly_volume', 20, 2)->default(0); // This month's volume
            $table->boolean('auto_sweep_enabled')->default(true);  // Auto-transfer excess to bank
            $table->boolean('auto_fund_enabled')->default(true);   // Auto-request funds when low
            $table->enum('status', ['active', 'inactive', 'maintenance', 'suspended'])->default('active');
            $table->timestamp('balance_updated_at')->nullable();
            $table->timestamp('last_sweep_at')->nullable();
            $table->timestamp('last_fund_at')->nullable();
            $table->json('api_credentials')->nullable();     // Encrypted API keys
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['provider', 'account_id']);
            $table->index(['status', 'provider']);
        });

        // Treasury transfers (movement between accounts)
        Schema::create('treasury_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference')->unique();           // Internal reference
            $table->string('external_reference')->nullable(); // Bank/provider reference
            $table->enum('type', [
                'sweep',         // Mobile → Bank (excess funds)
                'fund',          // Bank → Mobile (liquidity)
                'rebalance',     // Mobile ↔ Mobile
                'bank_transfer', // Bank → Bank
                'manual',        // Manual adjustment
                'fee',           // Fee payment
                'interest',      // Interest credit
            ]);
            $table->enum('direction', ['inbound', 'outbound', 'internal']);

            // Source account
            $table->string('source_type');                   // 'custodian' or 'provider'
            $table->unsignedBigInteger('source_id');
            $table->decimal('source_balance_before', 20, 2);
            $table->decimal('source_balance_after', 20, 2);

            // Destination account
            $table->string('destination_type');
            $table->unsignedBigInteger('destination_id');
            $table->decimal('destination_balance_before', 20, 2);
            $table->decimal('destination_balance_after', 20, 2);

            $table->decimal('amount', 20, 2);
            $table->decimal('fee', 20, 2)->default(0);
            $table->decimal('net_amount', 20, 2);
            $table->string('currency', 3)->default('XOF');

            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'cancelled',
                'reversed',
            ])->default('pending');
            $table->string('status_reason')->nullable();

            $table->enum('initiated_by', [
                'system',        // Automatic sweep/fund
                'admin',         // Manual by admin
                'scheduler',     // Scheduled task
                'reconciliation', // Reconciliation adjustment
            ])->default('system');
            $table->unsignedBigInteger('initiated_by_user_id')->nullable();

            $table->text('description')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('initiated_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
            $table->index(['source_type', 'source_id']);
            $table->index(['destination_type', 'destination_id']);
            $table->index('initiated_at');
        });

        // Treasury ledger (double-entry bookkeeping)
        Schema::create('treasury_ledger', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('entry_date');                    // YYYY-MM-DD

            // Account reference (polymorphic)
            $table->string('account_type');                  // 'custodian' or 'provider'
            $table->unsignedBigInteger('account_id');

            // Double-entry
            $table->decimal('debit', 20, 2)->default(0);     // Money going out
            $table->decimal('credit', 20, 2)->default(0);    // Money coming in
            $table->decimal('balance', 20, 2);               // Running balance after entry

            // Reference to source transaction
            $table->string('reference_type');                // 'transaction', 'transfer', 'adjustment'
            $table->unsignedBigInteger('reference_id');
            $table->string('external_reference')->nullable();

            $table->enum('entry_type', [
                'customer_deposit',      // Customer deposited funds
                'customer_withdrawal',   // Customer withdrew funds
                'merchant_payment',      // Payment to merchant
                'merchant_payout',       // Payout from merchant
                'sweep',                 // Excess funds to bank
                'funding',               // Funds from bank
                'fee_collected',         // Platform fee
                'fee_paid',              // Provider/bank fee
                'refund',                // Refund processed
                'adjustment',            // Manual adjustment
                'interest',              // Interest earned
                'reconciliation',        // Reconciliation entry
            ]);

            $table->string('currency', 3)->default('XOF');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();

            $table->boolean('is_reconciled')->default(false);
            $table->timestamp('reconciled_at')->nullable();
            $table->unsignedBigInteger('reconciled_by')->nullable();

            $table->timestamps();

            $table->index(['account_type', 'account_id', 'entry_date']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['entry_date', 'entry_type']);
            $table->index('is_reconciled');
        });

        // Balance snapshots (daily/hourly snapshots for reconciliation)
        Schema::create('balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('snapshot_type');                 // 'hourly', 'daily', 'weekly', 'monthly'
            $table->timestamp('snapshot_at');
            $table->string('account_type');
            $table->unsignedBigInteger('account_id');

            $table->decimal('reported_balance', 20, 2);      // Balance from provider/bank
            $table->decimal('calculated_balance', 20, 2);    // Balance from our ledger
            $table->decimal('discrepancy', 20, 2)->default(0);
            $table->boolean('is_reconciled')->default(false);

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['account_type', 'account_id', 'snapshot_at']);
            $table->index(['snapshot_type', 'snapshot_at']);
        });

        // Treasury rules (configurable sweep/fund rules)
        Schema::create('treasury_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->enum('rule_type', [
                'sweep',              // When to sweep excess funds
                'fund',               // When to request funding
                'rebalance',          // When to rebalance between accounts
                'alert',              // When to send alerts
            ]);

            // Condition
            $table->string('account_type')->nullable();      // 'custodian', 'provider', or null for all
            $table->unsignedBigInteger('account_id')->nullable(); // Specific account or null for all
            $table->string('provider')->nullable();          // Specific provider or null

            $table->enum('condition_field', [
                'balance',
                'available_balance',
                'daily_volume',
                'monthly_volume',
            ]);
            $table->enum('condition_operator', ['>', '<', '>=', '<=', '=']);
            $table->decimal('condition_value', 20, 2);

            // Action
            $table->enum('action', [
                'transfer',           // Initiate transfer
                'alert',              // Send alert
                'disable_deposits',   // Stop accepting deposits
                'disable_withdrawals', // Stop allowing withdrawals
            ]);
            $table->unsignedBigInteger('target_account_id')->nullable(); // Target for transfer
            $table->decimal('transfer_amount', 20, 2)->nullable(); // Fixed amount or null for calculated

            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->integer('cooldown_minutes')->default(60); // Minimum time between executions
            $table->timestamp('last_executed_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'rule_type', 'priority']);
        });

        // Reconciliation reports
        Schema::create('reconciliation_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('report_type');                   // 'daily', 'weekly', 'monthly', 'ad_hoc'
            $table->date('period_start');
            $table->date('period_end');

            $table->decimal('total_custodian_balance', 20, 2);
            $table->decimal('total_provider_balance', 20, 2);
            $table->decimal('total_customer_wallets', 20, 2);
            $table->decimal('total_merchant_wallets', 20, 2);
            $table->decimal('total_pending_transactions', 20, 2);
            $table->decimal('total_platform_fees', 20, 2);

            // Verification
            $table->decimal('expected_total', 20, 2);        // What we should have
            $table->decimal('actual_total', 20, 2);          // What we actually have
            $table->decimal('discrepancy', 20, 2);           // Difference

            $table->enum('status', ['pending', 'balanced', 'discrepancy', 'resolved']);
            $table->text('notes')->nullable();
            $table->json('details')->nullable();             // Detailed breakdown

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['report_type', 'period_start']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_reports');
        Schema::dropIfExists('treasury_rules');
        Schema::dropIfExists('balance_snapshots');
        Schema::dropIfExists('treasury_ledger');
        Schema::dropIfExists('treasury_transfers');
        Schema::dropIfExists('provider_accounts');
        Schema::dropIfExists('custodian_accounts');
    }
};
