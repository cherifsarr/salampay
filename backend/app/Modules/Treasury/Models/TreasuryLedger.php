<?php

namespace App\Modules\Treasury\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TreasuryLedger extends Model
{
    protected $table = 'treasury_ledger';

    protected $fillable = [
        'uuid',
        'entry_date',
        'account_type',
        'account_id',
        'debit',
        'credit',
        'balance',
        'reference_type',
        'reference_id',
        'external_reference',
        'entry_type',
        'currency',
        'description',
        'metadata',
        'is_reconciled',
        'reconciled_at',
        'reconciled_by',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'balance' => 'decimal:2',
        'metadata' => 'array',
        'is_reconciled' => 'boolean',
        'reconciled_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
            if (!$model->entry_date) {
                $model->entry_date = now()->format('Y-m-d');
            }
        });
    }

    // Relationships

    public function account()
    {
        return $this->account_type === 'custodian'
            ? $this->belongsTo(CustodianAccount::class, 'account_id')
            : $this->belongsTo(ProviderAccount::class, 'account_id');
    }

    public function reconciledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    // Scopes

    public function scopeForAccount($query, string $type, int $id)
    {
        return $query->where('account_type', $type)->where('account_id', $id);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where('entry_date', $date);
    }

    public function scopeForDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('entry_date', [$from, $to]);
    }

    public function scopeUnreconciled($query)
    {
        return $query->where('is_reconciled', false);
    }

    public function scopeReconciled($query)
    {
        return $query->where('is_reconciled', true);
    }

    public function scopeDebits($query)
    {
        return $query->where('debit', '>', 0);
    }

    public function scopeCredits($query)
    {
        return $query->where('credit', '>', 0);
    }

    public function scopeEntryType($query, string $type)
    {
        return $query->where('entry_type', $type);
    }

    // Helpers

    public function isDebit(): bool
    {
        return $this->debit > 0;
    }

    public function isCredit(): bool
    {
        return $this->credit > 0;
    }

    public function getNetAmount(): float
    {
        return $this->credit - $this->debit;
    }

    public function markAsReconciled(int $userId): void
    {
        $this->update([
            'is_reconciled' => true,
            'reconciled_at' => now(),
            'reconciled_by' => $userId,
        ]);
    }

    public function getEntryTypeLabel(): string
    {
        return match ($this->entry_type) {
            'customer_deposit' => 'Customer Deposit',
            'customer_withdrawal' => 'Customer Withdrawal',
            'merchant_payment' => 'Merchant Payment',
            'merchant_payout' => 'Merchant Payout',
            'sweep' => 'Sweep to Bank',
            'funding' => 'Funding from Bank',
            'fee_collected' => 'Fee Collected',
            'fee_paid' => 'Fee Paid',
            'refund' => 'Refund',
            'adjustment' => 'Manual Adjustment',
            'interest' => 'Interest',
            'reconciliation' => 'Reconciliation',
            default => ucfirst(str_replace('_', ' ', $this->entry_type)),
        };
    }

    // Static factory methods for creating entries

    public static function recordDebit(
        string $accountType,
        int $accountId,
        float $amount,
        float $newBalance,
        string $entryType,
        string $referenceType,
        int $referenceId,
        ?string $description = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'account_type' => $accountType,
            'account_id' => $accountId,
            'debit' => $amount,
            'credit' => 0,
            'balance' => $newBalance,
            'entry_type' => $entryType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    public static function recordCredit(
        string $accountType,
        int $accountId,
        float $amount,
        float $newBalance,
        string $entryType,
        string $referenceType,
        int $referenceId,
        ?string $description = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'account_type' => $accountType,
            'account_id' => $accountId,
            'debit' => 0,
            'credit' => $amount,
            'balance' => $newBalance,
            'entry_type' => $entryType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Record a double-entry transaction (debit one account, credit another)
     */
    public static function recordTransfer(
        string $sourceType,
        int $sourceId,
        float $sourceNewBalance,
        string $destType,
        int $destId,
        float $destNewBalance,
        float $amount,
        string $entryType,
        string $referenceType,
        int $referenceId,
        ?string $description = null
    ): array {
        $debitEntry = self::recordDebit(
            $sourceType,
            $sourceId,
            $amount,
            $sourceNewBalance,
            $entryType,
            $referenceType,
            $referenceId,
            $description
        );

        $creditEntry = self::recordCredit(
            $destType,
            $destId,
            $amount,
            $destNewBalance,
            $entryType,
            $referenceType,
            $referenceId,
            $description
        );

        return [$debitEntry, $creditEntry];
    }
}
