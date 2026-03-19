<?php

namespace App\Modules\Provider\Adapters\Wizall;

use App\Modules\Provider\Contracts\PaymentProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Wizall Payment Adapter
 *
 * Wizall is a mobile money service in Senegal offering payment collection,
 * money transfers, and financial services.
 */
class WizallAdapter implements PaymentProviderInterface
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $secretKey;
    protected string $merchantCode;

    public function __construct(array $config = [])
    {
        $this->baseUrl = $config['base_url'] ?? config('services.wizall.base_url', 'https://api.wizall.com');
        $this->apiKey = $config['api_key'] ?? config('services.wizall.api_key');
        $this->secretKey = $config['secret'] ?? config('services.wizall.secret');
        $this->merchantCode = $config['merchant_code'] ?? config('services.wizall.merchant_code');
    }

    public function getIdentifier(): string
    {
        return 'wizall';
    }

    public function getName(): string
    {
        return 'Wizall';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey) && !empty($this->secretKey);
    }

    public function getBalance(): array
    {
        $cacheKey = "wizall_balance_{$this->merchantCode}";

        return Cache::remember($cacheKey, 300, function () {
            try {
                $timestamp = time();
                $signature = $this->generateSignature(['timestamp' => $timestamp]);

                $response = Http::withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'X-Signature' => $signature,
                    'X-Timestamp' => $timestamp,
                ])
                    ->timeout(10)
                    ->get($this->baseUrl . '/v1/accounts/balance');

                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'success' => true,
                        'balance' => $data['balance'] ?? $data['available'] ?? null,
                        'currency' => $data['currency'] ?? 'XOF',
                        'raw_response' => $data,
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Failed to fetch balance: ' . $response->body(),
                ];
            } catch (\Exception $e) {
                Log::error('Wizall balance check failed', ['error' => $e->getMessage()]);
                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        });
    }

    public function createCheckout(array $data): array
    {
        try {
            $reference = $data['client_reference'] ?? 'WZ-' . time() . '-' . random_int(1000, 9999);
            $timestamp = time();

            $payload = [
                'merchant_code' => $this->merchantCode,
                'reference' => $reference,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'XOF',
                'customer_msisdn' => $data['customer_phone'] ?? null,
                'description' => $data['description'] ?? 'Payment via SalamPay',
                'callback_url' => $data['callback_url'] ?? config('app.url') . '/api/v1/webhooks/wizall',
                'success_url' => $data['success_url'] ?? null,
                'failure_url' => $data['error_url'] ?? null,
                'timestamp' => $timestamp,
            ];

            $payload['signature'] = $this->generateSignature($payload);

            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post($this->baseUrl . '/v1/collections/initiate', $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'session_id' => $responseData['transaction_id'] ?? $responseData['collection_id'] ?? $reference,
                    'checkout_url' => $responseData['payment_url'] ?? $responseData['redirect_url'] ?? null,
                    'ussd_code' => $responseData['ussd_string'] ?? null,
                    'expires_at' => $responseData['expires_at'] ?? null,
                    'raw_response' => $responseData,
                ];
            }

            Log::warning('Wizall checkout creation failed', ['response' => $response->body()]);
            return [
                'success' => false,
                'message' => 'Checkout creation failed: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Wizall checkout error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getCheckoutStatus(string $sessionId): array
    {
        try {
            $timestamp = time();
            $signature = $this->generateSignature([
                'transaction_id' => $sessionId,
                'timestamp' => $timestamp,
            ]);

            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
            ])
                ->timeout(10)
                ->get($this->baseUrl . '/v1/collections/' . $sessionId);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'session_id' => $sessionId,
                    'status' => $this->mapStatus($data['status'] ?? 'unknown'),
                    'payment_status' => $data['payment_status'] ?? $data['status'] ?? null,
                    'amount' => $data['amount'] ?? null,
                    'raw_response' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to get checkout status: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function payout(array $data): array
    {
        try {
            $reference = $data['reference'] ?? 'WZ-PO-' . time() . '-' . random_int(1000, 9999);
            $timestamp = time();

            $payload = [
                'merchant_code' => $this->merchantCode,
                'reference' => $reference,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'XOF',
                'recipient_msisdn' => $data['mobile'] ?? $data['recipient_phone'],
                'recipient_name' => $data['name'] ?? null,
                'description' => $data['description'] ?? 'Payout from SalamPay',
                'timestamp' => $timestamp,
            ];

            $payload['signature'] = $this->generateSignature($payload);

            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post($this->baseUrl . '/v1/disbursements/initiate', $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'payout_id' => $responseData['transaction_id'] ?? $responseData['disbursement_id'] ?? $reference,
                    'status' => $this->mapStatus($responseData['status'] ?? 'pending'),
                    'raw_response' => $responseData,
                ];
            }

            return [
                'success' => false,
                'message' => 'Payout failed: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Wizall payout error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getPayoutStatus(string $payoutId): array
    {
        try {
            $timestamp = time();
            $signature = $this->generateSignature([
                'transaction_id' => $payoutId,
                'timestamp' => $timestamp,
            ]);

            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
            ])
                ->timeout(10)
                ->get($this->baseUrl . '/v1/disbursements/' . $payoutId);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'payout_id' => $payoutId,
                    'status' => $this->mapStatus($data['status'] ?? 'unknown'),
                    'raw_response' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to get payout status: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function refund(string $transactionId, ?int $amount = null): array
    {
        try {
            $timestamp = time();
            $payload = [
                'merchant_code' => $this->merchantCode,
                'original_transaction_id' => $transactionId,
                'timestamp' => $timestamp,
            ];

            if ($amount !== null) {
                $payload['amount'] = $amount;
            }

            $payload['signature'] = $this->generateSignature($payload);

            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post($this->baseUrl . '/v1/refunds', $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'refund_id' => $data['refund_id'] ?? $data['transaction_id'] ?? $transactionId,
                    'status' => $data['status'] ?? 'processing',
                    'raw_response' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => 'Refund failed: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function verifyWebhook(string $payload, string $signature, ?string $timestamp = null): bool
    {
        $data = json_decode($payload, true) ?? [];
        if ($timestamp) {
            $data['timestamp'] = $timestamp;
        }
        $expectedSignature = $this->generateSignature($data);
        return hash_equals($expectedSignature, $signature);
    }

    public function parseWebhook(string $payload): array
    {
        $data = json_decode($payload, true) ?? [];

        return [
            'event_type' => $data['event_type'] ?? $data['type'] ?? 'collection',
            'transaction_id' => $data['transaction_id'] ?? $data['reference'] ?? null,
            'status' => $this->mapStatus($data['status'] ?? 'unknown'),
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? 'XOF',
            'raw_data' => $data,
        ];
    }

    public function getSupportedCurrencies(): array
    {
        return ['XOF'];
    }

    public function getLimits(): array
    {
        return [
            'min_amount' => 100,
            'max_amount' => 500000,
            'daily_limit' => 2000000,
        ];
    }

    public function getFees(): array
    {
        return [
            'checkout' => [
                'type' => 'percentage',
                'value' => 0.02, // 2%
            ],
            'payout' => [
                'type' => 'percentage',
                'value' => 0.015, // 1.5%
            ],
        ];
    }

    // Helper methods

    protected function generateSignature(array $data): string
    {
        // Sort alphabetically
        ksort($data);

        // Remove existing signature
        unset($data['signature']);

        // Create string to sign
        $stringToSign = '';
        foreach ($data as $key => $value) {
            if ($value !== null && $value !== '') {
                $stringToSign .= $key . $value;
            }
        }

        return hash_hmac('sha256', $stringToSign, $this->secretKey);
    }

    protected function mapStatus(string $providerStatus): string
    {
        return match (strtolower($providerStatus)) {
            'success', 'successful', 'completed', 'paid', 'approved' => 'completed',
            'pending', 'initiated', 'processing', 'awaiting' => 'pending',
            'failed', 'error', 'rejected', 'declined' => 'failed',
            'cancelled', 'canceled', 'expired', 'timeout' => 'cancelled',
            default => $providerStatus,
        };
    }
}
