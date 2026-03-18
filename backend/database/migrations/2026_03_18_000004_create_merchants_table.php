<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('business_name', 255);
            $table->enum('business_type', ['individual', 'company', 'ngo', 'government']);
            $table->string('registration_number', 100)->nullable(); // NINEA
            $table->string('tax_id', 100)->nullable();
            $table->string('industry_code', 10)->nullable(); // MCC
            $table->string('website', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->enum('kyb_status', ['pending', 'in_review', 'approved', 'rejected'])->default('pending');
            $table->timestamp('kyb_approved_at')->nullable();
            $table->unsignedBigInteger('fee_tier_id')->nullable();
            $table->enum('settlement_schedule', ['instant', 'daily', 'weekly', 'monthly'])->default('daily');
            $table->tinyInteger('settlement_day')->nullable();
            $table->unsignedBigInteger('settlement_account_id')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended', 'blocked'])->default('pending');
            $table->timestamps();

            $table->index('status');
            $table->index('business_name');
        });

        Schema::create('merchant_stores', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained();
            $table->string('store_name', 255);
            $table->string('store_code', 50)->nullable();
            $table->string('address_line1', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('country', 2)->default('SN');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->json('operating_hours')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index('merchant_id');
            $table->index(['latitude', 'longitude']);
        });

        // QR Codes
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained();
            $table->foreignId('store_id')->nullable()->constrained('merchant_stores');
            $table->enum('qr_type', ['static', 'dynamic']);
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('description', 255)->nullable();
            $table->text('qr_data');
            $table->string('qr_image_url', 500)->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->integer('scan_count')->default(0);
            $table->enum('status', ['active', 'expired', 'disabled'])->default('active');
            $table->timestamps();

            $table->index('merchant_id');
            $table->index('uuid');
        });

        // Payment Links
        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained();
            $table->string('short_code', 20)->unique();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('XOF');
            $table->boolean('allow_tip')->default(false);
            $table->integer('max_uses')->nullable();
            $table->integer('use_count')->default(0);
            $table->timestamp('valid_until')->nullable();
            $table->string('redirect_url', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->enum('status', ['active', 'expired', 'disabled'])->default('active');
            $table->timestamps();

            $table->index('short_code');
            $table->index('merchant_id');
        });

        // Invoices
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('merchant_id')->constrained();
            $table->foreignId('customer_user_id')->nullable()->constrained('users');
            $table->string('customer_name', 255)->nullable();
            $table->string('customer_email', 255)->nullable();
            $table->string('customer_phone', 20)->nullable();

            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('currency', 3)->default('XOF');

            $table->date('issue_date');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();

            $table->foreignId('payment_link_id')->nullable()->constrained();
            $table->unsignedBigInteger('transaction_id')->nullable();

            $table->json('line_items');
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->enum('status', ['draft', 'sent', 'viewed', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payment_links');
        Schema::dropIfExists('qr_codes');
        Schema::dropIfExists('merchant_stores');
        Schema::dropIfExists('merchants');
    }
};
