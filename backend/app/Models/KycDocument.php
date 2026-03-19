<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycDocument extends Model
{
    protected $fillable = [
        'user_id',
        'document_type',
        'document_url',
        'document_number',
        'expiry_date',
        'verification_status',
        'rejection_reason',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    // Accessor for consistent API naming
    public function getStatusAttribute(): string
    {
        return $this->verification_status;
    }

    public function getReviewedAtAttribute(): ?\DateTime
    {
        return $this->verified_at;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->verification_status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }

    public function approve(int $reviewerId): void
    {
        $this->update([
            'verification_status' => 'approved',
            'verified_by' => $reviewerId,
            'verified_at' => now(),
        ]);
    }

    public function reject(int $reviewerId, string $reason): void
    {
        $this->update([
            'verification_status' => 'rejected',
            'rejection_reason' => $reason,
            'verified_by' => $reviewerId,
            'verified_at' => now(),
        ]);
    }
}
