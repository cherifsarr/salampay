<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'uuid',
        'invoice_number',
        'merchant_id',
        'customer_user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'issue_date',
        'due_date',
        'paid_at',
        'payment_link_id',
        'transaction_id',
        'line_items',
        'notes',
        'terms',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'issue_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'line_items' => 'array',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function paymentLink(): BelongsTo
    {
        return $this->belongsTo(PaymentLink::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->due_date->isPast();
    }

    public function markAsPaid(Transaction $transaction): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'transaction_id' => $transaction->id,
        ]);
    }
}
