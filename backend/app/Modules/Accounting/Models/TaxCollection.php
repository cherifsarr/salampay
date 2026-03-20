<?php

namespace App\Modules\Accounting\Models;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TaxCollection extends Model
{
    protected $fillable = [
        'uuid',
        'reference',
        'tax_config_id',
        'transaction_id',
        'taxable_amount',
        'tax_amount',
        'currency',
        'payer_type',
        'payer_id',
        'is_remitted',
        'remitted_at',
        'remittance_reference',
        'tax_period_start',
        'tax_period_end',
        'calculation_details',
    ];

    protected $casts = [
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'is_remitted' => 'boolean',
        'remitted_at' => 'datetime',
        'tax_period_start' => 'date',
        'tax_period_end' => 'date',
        'calculation_details' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
            if (!$model->reference) {
                $model->reference = 'TAX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
            }
            if (!$model->tax_period_start) {
                $model->tax_period_start = now()->startOfMonth();
            }
            if (!$model->tax_period_end) {
                $model->tax_period_end = now()->endOfMonth();
            }
        });
    }

    // Relationships

    public function taxConfiguration(): BelongsTo
    {
        return $this->belongsTo(TaxConfiguration::class, 'tax_config_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    // Scopes

    public function scopeUnremitted($query)
    {
        return $query->where('is_remitted', false);
    }

    public function scopeRemitted($query)
    {
        return $query->where('is_remitted', true);
    }

    public function scopeForPeriod($query, $start, $end)
    {
        return $query->where('tax_period_start', '>=', $start)
            ->where('tax_period_end', '<=', $end);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        $start = now()->setYear($year)->setMonth($month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return $query->forPeriod($start, $end);
    }

    // Helpers

    public function markAsRemitted(string $reference): void
    {
        $this->update([
            'is_remitted' => true,
            'remitted_at' => now(),
            'remittance_reference' => $reference,
        ]);
    }

    /**
     * Get total unremitted tax by tax type
     */
    public static function getUnremittedTotals(): array
    {
        return static::unremitted()
            ->join('tax_configurations', 'tax_collections.tax_config_id', '=', 'tax_configurations.id')
            ->selectRaw('tax_configurations.code, tax_configurations.name, tax_configurations.authority')
            ->selectRaw('COUNT(*) as collection_count')
            ->selectRaw('SUM(tax_collections.tax_amount) as total_amount')
            ->groupBy('tax_configurations.code', 'tax_configurations.name', 'tax_configurations.authority')
            ->get()
            ->toArray();
    }
}
