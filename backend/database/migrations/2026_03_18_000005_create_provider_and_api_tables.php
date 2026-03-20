<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Provider accounts (platform's connections to payment providers)
        // Skip if already created by treasury migration
        if (!Schema::hasTable('provider_accounts')) {
            Schema::create('provider_accounts', function (Blueprint $table) {
                $table->id();
                $table->enum('provider', ['wave', 'orange_money', 'free_money', 'wizall', 'emoney', 'card_gateway']);
                $table->string('account_name', 255);
                $table->text('api_key_encrypted');
                $table->text('webhook_secret_encrypted')->nullable();
                $table->boolean('sandbox_mode')->default(false);
                $table->decimal('balance', 15, 2)->default(0);
                $table->timestamp('balance_updated_at')->nullable();
                $table->enum('status', ['active', 'inactive', 'error'])->default('active');
                $table->json('config')->nullable();
                $table->timestamps();

                $table->index(['provider', 'status']);
            });
        }

        // Provider webhooks log
        Schema::create('provider_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_account_id')->constrained();
            $table->string('webhook_id', 100)->nullable();
            $table->string('event_type', 100);
            $table->json('payload');
            $table->string('signature', 255)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps();

            $table->index('webhook_id');
            $table->index('processed_at');
        });

        // Merchant API keys
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained();
            $table->string('name', 100);
            $table->string('key_prefix', 20); // spk_live_ or spk_test_
            $table->string('key_hash', 64);
            $table->string('signing_secret', 64);
            $table->boolean('is_test_mode')->default(false);
            $table->json('allowed_ips')->nullable();
            $table->json('scopes')->nullable();
            $table->integer('rate_limit_per_minute')->default(60);
            $table->string('webhook_url', 500)->nullable();
            $table->string('webhook_secret', 64)->nullable();
            $table->json('webhook_events')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->enum('status', ['active', 'inactive', 'revoked'])->default('active');
            $table->timestamps();

            $table->index('key_hash');
            $table->index('merchant_id');
        });

        // API audit logs
        Schema::create('api_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained();
            $table->string('endpoint', 255);
            $table->string('method', 10);
            $table->text('request_body')->nullable();
            $table->integer('response_code')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('error_code', 50)->nullable();
            $table->timestamps();

            $table->index(['api_key_id', 'created_at']);
        });

        // Fee tiers
        Schema::create('fee_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Fee rules
        Schema::create('fee_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_tier_id')->constrained();
            $table->string('transaction_type', 50);
            $table->string('provider', 50)->nullable();
            $table->decimal('min_amount', 15, 2)->nullable();
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->enum('fee_type', ['fixed', 'percentage', 'mixed']);
            $table->decimal('fixed_fee', 10, 2)->default(0);
            $table->decimal('percentage_fee', 5, 4)->default(0);
            $table->decimal('min_fee', 10, 2)->nullable();
            $table->decimal('max_fee', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['fee_tier_id', 'transaction_type']);
        });

        // Settlement batches
        Schema::create('settlement_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('batch_number', 50)->unique();
            $table->foreignId('merchant_id')->constrained();

            $table->timestamp('period_start');
            $table->timestamp('period_end');

            $table->decimal('gross_amount', 15, 2);
            $table->decimal('fee_amount', 15, 2);
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->decimal('chargeback_amount', 15, 2)->default(0);
            $table->decimal('adjustment_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->string('currency', 3)->default('XOF');

            $table->unsignedBigInteger('settlement_account_id')->nullable();
            $table->enum('settlement_method', ['wave', 'orange_money', 'bank_transfer']);
            $table->timestamp('settled_at')->nullable();
            $table->string('settlement_reference', 100)->nullable();

            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamps();

            $table->index(['merchant_id', 'period_start']);
        });

        // Settlement transactions
        Schema::create('settlement_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_batch_id')->constrained();
            $table->foreignId('transaction_id')->constrained();
            $table->decimal('amount', 15, 2);
            $table->decimal('fee_amount', 15, 2);

            $table->index('settlement_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_transactions');
        Schema::dropIfExists('settlement_batches');
        Schema::dropIfExists('fee_rules');
        Schema::dropIfExists('fee_tiers');
        Schema::dropIfExists('api_audit_logs');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('provider_webhooks');
        Schema::dropIfExists('provider_accounts');
    }
};
