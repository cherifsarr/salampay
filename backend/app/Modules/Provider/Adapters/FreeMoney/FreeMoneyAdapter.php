<?php

namespace App\Modules\Provider\Adapters\FreeMoney;

use App\Modules\Provider\Contracts\PaymentProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Free Money (Free/Tigo) Payment Adapter
 *
 * Free Money is the mobile money service operated by Free (formerly Tigo) in Senegal.
 * It provides payment collection and disbursement services.
 */
class FreeMoneyAdapter implements PaymentProviderInterface
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $merchantId;
    protected string $secretKey;

    public function __construct(array $config = [])
    {
        $this->baseUrl = $config['base_url'] ?? config('services.free_money.base_url', 'https://api.freemoney.sn');
        $this->apiKey = $config['api_key'] ?? config('services.free_money.api_key');
        $this->merchantId = $config['merchant_id'] ?? config('services.free_money.merchant_id');
        $this->secretKey = $config['secret_key'] ?? config('services.free_money.secret_key');
    }

    public function getIdentifier(): string
    {
        return 'free_money';
    }

    public function getName(): string
    {
        return 'Free Money';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey) && !empty($this->merchantId);
    }

    public function getBalance(): array
    {
        $cacheKey = "free_money_balance_{$this->merchantId}";

        return Cache::remember($cacheKey, 300, function () {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'X-Merchant-Id' => $this->merchantId,
                ])
                    ->timeout(10)
                    ->get($this->baseUrl . '/api/v1/merchant/balance');

                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'success' => true,
                        'balance' => $data['balance'] ?? $data['available_balance'] ?? null,
                        'currency' => $data['currency'] ?? 'XOF',
                        'raw_response' => $data,
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Failed to fetch balance: ' . $response->body(),
                ];
            } catch (\Exception $e) {
                Log::error('Free Money balance check failed', ['error' => $e->getMessage()]);
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
            $transactionId = $data['client_reference'] ?? 'FM-' . time() . '-' . random_int(1000, 9999);

            $payload = [
                'merchant_id' => $this->merchantId,
                'transaction_id' => $transactionId,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'XOF',
                'customer_phone' => $data['customer_phone'] ?? null,
                'description' => $data['description'] ?? 'Payment via SalamPay',
                'callback_url' => $data['callback_url'] ?? config('app.url') . '/api/v1/webhooks/free-money',
                'return_url' => $data['success_url'] ?? $data['return_url'] ?? null,
                'cancel_url' => $data['error_url'] ?? $data['cancel_url'] ?? null,
            ];

            // Generate signature
            $payload['signature'] = $this->generateSignature($payload);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post($this->baseUrl . '/api/v1/payments/initiate', $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'session_id' => $responseData['payment_id'] ?? $responseData['transaction_id'] ?? $transactionId,
                    'checkout_url' => $responseData['payment_url'] ?? $responseData['redirect_url'] ?? null,
                    'ussd_code' => $responseData['ussd_code'] ?? null,
                    'expires_at' => $responseData['expires_at'] ?? null,
                    'raw_response' => $responseData,
                ];
            }

            Log::warning('Free Money checkout creation failed', ['response' => $response->body()]);
            return [
                'success' => false,
                'message' => 'Checkout creation failed: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Free Money checkout error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getCheckoutStatus(string $sessionId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'X-Merchant-Id' => $this->merchantId,
            ])
                ->timeout(10)
                ->get($this->baseUrl . '/api/v1/payments/' . $sessionId . '/status');

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
            $reference = $data['reference'] ?? 'FM-PO-' . time() . '-' . random_int(1000, 9999);

            $payload = [
                'merchant_id' => $this->merchantId,
                'reference' => $reference,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'XOF',
                'recipient_phone' => $data['mobile'] ?? $data['recipient_phone'],
                'recipient_name' => $data['name'] ?? null,
                'description' => $data['description'] ?? 'Payout from SalamPay',
            ];

            $payload['signature'] = $this->generateSignature($payload);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post($this->baseUrl . '/api/v1/payouts/initiate', $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'payout_id' => $responseData['payout_id'] ?? $responseData['transaction_id'] ?? $reference,
                    'status' => $this->mapStatus($responseData['status'] ?? 'pending'),
                    'raw_response' => $responseData,
                ];
            }

            return [
                'success' => false,
                'message' => 'Payout failed: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Free Money payout error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getPayoutStatus(string $payoutId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'X-Merchant-Id' => $this->merchantId,
            ])
                ->timeout(10)
                ->get($this->baseUrl . '/api/v1/payouts/' . $payoutId . '/status');

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
            $payload = [
                'transaction_id' => $transactionId,
                'merchant_id' => $this->merchantId,
            ];

            if ($amount !== null) {
                $payload['amount'] = $amount;
            }

            $payload['signature'] = $this->generateSignature($payload);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post($this->baseUrl . '/api/v1/payments/' . $transactionId . '/refund', $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'refund_id' => $data['refund_id'] ?? $transactionId,
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
        $expectedSignature = $this->generateSignature($data);
        return hash_equals($expectedSignature, $signature);
    }

    public function parseWebhook(string $payload): array
    {
        $data = json_decode($payload, true) ?? [];

        return [
            'event_type' => $data['event'] ?? $data['type'] ?? 'payment',
            'transaction_id' => $data['transaction_id'] ?? $data['payment_id'] ?? null,
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
            'max_amount' => 1000000,
            'daily_limit' => 3000000,
        ];
    }

    public function getFees(): array
    {
        return [
            'checkout' => [
                'type' => 'fixed',
                'value' => 50, // 50 XOF fixed fee
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
        // Sort the data alphabetically by key
        ksort($data);

        // Remove any existing signature
        unset($data['signature']);

        // Create signature string
        $signatureString = '';
        foreach ($data as $key => $value) {
            if ($value !== null && $value !== '') {
                $signatureString .= $key . '=' . $value . '&';
            }
        }
        $signatureString = rtrim($signatureString, '&');
        $signatureString .= $this->secretKey;

        return hash('sha256', $signatureString);
    }

    protected function mapStatus(string $providerStatus): string
    {
        return match (strtolower($providerStatus)) {
            'success', 'successful', 'completed', 'paid' => 'completed',
            'pending', 'initiated', 'processing' => 'pending',
            'failed', 'error', 'rejected' => 'failed',
            'cancelled', 'canceled', 'expired' => 'cancelled',
            default => $providerStatus,
        };
    }
}
