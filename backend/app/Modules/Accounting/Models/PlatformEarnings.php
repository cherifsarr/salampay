<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformEarnings extends Model
{
    protected $fillable = [
        'uuid',
        'reference',
        'type',
        'amount',
        'currency',
        'source_type',
        'source_id',
        'source_reference',
        'customer_id',
        'merchant_id',
        'provider',
        'description',
        'breakdown',
        'metadata',
        'is_withdrawn',
        'withdrawn_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'breakdown' => 'array',
        'metadata' => 'array',
        'is_withdrawn' => 'boolean',
        'withdrawn_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
            if (!$model->reference) {
                $model->reference = 'EARN-' . strtoupper(Str::random(10));
            }
        });
    }

    // Scopes

    public function scopeNotWithdrawn($query)
    {
        return $query->where('is_withdrawn', false);
    }

    public function scopeWithdrawn($query)
    {
        return $query->where('is_withdrawn', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForPeriod($query, string $start, string $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    // Aggregations

    public static function getTotalEarnings(): float
    {
        return (float) self::sum('amount');
    }

    public static function getUnwithdrawnEarnings(): float
    {
        return (float) self::notWithdrawn()->sum('amount');
    }

    public static function getEarningsByType(): array
    {
        return self::selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();
    }

    public static function getDailyEarnings(int $days = 30): array
    {
        return self::selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();
    }
}
