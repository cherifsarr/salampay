<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\PaymentLink;
use App\Models\QrCode;
use App\Models\Transaction;
use App\Modules\Provider\ProviderFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Public Checkout Controller
 *
 * Handles guest payments without requiring a SalamPay account.
 * Customers can pay directly using their preferred mobile money provider.
 */
class CheckoutController extends Controller
{
    /**
     * Supported payment providers for guest checkout
     */
    private const PROVIDERS = [
        'wave' => [
            'name' => 'Wave',
            'logo' => 'wave.png',
            'enabled' => true,
        ],
        'orange_money' => [
            'name' => 'Orange Money',
            'logo' => 'orange_money.png',
            'enabled' => true,
        ],
        'free_money' => [
            'name' => 'Free Money',
            'logo' => 'free_money.png',
            'enabled' => true,
        ],
        'wizall' => [
            'name' => 'Wizall',
            'logo' => 'wizall.png',
            'enabled' => true,
        ],
        'emoney' => [
            'name' => 'E-Money',
            'logo' => 'emoney.png',
            'enabled' => true,
        ],
    ];

    public function __construct(
        private ProviderFactory $providerFactory
    ) {}

    /**
     * Get available payment providers
     */
    public function providers(): JsonResponse
    {
        $providers = collect(self::PROVIDERS)
            ->filter(fn($p) => $p['enabled'])
            ->map(fn($p, $code) => [
                'code' => $code,
                'name' => $p['name'],
                'logo' => $p['logo'],
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $providers,
        ]);
    }

    /**
     * Resolve a checkout session by ID
     * Returns payment details without requiring authentication
     */
    public function resolve(string $id): JsonResponse
    {
        $transaction = Transaction::with('merchant:id,business_name,logo_url')
            ->where('uuid', $id)
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Checkout session not found or expired',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $transaction->uuid,
                'merchant' => [
                    'name' => $transaction->merchant?->business_name ?? 'SalamPay Merchant',
                    'logo' => $transaction->merchant?->logo_url,
                ],
                'amount' => (float) $transaction->amount,
                'currency' => $transaction->currency,
                'description' => $transaction->description,
                'status' => $transaction->status,
                'providers' => collect(self::PROVIDERS)
                    ->filter(fn($p) => $p['enabled'])
                    ->map(fn($p, $code) => [
                        'code' => $code,
                        'name' => $p['name'],
                    ])
                    ->values(),
                'created_at' => $transaction->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Pay a checkout session using selected provider
     * No SalamPay account required - direct provider payment
     */
    public function pay(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['required', 'string', 'in:wave,orange_money,free_money,wizall,emoney'],
            'phone' => ['sometimes', 'string', 'regex:/^\+221[0-9]{9}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $transaction = Transaction::where('uuid', $id)
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Checkout session not found or expired',
            ], 404);
        }

        $providerCode = $request->provider;

        try {
            // Get provider adapter
            $provider = $this->providerFactory->make($providerCode);

            // Get callback URLs from transaction metadata
            $metadata = $transaction->metadata ?? [];
            $successUrl = $metadata['success_url'] ?? config('app.url') . '/checkout/success';
            $errorUrl = $metadata['error_url'] ?? config('app.url') . '/checkout/error';

            // Create checkout with selected provider
            $checkoutData = $provider->createCheckout([
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'client_reference' => $transaction->reference,
                'success_url' => $successUrl,
                'error_url' => $errorUrl,
                'phone' => $request->phone,
            ]);

            // Update transaction with provider info
            $transaction->update([
                'provider' => $providerCode,
                'external_reference' => $checkoutData['id'] ?? $checkoutData['checkout_id'] ?? null,
                'status' => 'processing',
                'metadata' => array_merge($metadata, [
                    'customer_phone' => $request->phone,
                    'provider_response' => $checkoutData,
                ]),
            ]);

            // Get redirect URL based on provider
            $redirectUrl = $this->getProviderRedirectUrl($providerCode, $checkoutData);

            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $transaction->uuid,
                    'provider' => $providerCode,
                    'status' => 'processing',
                    'redirect_url' => $redirectUrl,
                    'ussd_code' => $checkoutData['ussd_code'] ?? null,
                    'expires_at' => $checkoutData['when_expires'] ?? $checkoutData['expires_at'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve a QR code by data
     * Returns merchant and payment info for QR scan
     */
    public function resolveQr(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'qr_data' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid QR code',
            ], 422);
        }

        $qrData = $request->qr_data;

        // Parse QR data format: SP:{uuid} or SP:{uuid}:{amount}
        if (!preg_match('/^SP:([a-f0-9-]+)(?::(\d+(?:\.\d{2})?))?$/i', $qrData, $matches)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid QR code format',
            ], 400);
        }

        $uuid = $matches[1];
        $amount = isset($matches[2]) ? (float) $matches[2] : null;

        $qrCode = QrCode::with('merchant:id,business_name,logo_url')
            ->where('uuid', $uuid)
            ->where('status', 'active')
            ->first();

        if (!$qrCode) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not found or disabled',
            ], 404);
        }

        // Check expiration
        if ($qrCode->valid_until && $qrCode->valid_until->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'QR code has expired',
            ], 410);
        }

        // Increment scan count
        $qrCode->increment('scan_count');

        // Determine amount (from QR or dynamic)
        $paymentAmount = $qrCode->amount ?? $amount;

        return response()->json([
            'success' => true,
            'data' => [
                'qr_id' => $qrCode->uuid,
                'type' => $qrCode->qr_type,
                'merchant' => [
                    'id' => $qrCode->merchant?->id,
                    'name' => $qrCode->merchant?->business_name ?? 'SalamPay Merchant',
                    'logo' => $qrCode->merchant?->logo_url,
                ],
                'amount' => $paymentAmount,
                'amount_editable' => $qrCode->qr_type === 'static' && !$qrCode->amount,
                'currency' => 'XOF',
                'description' => $qrCode->description,
                'providers' => collect(self::PROVIDERS)
                    ->filter(fn($p) => $p['enabled'])
                    ->map(fn($p, $code) => [
                        'code' => $code,
                        'name' => $p['name'],
                    ])
                    ->values(),
            ],
        ]);
    }

    /**
     * Pay via QR code using selected provider
     * No SalamPay account required
     */
    public function payQr(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'qr_id' => ['required', 'string', 'uuid'],
            'amount' => ['required', 'numeric', 'min:100', 'max:10000000'],
            'provider' => ['required', 'string', 'in:wave,orange_money,free_money,wizall,emoney'],
            'phone' => ['sometimes', 'string', 'regex:/^\+221[0-9]{9}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $qrCode = QrCode::with('merchant.wallet')
            ->where('uuid', $request->qr_id)
            ->where('status', 'active')
            ->first();

        if (!$qrCode) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not found or disabled',
            ], 404);
        }

        // Validate amount for dynamic QR with fixed amount
        if ($qrCode->amount && abs($qrCode->amount - $request->amount) > 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Amount does not match QR code',
            ], 400);
        }

        $merchant = $qrCode->merchant;
        $providerCode = $request->provider;

        try {
            // Create transaction
            $transaction = Transaction::create([
                'uuid' => Str::uuid(),
                'reference' => Transaction::generateReference(),
                'type' => 'qr_payment',
                'amount' => $request->amount,
                'currency' => 'XOF',
                'fee_amount' => $this->calculateFee($request->amount),
                'net_amount' => $request->amount - $this->calculateFee($request->amount),
                'merchant_id' => $merchant->id,
                'destination_wallet_id' => $merchant->wallet?->id,
                'provider' => $providerCode,
                'status' => 'pending',
                'description' => $qrCode->description ?? 'QR Payment to ' . $merchant->business_name,
                'metadata' => [
                    'qr_code_id' => $qrCode->uuid,
                    'customer_phone' => $request->phone,
                    'is_guest' => true,
                ],
                'ip_address' => $request->ip(),
            ]);

            // Get provider adapter
            $provider = $this->providerFactory->make($providerCode);

            // Create checkout with provider
            $checkoutData = $provider->createCheckout([
                'amount' => $request->amount,
                'currency' => 'XOF',
                'client_reference' => $transaction->reference,
                'success_url' => config('app.url') . '/payment/success?ref=' . $transaction->reference,
                'error_url' => config('app.url') . '/payment/error?ref=' . $transaction->reference,
                'phone' => $request->phone,
            ]);

            // Update transaction
            $transaction->update([
                'external_reference' => $checkoutData['id'] ?? $checkoutData['checkout_id'] ?? null,
                'status' => 'processing',
            ]);

            $redirectUrl = $this->getProviderRedirectUrl($providerCode, $checkoutData);

            return response()->json([
                'success' => true,
                'data' => [
                    'transaction_id' => $transaction->uuid,
                    'reference' => $transaction->reference,
                    'provider' => $providerCode,
                    'amount' => (float) $transaction->amount,
                    'status' => 'processing',
                    'redirect_url' => $redirectUrl,
                    'ussd_code' => $checkoutData['ussd_code'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve a payment link
     */
    public function resolvePaymentLink(string $code): JsonResponse
    {
        $paymentLink = PaymentLink::with('merchant:id,business_name,logo_url')
            ->where('short_code', $code)
            ->where('status', 'active')
            ->first();

        if (!$paymentLink) {
            return response()->json([
                'success' => false,
                'message' => 'Payment link not found or disabled',
            ], 404);
        }

        // Check expiration
        if ($paymentLink->expires_at && $paymentLink->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Payment link has expired',
            ], 410);
        }

        // Check max uses
        if ($paymentLink->max_uses && $paymentLink->use_count >= $paymentLink->max_uses) {
            return response()->json([
                'success' => false,
                'message' => 'Payment link has reached maximum uses',
            ], 410);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'link_id' => $paymentLink->uuid,
                'merchant' => [
                    'name' => $paymentLink->merchant?->business_name ?? 'SalamPay Merchant',
                    'logo' => $paymentLink->merchant?->logo_url,
                ],
                'amount' => $paymentLink->amount ? (float) $paymentLink->amount : null,
                'amount_editable' => !$paymentLink->amount,
                'currency' => 'XOF',
                'title' => $paymentLink->title,
                'description' => $paymentLink->description,
                'providers' => collect(self::PROVIDERS)
                    ->filter(fn($p) => $p['enabled'])
                    ->map(fn($p, $code) => [
                        'code' => $code,
                        'name' => $p['name'],
                    ])
                    ->values(),
            ],
        ]);
    }

    /**
     * Pay via payment link using selected provider
     */
    public function payPaymentLink(Request $request, string $code): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:100', 'max:10000000'],
            'provider' => ['required', 'string', 'in:wave,orange_money,free_money,wizall,emoney'],
            'phone' => ['sometimes', 'string', 'regex:/^\+221[0-9]{9}$/'],
            'email' => ['sometimes', 'email'],
            'name' => ['sometimes', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $paymentLink = PaymentLink::with('merchant.wallet')
            ->where('short_code', $code)
            ->where('status', 'active')
            ->first();

        if (!$paymentLink) {
            return response()->json([
                'success' => false,
                'message' => 'Payment link not found',
            ], 404);
        }

        // Validate amount if fixed
        if ($paymentLink->amount && abs($paymentLink->amount - $request->amount) > 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Amount does not match payment link',
            ], 400);
        }

        $merchant = $paymentLink->merchant;
        $providerCode = $request->provider;

        try {
            // Increment use count
            $paymentLink->increment('use_count');

            // Create transaction
            $transaction = Transaction::create([
                'uuid' => Str::uuid(),
                'reference' => Transaction::generateReference(),
                'type' => 'payment_link',
                'amount' => $request->amount,
                'currency' => 'XOF',
                'fee_amount' => $this->calculateFee($request->amount),
                'net_amount' => $request->amount - $this->calculateFee($request->amount),
                'merchant_id' => $merchant->id,
                'destination_wallet_id' => $merchant->wallet?->id,
                'provider' => $providerCode,
                'status' => 'pending',
                'description' => $paymentLink->title ?? 'Payment via link',
                'metadata' => [
                    'payment_link_id' => $paymentLink->uuid,
                    'customer_phone' => $request->phone,
                    'customer_email' => $request->email,
                    'customer_name' => $request->name,
                    'is_guest' => true,
                ],
                'ip_address' => $request->ip(),
            ]);

            // Get provider adapter
            $provider = $this->providerFactory->make($providerCode);

            // Build callback URLs
            $successUrl = $paymentLink->success_url ?? config('app.url') . '/payment/success?ref=' . $transaction->reference;
            $errorUrl = $paymentLink->cancel_url ?? config('app.url') . '/payment/error?ref=' . $transaction->reference;

            // Create checkout with provider
            $checkoutData = $provider->createCheckout([
                'amount' => $request->amount,
                'currency' => 'XOF',
                'client_reference' => $transaction->reference,
                'success_url' => $successUrl,
                'error_url' => $errorUrl,
                'phone' => $request->phone,
            ]);

            // Update transaction
            $transaction->update([
                'external_reference' => $checkoutData['id'] ?? $checkoutData['checkout_id'] ?? null,
                'status' => 'processing',
            ]);

            $redirectUrl = $this->getProviderRedirectUrl($providerCode, $checkoutData);

            return response()->json([
                'success' => true,
                'data' => [
                    'transaction_id' => $transaction->uuid,
                    'reference' => $transaction->reference,
                    'provider' => $providerCode,
                    'amount' => (float) $transaction->amount,
                    'status' => 'processing',
                    'redirect_url' => $redirectUrl,
                    'ussd_code' => $checkoutData['ussd_code'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment status by reference
     */
    public function status(string $reference): JsonResponse
    {
        $transaction = Transaction::where('reference', $reference)
            ->orWhere('uuid', $reference)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'reference' => $transaction->reference,
                'status' => $transaction->status,
                'amount' => (float) $transaction->amount,
                'currency' => $transaction->currency,
                'provider' => $transaction->provider,
                'completed_at' => $transaction->completed_at?->toISOString(),
            ],
        ]);
    }

    // Helper methods

    private function calculateFee(float $amount): float
    {
        // Default 2% fee
        return round($amount * 0.02, 2);
    }

    private function getProviderRedirectUrl(string $provider, array $checkoutData): ?string
    {
        return match ($provider) {
            'wave' => $checkoutData['wave_launch_url'] ?? null,
            'orange_money' => $checkoutData['payment_url'] ?? null,
            'free_money' => $checkoutData['redirect_url'] ?? null,
            'wizall' => $checkoutData['checkout_url'] ?? null,
            'emoney' => $checkoutData['payment_url'] ?? null,
            default => null,
        };
    }
}
