<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'merchant_id',
        'name',
        'key_prefix',
        'key_hash',
        'signing_secret',
        'is_test_mode',
        'allowed_ips',
        'scopes',
        'rate_limit_per_minute',
        'webhook_url',
        'webhook_secret',
        'webhook_events',
        'last_used_at',
        'expires_at',
        'revoked_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_test_mode' => 'boolean',
            'allowed_ips' => 'array',
            'scopes' => 'array',
            'webhook_events' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ApiAuditLog::class);
    }

    public static function generate(Merchant $merchant, string $name, bool $isTestMode = false): array
    {
        $prefix = $isTestMode ? 'spk_test_' : 'spk_live_';
        $key = $prefix . Str::random(32);
        $signingSecret = 'sps_' . Str::random(32);

        $apiKey = self::create([
            'merchant_id' => $merchant->id,
            'name' => $name,
            'key_prefix' => $prefix,
            'key_hash' => hash('sha256', $key),
            'signing_secret' => $signingSecret,
            'is_test_mode' => $isTestMode,
            'status' => 'active',
        ]);

        return [
            'api_key' => $apiKey,
            'key' => $key, // Only returned once
            'signing_secret' => $signingSecret,
        ];
    }

    public static function findByKey(string $key): ?self
    {
        $hash = hash('sha256', $key);
        return self::where('key_hash', $hash)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->first();
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at > now());
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at <= now();
    }

    public function hasScope(string $scope): bool
    {
        if (empty($this->scopes)) {
            return true; // No scopes = all access
        }
        return in_array($scope, $this->scopes);
    }

    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->allowed_ips)) {
            return true; // No IP restrictions
        }
        return in_array($ip, $this->allowed_ips);
    }

    public function revoke(): void
    {
        $this->revoked_at = now();
        $this->status = 'revoked';
        $this->save();
    }

    public function touch(): void
    {
        $this->last_used_at = now();
        $this->save();
    }

    public function signPayload(string $payload): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $this->signing_secret);
        return "t={$timestamp},v1={$signature}";
    }

    public function verifySignature(string $payload, string $signature, int $timestamp): bool
    {
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $this->signing_secret);
        return hash_equals($expected, $signature);
    }
}
