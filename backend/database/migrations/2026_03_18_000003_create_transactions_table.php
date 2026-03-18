<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 50)->unique(); // SP-YYYYMMDD-XXXXX
            $table->string('external_reference', 100)->nullable();
            $table->string('idempotency_key', 100)->unique()->nullable();

            // Transaction Type
            $table->enum('type', [
                'deposit',
                'withdrawal',
                'transfer_p2p',
                'transfer_merchant',
                'payment_pos',
                'payment_qr',
                'payment_link',
                'payment_invoice',
                'payment_bill',
                'refund',
                'fee',
                'settlement',
                'adjustment'
            ]);

            // Amounts
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('XOF');
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->decimal('exchange_rate', 12, 6)->nullable();

            // Parties
            $table->foreignId('source_wallet_id')->nullable()->constrained('wallets');
            $table->foreignId('destination_wallet_id')->nullable()->constrained('wallets');
            $table->foreignId('source_user_id')->nullable()->constrained('users');
            $table->foreignId('destination_user_id')->nullable()->constrained('users');
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();

            // Provider
            $table->enum('provider', [
                'wave', 'orange_money', 'free_money', 'wizall', 'emoney',
                'visa', 'mastercard', 'bank_transfer', 'internal'
            ]);
            $table->string('provider_transaction_id', 100)->nullable();
            $table->json('provider_response')->nullable();

            // Status
            $table->enum('status', [
                'pending', 'processing', 'completed', 'failed',
                'cancelled', 'refunded', 'disputed'
            ])->default('pending');
            $table->string('status_reason', 255)->nullable();
            $table->timestamp('completed_at')->nullable();

            // Security
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('device_fingerprint', 100)->nullable();
            $table->tinyInteger('risk_score')->default(0);
            $table->timestamp('flagged_at')->nullable();
            $table->string('flagged_reason', 255)->nullable();

            // Metadata
            $table->string('description', 500)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('reference');
            $table->index('external_reference');
            $table->index(['type', 'status']);
            $table->index(['source_wallet_id', 'created_at']);
            $table->index(['destination_wallet_id', 'created_at']);
            $table->index(['merchant_id', 'created_at']);
            $table->index('created_at');
        });

        // Ledger entries for double-entry bookkeeping
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained();
            $table->foreignId('wallet_id')->constrained();
            $table->enum('entry_type', ['debit', 'credit']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('transactions');
    }
};
