<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Government Mandated Operating Tax Configuration
     *
     * Supports multiple tax types:
     * - VAT (TVA in French)
     * - Transaction Tax (e.g., mobile money tax)
     * - Withholding Tax
     * - Stamp Duty
     *
     * Each tax can be:
     * - Flat amount
     * - Percentage of transaction
     * - Mixed (percentage + flat minimum/maximum)
     * - Tiered (different rates for different amounts)
     */
    public function up(): void
    {
        // Tax configuration table
        Schema::create('tax_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');                          // "TVA", "Mobile Money Tax", etc.
            $table->string('code')->unique();                // tva, mobile_money_tax, etc.
            $table->string('authority');                     // "DGID", "BCEAO", etc. (tax authority)
            $table->string('regulation_reference')->nullable(); // Law/regulation number

            $table->enum('tax_type', [
                'vat',                   // Value Added Tax (TVA)
                'transaction_tax',       // Per-transaction tax
                'withholding_tax',       // Withholding tax
                'stamp_duty',            // Stamp duty
                'levy',                  // Special levy
                'other',
            ]);

            $table->enum('calculation_type', [
                'percentage',            // X% of amount
                'fixed',                 // Fixed amount per transaction
                'mixed',                 // Percentage with min/max
                'tiered',                // Based on amount tiers
            ]);

            $table->decimal('percentage_rate', 8, 5)->default(0);     // e.g., 0.01800 = 1.8%
            $table->decimal('fixed_amount', 15, 2)->default(0);
            $table->decimal('minimum_tax', 15, 2)->default(0);
            $table->decimal('maximum_tax', 15, 2)->nullable();
            $table->json('tiers')->nullable();                         // For tiered calculation

            // Application rules
            $table->json('applies_to_types')->nullable();              // Transaction types: ["deposit", "withdrawal"]
            $table->json('applies_to_providers')->nullable();          // Providers: ["wave", "orange_money"] or null for all
            $table->decimal('threshold_amount', 15, 2)->default(0);    // Min amount for tax to apply
            $table->boolean('applies_to_fees')->default(false);        // Tax on platform fees too?

            // Who pays
            $table->enum('payer', [
                'customer',              // End user pays
                'merchant',              // Merchant pays
                'platform',              // Platform absorbs (promo)
                'split',                 // Split between parties
            ])->default('customer');

            $table->decimal('split_customer_percent', 5, 2)->default(100); // If split, customer %
            $table->decimal('split_merchant_percent', 5, 2)->default(0);   // If split, merchant %

            // Status and dates
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_mandatory')->default(true);            // Cannot be waived

            // Metadata
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'effective_from', 'effective_until'], 'tax_cfg_active_dates_idx');
            $table->index('tax_type');
        });

        // Tax collection tracking (taxes owed to government)
        Schema::create('tax_collections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference')->unique();

            $table->unsignedBigInteger('tax_config_id');
            $table->unsignedBigInteger('transaction_id');

            $table->decimal('taxable_amount', 15, 2);        // Amount tax was calculated on
            $table->decimal('tax_amount', 15, 2);            // Actual tax collected
            $table->string('currency', 3)->default('XOF');

            $table->string('payer_type');                    // customer, merchant
            $table->unsignedBigInteger('payer_id')->nullable();

            $table->boolean('is_remitted')->default(false);  // Paid to government?
            $table->timestamp('remitted_at')->nullable();
            $table->string('remittance_reference')->nullable();

            // Period tracking for reporting
            $table->date('tax_period_start');
            $table->date('tax_period_end');

            $table->json('calculation_details')->nullable();  // Breakdown of calculation
            $table->timestamps();

            $table->foreign('tax_config_id')->references('id')->on('tax_configurations');
            $table->index(['tax_config_id', 'is_remitted']);
            $table->index(['tax_period_start', 'tax_period_end']);
        });

        // Monthly tax summary for government reporting
        Schema::create('tax_summaries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('tax_config_id');
            $table->date('period_start');
            $table->date('period_end');

            $table->integer('transaction_count')->default(0);
            $table->decimal('total_taxable_amount', 20, 2)->default(0);
            $table->decimal('total_tax_collected', 20, 2)->default(0);
            $table->decimal('total_tax_remitted', 20, 2)->default(0);
            $table->decimal('tax_balance_due', 20, 2)->default(0);

            // Breakdown by type
            $table->json('breakdown_by_type')->nullable();
            $table->json('breakdown_by_provider')->nullable();

            // Filing status
            $table->enum('status', [
                'pending',              // Not yet reported
                'filed',                // Declaration submitted
                'paid',                 // Payment made
                'confirmed',            // Government confirmed
            ])->default('pending');

            $table->timestamp('filed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('filing_reference')->nullable();
            $table->string('payment_reference')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tax_config_id')->references('id')->on('tax_configurations');
            $table->unique(['tax_config_id', 'period_start', 'period_end']);
        });

        // Add tax columns to transactions table if not exists
        if (Schema::hasTable('transactions') && !Schema::hasColumn('transactions', 'tax_amount')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('fee_amount');
                $table->json('tax_breakdown')->nullable()->after('tax_amount');
            });
        }

        // Add tax liability account to chart of accounts
        if (Schema::hasTable('chart_of_accounts')) {
            \DB::table('chart_of_accounts')->insert([
                ['code' => '2400', 'name' => 'Tax Liabilities', 'type' => 'liability', 'subtype' => 'payable', 'parent_code' => '2000', 'level' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['code' => '2410', 'name' => 'VAT Payable', 'type' => 'liability', 'subtype' => 'payable', 'parent_code' => '2400', 'level' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['code' => '2420', 'name' => 'Transaction Tax Payable', 'type' => 'liability', 'subtype' => 'payable', 'parent_code' => '2400', 'level' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['code' => '2430', 'name' => 'Withholding Tax Payable', 'type' => 'liability', 'subtype' => 'payable', 'parent_code' => '2400', 'level' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function down(): void
    {
        // Remove tax columns from transactions if we added them
        if (Schema::hasColumn('transactions', 'tax_amount')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn(['tax_amount', 'tax_breakdown']);
            });
        }

        Schema::dropIfExists('tax_summaries');
        Schema::dropIfExists('tax_collections');
        Schema::dropIfExists('tax_configurations');

        // Remove tax accounts from chart of accounts
        if (Schema::hasTable('chart_of_accounts')) {
            \DB::table('chart_of_accounts')->whereIn('code', ['2400', '2410', '2420', '2430'])->delete();
        }
    }
};
