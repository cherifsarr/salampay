<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Modules\Provider\ProviderFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private ProviderFactory $providerFactory
    ) {}

    /**
     * Handle Wave webhook
     */
    public function wave(Request $request): JsonResponse
    {
        Log::info('Wave webhook received', [
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        try {
            $provider = $this->providerFactory->make('wave');

            // Verify webhook signature
            $signature = $request->header('Wave-Signature');
            if (!$provider->verifyWebhook($request->all(), $signature)) {
                Log::warning('Wave webhook signature verification failed');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $payload = $request->all();
            $eventType = $payload['type'] ?? null;

            switch ($eventType) {
                case 'checkout.session.completed':
                    $this->handleCheckoutCompleted($payload, 'wave');
                    break;

                case 'checkout.session.expired':
                    $this->handleCheckoutExpired($payload, 'wave');
                    break;

                case 'payout.completed':
                    $this->handlePayoutCompleted($payload, 'wave');
                    break;

                case 'payout.failed':
                    $this->handlePayoutFailed($payload, 'wave');
                    break;

                default:
                    Log::info('Unhandled Wave webhook event', ['type' => $eventType]);
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Wave webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle Orange Money webhook
     */
    public function orangeMoney(Request $request): JsonResponse
    {
        Log::info('Orange Money webhook received', [
            'payload' => $request->all(),
        ]);

        try {
            $provider = $this->providerFactory->make('orange_money');

            // Verify webhook token
            $token = $request->header('X-Notif-Token');
            if (!$provider->verifyWebhook($request->all(), $token)) {
                Log::warning('Orange Money webhook token verification failed');
                return response()->json(['error' => 'Invalid token'], 401);
            }

            $payload = $request->all();
            $status = $payload['status'] ?? null;

            if (in_array($status, ['SUCCESS', 'SUCCESSFULL'])) {
                $this->handleCheckoutCompleted($payload, 'orange_money');
            } elseif (in_array($status, ['FAILED', 'CANCELLED'])) {
                $this->handleCheckoutFailed($payload, 'orange_money');
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Orange Money webhook processing error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle Free Money webhook
     */
    public function freeMoney(Request $request): JsonResponse
    {
        Log::info('Free Money webhook received', [
            'payload' => $request->all(),
        ]);

        // TODO: Implement Free Money webhook handling
        return response()->json(['success' => true]);
    }

    // Helper methods

    private function handleCheckoutCompleted(array $payload, string $provider): void
    {
        $externalRef = $this->extractExternalReference($payload, $provider);
        if (!$externalRef) {
            Log::warning('No external reference in checkout completed webhook', [
                'provider' => $provider,
            ]);
            return;
        }

        DB::transaction(function () use ($externalRef, $payload, $provider) {
            $transaction = Transaction::where('external_reference', $externalRef)
                ->where('provider', $provider)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                Log::warning('Transaction not found for webhook', [
                    'external_ref' => $externalRef,
                    'provider' => $provider,
                ]);
                return;
            }

            if ($transaction->status === 'completed') {
                Log::info('Transaction already completed', ['id' => $transaction->id]);
                return;
            }

            // Update transaction
            $transaction->update([
                'status' => 'completed',
                'completed_at' => now(),
                'provider_transaction_id' => $this->extractProviderTransactionId($payload, $provider),
                'provider_response' => $payload,
            ]);

            // Credit destination wallet
            if ($transaction->destination_wallet_id) {
                $transaction->destinationWallet->credit(
                    $transaction->net_amount,
                    $transaction
                );
            }

            Log::info('Transaction completed via webhook', [
                'transaction_id' => $transaction->id,
                'provider' => $provider,
            ]);
        });
    }

    private function handleCheckoutExpired(array $payload, string $provider): void
    {
        $externalRef = $this->extractExternalReference($payload, $provider);
        if (!$externalRef) {
            return;
        }

        $transaction = Transaction::where('external_reference', $externalRef)
            ->where('provider', $provider)
            ->first();

        if ($transaction && in_array($transaction->status, ['pending', 'processing'])) {
            $transaction->markAsFailed('Session expired');
        }
    }

    private function handleCheckoutFailed(array $payload, string $provider): void
    {
        $externalRef = $this->extractExternalReference($payload, $provider);
        if (!$externalRef) {
            return;
        }

        $transaction = Transaction::where('external_reference', $externalRef)
            ->where('provider', $provider)
            ->first();

        if ($transaction && in_array($transaction->status, ['pending', 'processing'])) {
            $reason = $payload['failure_reason'] ?? $payload['message'] ?? 'Payment failed';
            $transaction->markAsFailed($reason);
        }
    }

    private function handlePayoutCompleted(array $payload, string $provider): void
    {
        $externalRef = $this->extractExternalReference($payload, $provider);
        if (!$externalRef) {
            return;
        }

        $transaction = Transaction::where('external_reference', $externalRef)
            ->where('provider', $provider)
            ->where('type', 'withdrawal')
            ->first();

        if ($transaction && $transaction->status !== 'completed') {
            $transaction->markAsCompleted();
        }
    }

    private function handlePayoutFailed(array $payload, string $provider): void
    {
        $externalRef = $this->extractExternalReference($payload, $provider);
        if (!$externalRef) {
            return;
        }

        DB::transaction(function () use ($externalRef, $payload, $provider) {
            $transaction = Transaction::where('external_reference', $externalRef)
                ->where('provider', $provider)
                ->where('type', 'withdrawal')
                ->lockForUpdate()
                ->first();

            if ($transaction && in_array($transaction->status, ['pending', 'processing'])) {
                // Refund the source wallet
                if ($transaction->source_wallet_id) {
                    $transaction->sourceWallet->credit(
                        $transaction->amount,
                        $transaction
                    );
                }

                $reason = $payload['failure_reason'] ?? 'Payout failed';
                $transaction->markAsFailed($reason);
            }
        });
    }

    private function extractExternalReference(array $payload, string $provider): ?string
    {
        return match ($provider) {
            'wave' => $payload['data']['client_reference'] ?? $payload['data']['id'] ?? null,
            'orange_money' => $payload['txnid'] ?? $payload['order_id'] ?? null,
            default => $payload['reference'] ?? $payload['id'] ?? null,
        };
    }

    private function extractProviderTransactionId(array $payload, string $provider): ?string
    {
        return match ($provider) {
            'wave' => $payload['data']['id'] ?? null,
            'orange_money' => $payload['txnid'] ?? null,
            default => $payload['transaction_id'] ?? $payload['id'] ?? null,
        };
    }
}
