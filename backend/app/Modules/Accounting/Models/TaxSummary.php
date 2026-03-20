<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TaxSummary extends Model
{
    protected $fillable = [
        'uuid',
        'tax_config_id',
        'period_start',
        'period_end',
        'transaction_count',
        'total_taxable_amount',
        'total_tax_collected',
        'total_tax_remitted',
        'tax_balance_due',
        'breakdown_by_type',
        'breakdown_by_provider',
        'status',
        'filed_at',
        'paid_at',
        'filing_reference',
        'payment_reference',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_taxable_amount' => 'decimal:2',
        'total_tax_collected' => 'decimal:2',
        'total_tax_remitted' => 'decimal:2',
        'tax_balance_due' => 'decimal:2',
        'breakdown_by_type' => 'array',
        'breakdown_by_provider' => 'array',
        'filed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
        });

        static::saving(function ($model) {
            // Auto-calculate balance due
            $model->tax_balance_due = $model->total_tax_collected - $model->total_tax_remitted;
        });
    }

    // Relationships

    public function taxConfiguration(): BelongsTo
    {
        return $this->belongsTo(TaxConfiguration::class, 'tax_config_id');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFiled($query)
    {
        return $query->whereIn('status', ['filed', 'paid', 'confirmed']);
    }

    public function scopeWithBalanceDue($query)
    {
        return $query->where('tax_balance_due', '>', 0);
    }

    // Status transitions

    public function markAsFiled(string $reference): void
    {
        $this->update([
            'status' => 'filed',
            'filed_at' => now(),
            'filing_reference' => $reference,
        ]);
    }

    public function markAsPaid(string $reference): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => $reference,
            'total_tax_remitted' => $this->total_tax_collected,
            'tax_balance_due' => 0,
        ]);
    }

    public function markAsConfirmed(): void
    {
        $this->update(['status' => 'confirmed']);
    }

    /**
     * Generate monthly summary for a tax configuration
     */
    public static function generateMonthlySummary(
        TaxConfiguration $taxConfig,
        int $year,
        int $month
    ): self {
        $start = now()->setYear($year)->setMonth($month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $collections = TaxCollection::where('tax_config_id', $taxConfig->id)
            ->forPeriod($start, $end)
            ->get();

        $byType = $collections->groupBy(function ($c) {
            return $c->transaction->type ?? 'unknown';
        })->map(function ($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('tax_amount'),
            ];
        })->toArray();

        return static::updateOrCreate(
            [
                'tax_config_id' => $taxConfig->id,
                'period_start' => $start,
                'period_end' => $end,
            ],
            [
                'uuid' => Str::uuid(),
                'transaction_count' => $collections->count(),
                'total_taxable_amount' => $collections->sum('taxable_amount'),
                'total_tax_collected' => $collections->sum('tax_amount'),
                'total_tax_remitted' => $collections->where('is_remitted', true)->sum('tax_amount'),
                'breakdown_by_type' => $byType,
            ]
        );
    }
}
