<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Wallet extends Model
{
    protected $fillable = [
        'uuid',
        'owner_type',
        'owner_id',
        'wallet_type',
        'currency',
        'balance',
        'available_balance',
        'pending_balance',
        'daily_limit',
        'monthly_limit',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'available_balance' => 'decimal:2',
            'pending_balance' => 'decimal:2',
            'daily_limit' => 'decimal:2',
            'monthly_limit' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($wallet) {
            if (empty($wallet->uuid)) {
                $wallet->uuid = Str::uuid();
            }
            if (empty($wallet->available_balance)) {
                $wallet->available_balance = $wallet->balance ?? 0;
            }
        });
    }

    // Relationships
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function holds(): HasMany
    {
        return $this->hasMany(WalletHold::class);
    }

    public function activeHolds(): HasMany
    {
        return $this->hasMany(WalletHold::class)
            ->whereNull('released_at')
            ->where('expires_at', '>', now());
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function outgoingTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'source_wallet_id');
    }

    public function incomingTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'destination_wallet_id');
    }

    // Operations
    public function credit(float $amount, ?Transaction $transaction = null): LedgerEntry
    {
        $balanceBefore = $this->balance;
        $this->balance += $amount;
        $this->available_balance += $amount;
        $this->save();

        return $this->ledgerEntries()->create([
            'transaction_id' => $transaction?->id,
            'entry_type' => 'credit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
        ]);
    }

    public function debit(float $amount, ?Transaction $transaction = null): LedgerEntry
    {
        if ($this->available_balance < $amount) {
            throw new \Exception('Insufficient balance');
        }

        $balanceBefore = $this->balance;
        $this->balance -= $amount;
        $this->available_balance -= $amount;
        $this->save();

        return $this->ledgerEntries()->create([
            'transaction_id' => $transaction?->id,
            'entry_type' => 'debit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
        ]);
    }

    public function hold(float $amount, string $reason, \DateTime $expiresAt): WalletHold
    {
        if ($this->available_balance < $amount) {
            throw new \Exception('Insufficient available balance');
        }

        $this->available_balance -= $amount;
        $this->save();

        return $this->holds()->create([
            'amount' => $amount,
            'reason' => $reason,
            'expires_at' => $expiresAt,
        ]);
    }

    public function releaseHold(WalletHold $hold): void
    {
        if ($hold->wallet_id !== $this->id || $hold->released_at !== null) {
            return;
        }

        $this->available_balance += $hold->amount;
        $this->save();

        $hold->released_at = now();
        $hold->save();
    }

    // Helpers
    public function getAvailableBalance(): float
    {
        return (float) $this->available_balance;
    }

    public function getHeldBalance(): float
    {
        return $this->activeHolds()->sum('amount');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canDebit(float $amount): bool
    {
        return $this->isActive() && $this->available_balance >= $amount;
    }

    public function recalculateBalance(): void
    {
        $credits = $this->ledgerEntries()->where('entry_type', 'credit')->sum('amount');
        $debits = $this->ledgerEntries()->where('entry_type', 'debit')->sum('amount');
        $holds = $this->activeHolds()->sum('amount');

        $this->balance = $credits - $debits;
        $this->available_balance = $this->balance - $holds;
        $this->save();
    }
}
