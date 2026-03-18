<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('owner_type'); // user, merchant, store, system
            $table->unsignedBigInteger('owner_id');
            $table->enum('wallet_type', ['main', 'reserve', 'settlement', 'fee_collection'])->default('main');
            $table->string('currency', 3)->default('XOF');
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('available_balance', 15, 2)->default(0);
            $table->decimal('pending_balance', 15, 2)->default(0);
            $table->decimal('daily_limit', 15, 2)->nullable();
            $table->decimal('monthly_limit', 15, 2)->nullable();
            $table->enum('status', ['active', 'frozen', 'closed'])->default('active');
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'wallet_type']);
            $table->index(['owner_type', 'owner_id']);
            $table->index('status');
        });

        Schema::create('wallet_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('reason', 255)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_holds');
        Schema::dropIfExists('wallets');
    }
};
