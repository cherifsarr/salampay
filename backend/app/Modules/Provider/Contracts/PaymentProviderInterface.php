<?php

namespace App\Modules\Provider\Contracts;

interface PaymentProviderInterface
{
    /**
     * Get the provider identifier
     */
    public function getIdentifier(): string;

    /**
     * Get the provider display name
     */
    public function getName(): string;

    /**
     * Check if provider is available/configured
     */
    public function isAvailable(): bool;

    /**
     * Get current balance from provider
     */
    public function getBalance(): array;

    /**
     * Create a checkout/collection session
     */
    public function createCheckout(array $data): array;

    /**
     * Get checkout session status
     */
    public function getCheckoutStatus(string $sessionId): array;

    /**
     * Process a payout/withdrawal
     */
    public function payout(array $data): array;

    /**
     * Get payout status
     */
    public function getPayoutStatus(string $payoutId): array;

    /**
     * Refund a transaction
     */
    public function refund(string $transactionId, ?int $amount = null): array;

    /**
     * Verify webhook signature
     */
    public function verifyWebhook(string $payload, string $signature, ?string $timestamp = null): bool;

    /**
     * Parse webhook payload
     */
    public function parseWebhook(string $payload): array;

    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies(): array;

    /**
     * Get transaction limits
     */
    public function getLimits(): array;

    /**
     * Get fee structure
     */
    public function getFees(): array;
}
