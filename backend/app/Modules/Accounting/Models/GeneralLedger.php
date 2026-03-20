<?php

namespace App\Modules\Accounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GeneralLedger extends Model
{
    protected $table = 'general_ledger';

    protected $fillable = [
        'uuid',
        'journal_id',
        'entry_date',
        'account_code',
        'debit',
        'credit',
        'running_balance',
        'reference_type',
        'reference_id',
        'external_reference',
        'description',
        'metadata',
        'status',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'running_balance' => 'decimal:2',
        'metadata' => 'array',
        'posted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
        });
    }

    // Relationships

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccounts::class, 'account_code', 'code');
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    // Scopes

    public function scopeForAccount($query, string $accountCode)
    {
        return $query->where('account_code', $accountCode);
    }

    public function scopeForJournal($query, string $journalId)
    {
        return $query->where('journal_id', $journalId);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where('entry_date', $date);
    }

    public function scopeForDateRange($query, string $start, string $end)
    {
        return $query->whereBetween('entry_date', [$start, $end]);
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    public function scopeDebits($query)
    {
        return $query->where('debit', '>', 0);
    }

    public function scopeCredits($query)
    {
        return $query->where('credit', '>', 0);
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

    /**
     * Get all entries for a journal (grouped transaction)
     */
    public static function getJournalEntries(string $journalId): array
    {
        $entries = self::forJournal($journalId)
            ->orderBy('id')
            ->get();

        $totalDebits = $entries->sum('debit');
        $totalCredits = $entries->sum('credit');

        return [
            'journal_id' => $journalId,
            'entries' => $entries->toArray(),
            'total_debits' => (float) $totalDebits,
            'total_credits' => (float) $totalCredits,
            'is_balanced' => abs($totalDebits - $totalCredits) < 0.01,
        ];
    }

    /**
     * Get account balance as of a specific date
     */
    public static function getAccountBalance(string $accountCode, ?string $asOfDate = null): float
    {
        $query = self::forAccount($accountCode)->posted();

        if ($asOfDate) {
            $query->where('entry_date', '<=', $asOfDate);
        }

        $lastEntry = $query->orderBy('id', 'desc')->first();

        return $lastEntry ? (float) $lastEntry->running_balance : 0;
    }

    /**
     * Get trial balance (all accounts with balances)
     */
    public static function getTrialBalance(?string $asOfDate = null): array
    {
        $accounts = ChartOfAccounts::where('is_active', true)->get();
        $balances = [];
        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($accounts as $account) {
            $balance = self::getAccountBalance($account->code, $asOfDate);

            // Debit-normal accounts: Assets, Expenses
            // Credit-normal accounts: Liabilities, Equity, Revenue
            $isDebitNormal = in_array($account->type, ['asset', 'expense']);

            if ($balance != 0) {
                $balances[] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'debit' => $isDebitNormal && $balance > 0 ? abs($balance) : 0,
                    'credit' => !$isDebitNormal || $balance < 0 ? abs($balance) : 0,
                ];

                if ($isDebitNormal && $balance > 0) {
                    $totalDebits += abs($balance);
                } else {
                    $totalCredits += abs($balance);
                }
            }
        }

        return [
            'as_of' => $asOfDate ?? today()->format('Y-m-d'),
            'accounts' => $balances,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'is_balanced' => abs($totalDebits - $totalCredits) < 0.01,
        ];
    }
}
