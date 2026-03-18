<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Merchant extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'business_name',
        'business_type',
        'registration_number',
        'tax_id',
        'industry_code',
        'website',
        'description',
        'logo_url',
        'kyb_status',
        'kyb_approved_at',
        'fee_tier_id',
        'settlement_schedule',
        'settlement_day',
        'settlement_account_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'kyb_approved_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($merchant) {
            if (empty($merchant->uuid)) {
                $merchant->uuid = Str::uuid();
            }
        });
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(MerchantStore::class);
    }

    public function wallets(): MorphMany
    {
        return $this->morphMany(Wallet::class, 'owner');
    }

    public function mainWallet(): HasOne
    {
        return $this->morphOne(Wallet::class, 'owner')
            ->where('wallet_type', 'main');
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    public function paymentLinks(): HasMany
    {
        return $this->hasMany(PaymentLink::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function settlementBatches(): HasMany
    {
        return $this->hasMany(SettlementBatch::class);
    }

    public function feeTier(): BelongsTo
    {
        return $this->belongsTo(FeeTier::class);
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isKybApproved(): bool
    {
        return $this->kyb_status === 'approved';
    }

    public function canAcceptPayments(): bool
    {
        return $this->isActive() && $this->isKybApproved();
    }

    public function getBalance(): float
    {
        return $this->mainWallet?->balance ?? 0;
    }
}
