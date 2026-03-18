<?php

namespace App\Modules\Provider\Adapters\Wave;

use App\Modules\Provider\Contracts\PaymentProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WaveAdapter implements PaymentProviderInterface
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $webhookSecret;

    public function __construct(array $config = [])
    {
        $this->baseUrl = $config['base_url'] ?? config('services.wave.base_url', 'https://api.wave.com');
        $this->apiKey = $config['api_key'] ?? config('services.wave.api_key');
        $this->webhookSecret = $config['webhook_secret'] ?? config('services.wave.webhook_secret');
    }

    public function getIdentifier(): string
    {
        return 'wave';
    }

    public function getName(): string
    {
        return 'Wave';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey) && !empty($this->baseUrl);
    }

    public function getBalance(): array
    {
        $cacheKey = "wave_balance_{$this->apiKey}";

        return Cache::remember($cacheKey, 300, function () {
            try {
                $response = Http::withToken($this->apiKey)
                    ->timeout(10)
                    ->get($this->baseUrl . '/v1/balance');

                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'success' => true,
                        'balance' => $data['amount'] ?? null,
                        'currency' => $data['currency'] ?? 'XOF',
                        'raw_response' => $data,
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Failed to fetch balance: ' . $response->body(),
                ];
            } catch (\Exception $e) {
                Log::error('Wave balance check failed', ['error' => $e->getMessage()]);
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
            $payload = [
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'XOF',
                'error_url' => $data['error_url'],
                'success_url' => $data['success_url'],
            ];

            if (!empty($data['client_reference'])) {
                $payload['client_reference'] = $data['client_reference'];
            }

            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post($this->baseUrl . '/v1/checkout/sessions', $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'session_id' => $data['id'],
                    'checkout_url' => $data['wave_launch_url'],
                    'expires_at' => $data['when_expires'] ?? null,
                    'raw_response' => $data,
                ];
            }

            Log::warning('Wave checkout creation failed', ['response' => $response->body()]);
            return [
                'success' => false,
                'message' => 'Checkout creation failed: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Wave checkout error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getCheckoutStatus(string $sessionId): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(10)
                ->get($this->baseUrl . '/v1/checkout/sessions/' . $sessionId);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'session_id' => $data['id'],
                    'status' => $data['checkout_status'],
                    'payment_status' => $data['payment_status'] ?? null,
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
            $payload = [
                'receive_amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'XOF',
                'mobile' => $data['mobile'],
                'name' => $data['name'] ?? null,
                'client_reference' => $data['reference'] ?? uniqid('SP-'),
            ];

            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post($this->baseUrl . '/v1/payout', $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'payout_id' => $data['id'],
                    'status' => $data['status'],
                    'raw_response' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => 'Payout failed: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Wave payout error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getPayoutStatus(string $payoutId): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(10)
                ->get($this->baseUrl . '/v1/payout/' . $payoutId);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'payout_id' => $data['id'],
                    'status' => $data['status'],
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
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post($this->baseUrl . '/v1/checkout/sessions/' . $transactionId . '/refund');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'refund_id' => $response->json()['id'] ?? $transactionId,
                    'raw_response' => $response->json(),
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
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }

    public function parseWebhook(string $payload): array
    {
        return json_decode($payload, true) ?? [];
    }

    public function getSupportedCurrencies(): array
    {
        return ['XOF'];
    }

    public function getLimits(): array
    {
        return [
            'min_amount' => 100,
            'max_amount' => 1500000,
            'daily_limit' => 5000000,
        ];
    }

    public function getFees(): array
    {
        return [
            'checkout' => [
                'type' => 'percentage',
                'value' => 0.01, // 1%
            ],
            'payout' => [
                'type' => 'percentage',
                'value' => 0.01,
            ],
        ];
    }
}
