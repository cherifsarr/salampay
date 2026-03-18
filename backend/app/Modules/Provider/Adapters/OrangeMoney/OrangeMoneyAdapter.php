<?php

namespace App\Modules\Provider\Adapters\OrangeMoney;

use App\Modules\Provider\Contracts\PaymentProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Orange Money Senegal API Adapter
 *
 * Documentation: https://developer.orange.com/apis/om-webpay-sn
 *
 * Orange Money uses OAuth 2.0 for authentication and a separate
 * merchant key for transaction signing.
 */
class OrangeMoneyAdapter implements PaymentProviderInterface
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $merchantKey;
    protected string $authToken;

    public function __construct(array $config = [])
    {
        $this->baseUrl = $config['base_url'] ?? config('services.orange_money.base_url', 'https://api.orange.com');
        $this->clientId = $config['client_id'] ?? config('services.orange_money.client_id');
        $this->clientSecret = $config['client_secret'] ?? config('services.orange_money.client_secret');
        $this->merchantKey = $config['merchant_key'] ?? config('services.orange_money.merchant_key');
    }

    public function getIdentifier(): string
    {
        return 'orange_money';
    }

    public function getName(): string
    {
        return 'Orange Money';
    }

    public function isAvailable(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret) && !empty($this->merchantKey);
    }

    /**
     * Get OAuth access token (cached for 55 minutes)
     */
    protected function getAccessToken(): ?string
    {
        $cacheKey = "om_access_token_{$this->clientId}";

        return Cache::remember($cacheKey, 3300, function () {
            try {
                $response = Http::asForm()
                    ->withBasicAuth($this->clientId, $this->clientSecret)
                    ->post($this->baseUrl . '/oauth/v3/token', [
                        'grant_type' => 'client_credentials',
                    ]);

                if ($response->successful()) {
                    return $response->json()['access_token'] ?? null;
                }

                Log::error('Orange Money OAuth failed', ['response' => $response->body()]);
                return null;
            } catch (\Exception $e) {
                Log::error('Orange Money OAuth error', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    public function getBalance(): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Failed to authenticate'];
        }

        $cacheKey = "om_balance_{$this->merchantKey}";

        return Cache::remember($cacheKey, 300, function () use ($token) {
            try {
                $response = Http::withToken($token)
                    ->get($this->baseUrl . '/orange-money-webpay/sn/v1/balance');

                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'success' => true,
                        'balance' => $data['balance'] ?? $data['amount'] ?? null,
                        'currency' => $data['currency'] ?? 'XOF',
                        'raw_response' => $data,
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Failed to fetch balance: ' . $response->body(),
                ];
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        });
    }

    public function createCheckout(array $data): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Failed to authenticate'];
        }

        try {
            $orderId = $data['reference'] ?? 'SP-' . uniqid();

            $payload = [
                'merchant_key' => $this->merchantKey,
                'currency' => $data['currency'] ?? 'OUV',
                'order_id' => $orderId,
                'amount' => $data['amount'],
                'return_url' => $data['success_url'],
                'cancel_url' => $data['error_url'],
                'notif_url' => $data['webhook_url'] ?? config('app.url') . '/api/v1/webhooks/orange-money',
                'lang' => 'fr',
            ];

            $response = Http::withToken($token)
                ->post($this->baseUrl . '/orange-money-webpay/sn/v1/webpayment', $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'session_id' => $responseData['pay_token'] ?? $orderId,
                    'checkout_url' => $responseData['payment_url'] ?? null,
                    'expires_at' => now()->addMinutes(30)->toISOString(),
                    'raw_response' => $responseData,
                ];
            }

            Log::warning('Orange Money checkout failed', ['response' => $response->body()]);
            return [
                'success' => false,
                'message' => 'Checkout creation failed: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Orange Money checkout error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getCheckoutStatus(string $sessionId): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Failed to authenticate'];
        }

        try {
            $response = Http::withToken($token)
                ->get($this->baseUrl . '/orange-money-webpay/sn/v1/transactionstatus', [
                    'pay_token' => $sessionId,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $status = $this->mapOrangeMoneyStatus($data['status'] ?? 'UNKNOWN');

                return [
                    'success' => true,
                    'session_id' => $sessionId,
                    'status' => $status,
                    'payment_status' => $status,
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
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Failed to authenticate'];
        }

        try {
            $payload = [
                'merchant_key' => $this->merchantKey,
                'currency' => $data['currency'] ?? 'XOF',
                'order_id' => $data['reference'] ?? 'SP-' . uniqid(),
                'amount' => $data['amount'],
                'subscriber_msisdn' => $data['mobile'], // Format: 221XXXXXXXXX
            ];

            $response = Http::withToken($token)
                ->post($this->baseUrl . '/orange-money-webpay/sn/v1/cashout', $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'payout_id' => $responseData['txnid'] ?? $payload['order_id'],
                    'status' => $this->mapOrangeMoneyStatus($responseData['status'] ?? 'INITIATED'),
                    'raw_response' => $responseData,
                ];
            }

            return [
                'success' => false,
                'message' => 'Payout failed: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Orange Money payout error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getPayoutStatus(string $payoutId): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Failed to authenticate'];
        }

        try {
            $response = Http::withToken($token)
                ->get($this->baseUrl . '/orange-money-webpay/sn/v1/transactionstatus', [
                    'order_id' => $payoutId,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'payout_id' => $payoutId,
                    'status' => $this->mapOrangeMoneyStatus($data['status'] ?? 'UNKNOWN'),
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
        // Orange Money does not support direct refunds via API
        // Refunds must be processed manually or through customer service
        return [
            'success' => false,
            'message' => 'Orange Money refunds must be processed manually',
        ];
    }

    public function verifyWebhook(string $payload, string $signature, ?string $timestamp = null): bool
    {
        // Orange Money uses a notification token for verification
        // The token should match the one provided during merchant registration
        $data = json_decode($payload, true);
        $notifToken = $data['notif_token'] ?? '';

        // Compare with stored notification token
        $expectedToken = config('services.orange_money.notif_token');
        return hash_equals($expectedToken, $notifToken);
    }

    public function parseWebhook(string $payload): array
    {
        $data = json_decode($payload, true) ?? [];

        return [
            'event_type' => 'payment.completed',
            'transaction_id' => $data['txnid'] ?? null,
            'order_id' => $data['order_id'] ?? null,
            'status' => $this->mapOrangeMoneyStatus($data['status'] ?? 'UNKNOWN'),
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? 'XOF',
            'raw_data' => $data,
        ];
    }

    public function getSupportedCurrencies(): array
    {
        return ['XOF', 'OUV']; // OUV is Orange Money's internal unit
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
                'value' => 0.02, // 2%
            ],
            'payout' => [
                'type' => 'percentage',
                'value' => 0.015, // 1.5%
            ],
        ];
    }

    /**
     * Map Orange Money status to standard status
     */
    protected function mapOrangeMoneyStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'SUCCESS', 'SUCCESSFULL', 'COMPLETED' => 'completed',
            'PENDING', 'INITIATED', 'WAITING' => 'pending',
            'PROCESSING' => 'processing',
            'FAILED', 'ERROR', 'REFUSED' => 'failed',
            'CANCELLED', 'CANCELED', 'EXPIRED' => 'cancelled',
            default => 'pending',
        };
    }
}
