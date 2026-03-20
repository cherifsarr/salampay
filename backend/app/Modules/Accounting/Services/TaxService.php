<?php

namespace App\Modules\Accounting\Services;

use App\Models\Transaction;
use App\Modules\Accounting\Models\TaxCollection;
use App\Modules\Accounting\Models\TaxConfiguration;
use App\Modules\Accounting\Models\TaxSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaxService
{
    /**
     * Chart of Account codes for tax liabilities
     */
    public const TAX_ACCOUNTS = [
        'vat' => '2410',
        'transaction_tax' => '2420',
        'withholding_tax' => '2430',
        'levy' => '2420',
        'stamp_duty' => '2420',
        'other' => '2420',
    ];

    /**
     * Calculate all applicable taxes for a transaction
     */
    public function calculateTaxes(
        string $transactionType,
        float $amount,
        float $feeAmount = 0,
        ?string $provider = null
    ): array {
        return TaxConfiguration::calculateAllTaxes(
            $transactionType,
            $amount,
            $feeAmount,
            $provider
        );
    }

    /**
     * Get tax preview for a transaction (before execution)
     */
    public function getTaxPreview(
        string $transactionType,
        float $amount,
        float $feeAmount = 0,
        ?string $provider = null
    ): array {
        $result = $this->calculateTaxes($transactionType, $amount, $feeAmount, $provider);

        return [
            'transaction_type' => $transactionType,
            'amount' => $amount,
            'fee_amount' => $feeAmount,
            'total_tax' => $result['total_tax'],
            'total_with_tax' => $amount + $feeAmount + $result['total_tax'],
            'taxes' => array_map(function ($tax) {
                return [
                    'code' => $tax['config_code'],
                    'type' => $tax['tax_type'],
                    'authority' => $tax['authority'] ?? null,
                    'amount' => $tax['tax'],
                    'rate' => $tax['rate'],
                    'payer' => $tax['payer'],
                ];
            }, $result['breakdown']),
        ];
    }

    /**
     * Record tax collection for a completed transaction
     */
    public function recordTaxCollection(
        Transaction $transaction,
        array $taxBreakdown
    ): array {
        $collections = [];

        foreach ($taxBreakdown['breakdown'] ?? [] as $tax) {
            if ($tax['tax'] <= 0) {
                continue;
            }

            // Determine payer
            $payerType = $tax['payer'];
            $payerId = null;

            if ($payerType === 'customer' && $transaction->source_user_id) {
                $payerId = $transaction->source_user_id;
            } elseif ($payerType === 'merchant' && $transaction->merchant_id) {
                $payerId = $transaction->merchant_id;
            }

            $collection = TaxCollection::create([
                'tax_config_id' => $tax['config_id'],
                'transaction_id' => $transaction->id,
                'taxable_amount' => $tax['taxable_amount'],
                'tax_amount' => $tax['tax'],
                'currency' => $transaction->currency,
                'payer_type' => $payerType,
                'payer_id' => $payerId,
                'calculation_details' => $tax,
            ]);

            $collections[] = $collection;
        }

        Log::info('Tax collection recorded', [
            'transaction_id' => $transaction->id,
            'total_tax' => $taxBreakdown['total_tax'] ?? 0,
            'collection_count' => count($collections),
        ]);

        return $collections;
    }

    /**
     * Get tax accounting entry for a transaction
     * Returns ledger entries for tax liability
     */
    public function getTaxLedgerEntries(
        Transaction $transaction,
        array $taxBreakdown
    ): array {
        $entries = [];

        foreach ($taxBreakdown['breakdown'] ?? [] as $tax) {
            if ($tax['tax'] <= 0) {
                continue;
            }

            $accountCode = self::TAX_ACCOUNTS[$tax['tax_type']] ?? '2420';

            // Tax is a liability we owe to the government
            // Credit increases liability
            $entries[] = [
                'account_code' => $accountCode,
                'debit' => 0,
                'credit' => $tax['tax'],
                'description' => sprintf(
                    '%s collected: %s',
                    $tax['config_code'] ?? 'Tax',
                    $tax['authority'] ?? 'Government'
                ),
            ];
        }

        return $entries;
    }

    /**
     * Get unremitted tax totals by authority
     */
    public function getUnremittedTaxes(): array
    {
        return TaxCollection::getUnremittedTotals();
    }

    /**
     * Get tax due for a specific period
     */
    public function getTaxDueForPeriod(
        int $year,
        int $month,
        ?string $taxCode = null
    ): array {
        $query = TaxCollection::forMonth($year, $month)->unremitted();

        if ($taxCode) {
            $query->whereHas('taxConfiguration', function ($q) use ($taxCode) {
                $q->where('code', $taxCode);
            });
        }

        $collections = $query->with('taxConfiguration')->get();

        $byAuthority = $collections->groupBy(function ($c) {
            return $c->taxConfiguration->authority ?? 'Unknown';
        })->map(function ($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('tax_amount'),
                'taxes' => $group->groupBy(function ($c) {
                    return $c->taxConfiguration->code;
                })->map(function ($taxGroup) {
                    return [
                        'name' => $taxGroup->first()->taxConfiguration->name,
                        'count' => $taxGroup->count(),
                        'total' => $taxGroup->sum('tax_amount'),
                    ];
                })->toArray(),
            ];
        })->toArray();

        return [
            'period' => sprintf('%04d-%02d', $year, $month),
            'total_due' => $collections->sum('tax_amount'),
            'collection_count' => $collections->count(),
            'by_authority' => $byAuthority,
        ];
    }

    /**
     * Mark taxes as remitted for a period
     */
    public function remitTaxes(
        string $taxCode,
        int $year,
        int $month,
        string $remittanceReference
    ): array {
        $taxConfig = TaxConfiguration::where('code', $taxCode)->first();

        if (!$taxConfig) {
            throw new \Exception("Tax configuration not found: {$taxCode}");
        }

        $collections = TaxCollection::where('tax_config_id', $taxConfig->id)
            ->forMonth($year, $month)
            ->unremitted()
            ->get();

        $totalRemitted = 0;

        DB::transaction(function () use ($collections, $remittanceReference, &$totalRemitted) {
            foreach ($collections as $collection) {
                $collection->markAsRemitted($remittanceReference);
                $totalRemitted += $collection->tax_amount;
            }
        });

        // Update or create monthly summary
        $summary = TaxSummary::generateMonthlySummary($taxConfig, $year, $month);
        $summary->markAsPaid($remittanceReference);

        Log::info('Tax remitted', [
            'tax_code' => $taxCode,
            'period' => sprintf('%04d-%02d', $year, $month),
            'total_remitted' => $totalRemitted,
            'reference' => $remittanceReference,
        ]);

        return [
            'tax_code' => $taxCode,
            'period' => sprintf('%04d-%02d', $year, $month),
            'collections_remitted' => $collections->count(),
            'total_remitted' => $totalRemitted,
            'reference' => $remittanceReference,
        ];
    }

    /**
     * Generate monthly tax report
     */
    public function generateMonthlyReport(int $year, int $month): array
    {
        $taxConfigs = TaxConfiguration::where('is_active', true)->get();
        $summaries = [];

        foreach ($taxConfigs as $config) {
            $summary = TaxSummary::generateMonthlySummary($config, $year, $month);
            $summaries[] = [
                'tax_code' => $config->code,
                'tax_name' => $config->name,
                'authority' => $config->authority,
                'transaction_count' => $summary->transaction_count,
                'total_taxable' => (float) $summary->total_taxable_amount,
                'total_collected' => (float) $summary->total_tax_collected,
                'total_remitted' => (float) $summary->total_tax_remitted,
                'balance_due' => (float) $summary->tax_balance_due,
                'status' => $summary->status,
            ];
        }

        $totals = [
            'total_taxable' => array_sum(array_column($summaries, 'total_taxable')),
            'total_collected' => array_sum(array_column($summaries, 'total_collected')),
            'total_remitted' => array_sum(array_column($summaries, 'total_remitted')),
            'total_balance_due' => array_sum(array_column($summaries, 'balance_due')),
        ];

        return [
            'period' => sprintf('%04d-%02d', $year, $month),
            'generated_at' => now()->toISOString(),
            'summaries' => $summaries,
            'totals' => $totals,
        ];
    }
}
