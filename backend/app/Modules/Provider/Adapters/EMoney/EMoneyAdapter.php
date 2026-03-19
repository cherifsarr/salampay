<?php

namespace App\Modules\Provider\Adapters\EMoney;

use App\Modules\Provider\Contracts\PaymentProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * E-Money Payment Adapter
 *
 * E-Money is an electronic money platform in Senegal offering
 * payment collection and disbursement services.
 */
class EMoneyAdapter implements PaymentProviderInterface
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $apiSecret;
    protected string $merchantId;

    public function __construct(array $config = [])
    {
        $this->baseUrl = $config['base_url'] ?? config('services.emoney.base_url', 'https://api.emoney.sn');
        $this->apiKey = $config['api_key'] ?? config('services.emoney.api_key');
        $this->apiSecret = $config['api_secret'] ?? config('services.emoney.api_secret');
        $this->merchantId = $config['merchant_id'] ?? config('services.emoney.merchant_id');
    }

    public function getIdentifier(): string
    {
        return 'emoney';
    }

    public function getName(): string
    {
        return 'E-Money';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey) && !empty($this->apiSecret);
    }

    public function getBalance(): array
    {
        $cacheKey = "emoney_balance_{$this->merchantId}";

        return Cache::remember($cacheKey, 300, function () {
            try {
                $timestamp = now()->timestamp;
                $nonce = bin2hex(random_bytes(16));

                $response = Http::withHeaders($this->getAuthHeaders($timestamp, $nonce))
                    ->timeout(10)
                    ->get($this->baseUrl . '/api/v2/merchant/balance');

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
                Log::error('E-Money balance check failed', ['error' => $e->getMessage()]);
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
            $reference = $data['client_reference'] ?? 'EM-' . time() . '-' . random_int(1000, 9999);
            $timestamp = now()->timestamp;
            $nonce = bin2hex(random_bytes(16));

            $payload = [
                'merchant_id' => $this->merchantId,
                'external_reference' => $reference,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'XOF',
                'customer' => [
                    'phone' => $data['customer_phone'] ?? null,
                    'name' => $data['customer_name'] ?? null,
                    'email' => $data['customer_email'] ?? null,
                ],
                'description' => $data['description'] ?? 'Payment via SalamPay',
                'webhook_url' => $data['callback_url'] ?? config('app.url') . '/api/v1/webhooks/emoney',
                'success_redirect_url' => $data['success_url'] ?? null,
                'failure_redirect_url' => $data['error_url'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ];

            $response = Http::withHeaders($this->getAuthHeaders($timestamp, $nonce))
                ->timeout(30)
                ->post($this->baseUrl . '/api/v2/payments/create', $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'session_id' => $responseData['payment_id'] ?? $responseData['id'] ?? $reference,
                    'checkout_url' => $responseData['checkout_url'] ?? $responseData['payment_url'] ?? null,
                    'ussd_code' => $responseData['ussd_string'] ?? null,
                    'expires_at' => $responseData['expires_at'] ?? null,
                    'qr_code' => $responseData['qr_code_url'] ?? null,
                    'raw_response' => $responseData,
                ];
            }

            Log::warning('E-Money checkout creation failed', ['response' => $response->body()]);
            return [
                'success' => false,
                'message' => 'Checkout creation failed: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('E-Money checkout error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getCheckoutStatus(string $sessionId): array
    {
        try {
            $timestamp = now()->timestamp;
            $nonce = bin2hex(random_bytes(16));

            $response = Http::withHeaders($this->getAuthHeaders($timestamp, $nonce))
                ->timeout(10)
                ->get($this->baseUrl . '/api/v2/payments/' . $sessionId);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'session_id' => $sessionId,
                    'status' => $this->mapStatus($data['status'] ?? 'unknown'),
                    'payment_status' => $data['payment_status'] ?? $data['status'] ?? null,
                    'amount' => $data['amount'] ?? null,
                    'paid_at' => $data['paid_at'] ?? $data['completed_at'] ?? null,
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
            $reference = $data['reference'] ?? 'EM-PO-' . time() . '-' . random_int(1000, 9999);
            $timestamp = now()->timestamp;
            $nonce = bin2hex(random_bytes(16));

            $payload = [
                'merchant_id' => $this->merchantId,
                'external_reference' => $reference,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'XOF',
                'recipient' => [
                    'phone' => $data['mobile'] ?? $data['recipient_phone'],
                    'name' => $data['name'] ?? $data['recipient_name'] ?? null,
                ],
                'description' => $data['description'] ?? 'Payout from SalamPay',
            ];

            $response = Http::withHeaders($this->getAuthHeaders($timestamp, $nonce))
                ->timeout(30)
                ->post($this->baseUrl . '/api/v2/payouts/create', $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'payout_id' => $responseData['payout_id'] ?? $responseData['id'] ?? $reference,
                    'status' => $this->mapStatus($responseData['status'] ?? 'pending'),
                    'raw_response' => $responseData,
                ];
            }

            return [
                'success' => false,
                'message' => 'Payout failed: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('E-Money payout error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getPayoutStatus(string $payoutId): array
    {
        try {
            $timestamp = now()->timestamp;
            $nonce = bin2hex(random_bytes(16));

            $response = Http::withHeaders($this->getAuthHeaders($timestamp, $nonce))
                ->timeout(10)
                ->get($this->baseUrl . '/api/v2/payouts/' . $payoutId);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'payout_id' => $payoutId,
                    'status' => $this->mapStatus($data['status'] ?? 'unknown'),
                    'completed_at' => $data['completed_at'] ?? null,
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
            $timestamp = now()->timestamp;
            $nonce = bin2hex(random_bytes(16));

            $payload = [
                'payment_id' => $transactionId,
                'reason' => 'Refund requested via SalamPay',
            ];

            if ($amount !== null) {
                $payload['amount'] = $amount;
            }

            $response = Http::withHeaders($this->getAuthHeaders($timestamp, $nonce))
                ->timeout(30)
                ->post($this->baseUrl . '/api/v2/payments/' . $transactionId . '/refund', $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'refund_id' => $data['refund_id'] ?? $data['id'] ?? $transactionId,
                    'status' => $data['status'] ?? 'processing',
                    'amount' => $data['refund_amount'] ?? $amount,
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
        $expectedSignature = hash_hmac('sha256', $payload, $this->apiSecret);
        return hash_equals($expectedSignature, $signature);
    }

    public function parseWebhook(string $payload): array
    {
        $data = json_decode($payload, true) ?? [];

        return [
            'event_type' => $data['event'] ?? $data['type'] ?? 'payment',
            'transaction_id' => $data['payment_id'] ?? $data['id'] ?? null,
            'external_reference' => $data['external_reference'] ?? null,
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
            'max_amount' => 2000000,
            'daily_limit' => 5000000,
        ];
    }

    public function getFees(): array
    {
        return [
            'checkout' => [
                'type' => 'percentage',
                'value' => 0.018, // 1.8%
                'min' => 50, // Minimum 50 XOF
            ],
            'payout' => [
                'type' => 'percentage',
                'value' => 0.01, // 1%
                'min' => 25, // Minimum 25 XOF
            ],
        ];
    }

    // Helper methods

    protected function getAuthHeaders(int $timestamp, string $nonce): array
    {
        $signature = $this->generateAuthSignature($timestamp, $nonce);

        return [
            'X-API-Key' => $this->apiKey,
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    protected function generateAuthSignature(int $timestamp, string $nonce): string
    {
        $stringToSign = $this->apiKey . $timestamp . $nonce;
        return hash_hmac('sha256', $stringToSign, $this->apiSecret);
    }

    protected function mapStatus(string $providerStatus): string
    {
        return match (strtolower($providerStatus)) {
            'success', 'successful', 'completed', 'paid', 'confirmed' => 'completed',
            'pending', 'initiated', 'processing', 'waiting' => 'pending',
            'failed', 'error', 'rejected', 'declined', 'failure' => 'failed',
            'cancelled', 'canceled', 'expired', 'timeout', 'abandoned' => 'cancelled',
            'refunded', 'reversed' => 'refunded',
            default => $providerStatus,
        };
    }
}
