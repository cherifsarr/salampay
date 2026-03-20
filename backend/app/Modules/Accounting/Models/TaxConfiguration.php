<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TaxConfiguration extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'authority',
        'regulation_reference',
        'tax_type',
        'calculation_type',
        'percentage_rate',
        'fixed_amount',
        'minimum_tax',
        'maximum_tax',
        'tiers',
        'applies_to_types',
        'applies_to_providers',
        'threshold_amount',
        'applies_to_fees',
        'payer',
        'split_customer_percent',
        'split_merchant_percent',
        'is_active',
        'effective_from',
        'effective_until',
        'is_mandatory',
        'description',
        'metadata',
    ];

    protected $casts = [
        'percentage_rate' => 'decimal:5',
        'fixed_amount' => 'decimal:2',
        'minimum_tax' => 'decimal:2',
        'maximum_tax' => 'decimal:2',
        'threshold_amount' => 'decimal:2',
        'split_customer_percent' => 'decimal:2',
        'split_merchant_percent' => 'decimal:2',
        'tiers' => 'array',
        'applies_to_types' => 'array',
        'applies_to_providers' => 'array',
        'is_active' => 'boolean',
        'applies_to_fees' => 'boolean',
        'is_mandatory' => 'boolean',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'metadata' => 'array',
    ];

    /**
     * Default tax configurations for Senegal
     * Based on BCEAO regulations and DGID requirements
     */
    public const SENEGAL_TAXES = [
        // Mobile Money Transaction Tax (commonly 1-2%)
        'mobile_money_tax' => [
            'name' => 'Taxe sur les Transactions Électroniques',
            'code' => 'mobile_money_tax',
            'authority' => 'DGID',
            'tax_type' => 'transaction_tax',
            'calculation_type' => 'percentage',
            'percentage_rate' => 0.01,  // 1%
            'minimum_tax' => 0,
            'maximum_tax' => 5000,  // Cap at 5000 XOF
            'threshold_amount' => 0,
            'applies_to_types' => ['deposit', 'withdrawal', 'transfer_p2p'],
            'payer' => 'customer',
            'is_mandatory' => true,
        ],

        // VAT on platform fees (18% in Senegal)
        'vat_on_fees' => [
            'name' => 'TVA sur Frais de Service',
            'code' => 'vat_on_fees',
            'authority' => 'DGID',
            'tax_type' => 'vat',
            'calculation_type' => 'percentage',
            'percentage_rate' => 0.18,  // 18% VAT
            'applies_to_fees' => true,
            'applies_to_types' => null, // All types
            'payer' => 'customer',
            'is_mandatory' => true,
        ],

        // Merchant payment tax
        'merchant_payment_tax' => [
            'name' => 'Taxe sur Paiements Marchands',
            'code' => 'merchant_payment_tax',
            'authority' => 'DGID',
            'tax_type' => 'transaction_tax',
            'calculation_type' => 'percentage',
            'percentage_rate' => 0.005,  // 0.5%
            'applies_to_types' => ['payment', 'payment_link', 'qr_payment'],
            'payer' => 'merchant',
            'is_mandatory' => true,
        ],
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
            if (!$model->effective_from) {
                $model->effective_from = today();
            }
        });
    }

    // Relationships

    public function collections(): HasMany
    {
        return $this->hasMany(TaxCollection::class, 'tax_config_id');
    }

    public function summaries(): HasMany
    {
        return $this->hasMany(TaxSummary::class, 'tax_config_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('effective_from', '<=', today())
            ->where(function ($q) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>=', today());
            });
    }

    public function scopeForTransactionType($query, string $type)
    {
        return $query->where(function ($q) use ($type) {
            $q->whereNull('applies_to_types')
              ->orWhereJsonContains('applies_to_types', $type);
        });
    }

    public function scopeForProvider($query, ?string $provider)
    {
        if (!$provider) {
            return $query->whereNull('applies_to_providers');
        }

        return $query->where(function ($q) use ($provider) {
            $q->whereNull('applies_to_providers')
              ->orWhereJsonContains('applies_to_providers', $provider);
        });
    }

    // Tax calculation

    /**
     * Calculate tax for a given amount
     */
    public function calculateTax(float $amount, float $feeAmount = 0): array
    {
        // Check threshold
        if ($amount < $this->threshold_amount) {
            return [
                'tax' => 0,
                'taxable_amount' => 0,
                'rate' => 0,
                'type' => $this->calculation_type,
                'config_id' => $this->id,
                'config_code' => $this->code,
                'below_threshold' => true,
            ];
        }

        // Determine taxable amount
        $taxableAmount = $this->applies_to_fees ? ($amount + $feeAmount) : $amount;

        $tax = match ($this->calculation_type) {
            'percentage' => $this->calculatePercentageTax($taxableAmount),
            'fixed' => (float) $this->fixed_amount,
            'mixed' => $this->calculateMixedTax($taxableAmount),
            'tiered' => $this->calculateTieredTax($taxableAmount),
            default => 0,
        };

        // Apply min/max
        if ($this->minimum_tax > 0 && $tax < $this->minimum_tax) {
            $tax = (float) $this->minimum_tax;
        }
        if ($this->maximum_tax && $tax > $this->maximum_tax) {
            $tax = (float) $this->maximum_tax;
        }

        $tax = round($tax, 2);

        return [
            'tax' => $tax,
            'taxable_amount' => $taxableAmount,
            'rate' => $this->percentage_rate,
            'type' => $this->calculation_type,
            'config_id' => $this->id,
            'config_code' => $this->code,
            'authority' => $this->authority,
            'tax_type' => $this->tax_type,
            'payer' => $this->payer,
            'split' => $this->payer === 'split' ? [
                'customer_percent' => $this->split_customer_percent,
                'merchant_percent' => $this->split_merchant_percent,
            ] : null,
        ];
    }

    protected function calculatePercentageTax(float $amount): float
    {
        return $amount * (float) $this->percentage_rate;
    }

    protected function calculateMixedTax(float $amount): float
    {
        $percentageTax = $amount * (float) $this->percentage_rate;
        return $percentageTax + (float) $this->fixed_amount;
    }

    protected function calculateTieredTax(float $amount): float
    {
        if (!$this->tiers || empty($this->tiers)) {
            return 0;
        }

        foreach ($this->tiers as $tier) {
            $min = $tier['min'] ?? 0;
            $max = $tier['max'] ?? PHP_FLOAT_MAX;
            $rate = $tier['rate'] ?? 0;

            if ($amount >= $min && $amount < $max) {
                return $amount * $rate;
            }
        }

        return 0;
    }

    /**
     * Get all applicable taxes for a transaction
     */
    public static function getApplicableTaxes(
        string $transactionType,
        ?string $provider = null
    ): \Illuminate\Database\Eloquent\Collection {
        return static::active()
            ->forTransactionType($transactionType)
            ->forProvider($provider)
            ->orderBy('tax_type')
            ->get();
    }

    /**
     * Calculate all taxes for a transaction
     */
    public static function calculateAllTaxes(
        string $transactionType,
        float $amount,
        float $feeAmount = 0,
        ?string $provider = null
    ): array {
        $taxes = static::getApplicableTaxes($transactionType, $provider);

        $totalTax = 0;
        $breakdown = [];

        foreach ($taxes as $taxConfig) {
            $result = $taxConfig->calculateTax($amount, $feeAmount);
            $totalTax += $result['tax'];
            $breakdown[] = $result;
        }

        return [
            'total_tax' => round($totalTax, 2),
            'breakdown' => $breakdown,
            'tax_count' => count($breakdown),
        ];
    }

    /**
     * Seed default Senegal tax configurations
     */
    public static function seedDefaults(): void
    {
        foreach (self::SENEGAL_TAXES as $code => $config) {
            static::updateOrCreate(
                ['code' => $config['code']],
                array_merge($config, [
                    'uuid' => Str::uuid(),
                    'is_active' => true,
                    'effective_from' => today(),
                ])
            );
        }
    }
}
