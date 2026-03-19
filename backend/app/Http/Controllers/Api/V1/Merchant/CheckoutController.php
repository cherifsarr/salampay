<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Modules\Provider\ProviderFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(
        private ProviderFactory $providerFactory
    ) {}

    /**
     * Create a checkout session
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:100', 'max:10000000'],
            'currency' => ['sometimes', 'string', 'in:XOF'],
            'description' => ['sometimes', 'string', 'max:255'],
            'success_url' => ['required', 'url'],
            'error_url' => ['required', 'url'],
            'cancel_url' => ['sometimes', 'url'],
            'metadata' => ['sometimes', 'array'],
            'customer_email' => ['sometimes', 'email'],
            'customer_phone' => ['sometimes', 'string'],
            'idempotency_key' => ['sometimes', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $merchant = $request->get('merchant');
        $apiKey = $request->get('api_key');

        // Check for idempotency
        if ($idempotencyKey = $request->input('idempotency_key')) {
            $existing = Transaction::where('idempotency_key', $idempotencyKey)
                ->where('merchant_id', $merchant->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'data' => $this->formatCheckoutSession($existing),
                ]);
            }
        }

        try {
            // Create transaction
            $transaction = Transaction::create([
                'uuid' => Str::uuid(),
                'reference' => Transaction::generateReference(),
                'idempotency_key' => $idempotencyKey,
                'type' => 'payment_link',
                'amount' => $request->amount,
                'currency' => $request->input('currency', 'XOF'),
                'fee_amount' => $this->calculateMerchantFee($request->amount, $merchant),
                'net_amount' => $request->amount - $this->calculateMerchantFee($request->amount, $merchant),
                'merchant_id' => $merchant->id,
                'destination_wallet_id' => $merchant->wallet?->id,
                'provider' => 'wave', // Default provider
                'status' => 'pending',
                'description' => $request->input('description'),
                'metadata' => array_merge($request->input('metadata', []), [
                    'success_url' => $request->success_url,
                    'error_url' => $request->error_url,
                    'cancel_url' => $request->input('cancel_url'),
                    'customer_email' => $request->input('customer_email'),
                    'customer_phone' => $request->input('customer_phone'),
                ]),
                'ip_address' => $request->ip(),
            ]);

            // Create Wave checkout session
            $provider = $this->providerFactory->make('wave');
            $checkoutData = $provider->createCheckout([
                'amount' => $request->amount,
                'currency' => 'XOF',
                'client_reference' => $transaction->reference,
                'success_url' => $request->success_url,
                'error_url' => $request->error_url,
            ]);

            $transaction->update([
                'external_reference' => $checkoutData['id'] ?? null,
                'status' => 'processing',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $transaction->uuid,
                    'reference' => $transaction->reference,
                    'amount' => (float) $transaction->amount,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status,
                    'checkout_url' => $checkoutData['wave_launch_url'] ?? null,
                    'expires_at' => $checkoutData['when_expires'] ?? null,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create checkout session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get checkout session details
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $merchant = $request->get('merchant');

        $transaction = Transaction::where('uuid', $id)
            ->where('merchant_id', $merchant->id)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Checkout session not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatCheckoutSession($transaction),
        ]);
    }

    /**
     * Cancel a checkout session
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $merchant = $request->get('merchant');

        $transaction = Transaction::where('uuid', $id)
            ->where('merchant_id', $merchant->id)
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Checkout session not found or cannot be cancelled',
            ], 404);
        }

        $transaction->update([
            'status' => 'cancelled',
            'status_reason' => 'Cancelled by merchant',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checkout session cancelled',
            'data' => $this->formatCheckoutSession($transaction),
        ]);
    }

    /**
     * Refund a completed checkout
     */
    public function refund(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['sometimes', 'numeric', 'min:100'],
            'reason' => ['sometimes', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $merchant = $request->get('merchant');

        $transaction = Transaction::where('uuid', $id)
            ->where('merchant_id', $merchant->id)
            ->where('status', 'completed')
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found or cannot be refunded',
            ], 404);
        }

        $refundAmount = $request->input('amount', $transaction->amount);

        if ($refundAmount > $transaction->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Refund amount exceeds original amount',
            ], 400);
        }

        try {
            $provider = $this->providerFactory->make($transaction->provider);
            $refundResult = $provider->refund([
                'transaction_id' => $transaction->provider_transaction_id,
                'amount' => $refundAmount,
            ]);

            // Create refund transaction
            $refundTx = Transaction::create([
                'uuid' => Str::uuid(),
                'reference' => Transaction::generateReference(),
                'type' => 'refund',
                'amount' => $refundAmount,
                'currency' => $transaction->currency,
                'fee_amount' => 0,
                'net_amount' => $refundAmount,
                'merchant_id' => $merchant->id,
                'source_wallet_id' => $merchant->wallet?->id,
                'provider' => $transaction->provider,
                'status' => 'completed',
                'completed_at' => now(),
                'description' => $request->input('reason', 'Refund for ' . $transaction->reference),
                'metadata' => [
                    'original_transaction' => $transaction->uuid,
                ],
            ]);

            // Update original transaction
            if ($refundAmount >= $transaction->amount) {
                $transaction->update(['status' => 'refunded']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Refund processed',
                'data' => [
                    'refund_id' => $refundTx->uuid,
                    'amount' => $refundAmount,
                    'status' => 'completed',
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process refund: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Helper methods

    private function calculateMerchantFee(float $amount, $merchant): float
    {
        // Default 2% fee, can be customized per merchant tier
        $feeRate = 0.02;
        return round($amount * $feeRate, 2);
    }

    private function formatCheckoutSession(Transaction $transaction): array
    {
        return [
            'session_id' => $transaction->uuid,
            'reference' => $transaction->reference,
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency,
            'fee_amount' => (float) $transaction->fee_amount,
            'net_amount' => (float) $transaction->net_amount,
            'status' => $transaction->status,
            'description' => $transaction->description,
            'created_at' => $transaction->created_at->toISOString(),
            'completed_at' => $transaction->completed_at?->toISOString(),
        ];
    }
}
