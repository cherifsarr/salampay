<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Provider\ProviderFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        private ProviderFactory $providerFactory
    ) {}

    /**
     * Get available deposit methods
     */
    public function depositMethods(Request $request): JsonResponse
    {
        $methods = [
            [
                'id' => 'wave',
                'name' => 'Wave',
                'icon' => 'wave',
                'min_amount' => 100,
                'max_amount' => 1000000,
                'fee_type' => 'percentage',
                'fee_value' => 1,
                'available' => $this->providerFactory->has('wave'),
            ],
            [
                'id' => 'orange_money',
                'name' => 'Orange Money',
                'icon' => 'orange_money',
                'min_amount' => 100,
                'max_amount' => 500000,
                'fee_type' => 'percentage',
                'fee_value' => 1.5,
                'available' => $this->providerFactory->has('orange_money'),
            ],
            [
                'id' => 'free_money',
                'name' => 'Free Money',
                'icon' => 'free_money',
                'min_amount' => 100,
                'max_amount' => 500000,
                'fee_type' => 'fixed',
                'fee_value' => 50,
                'available' => $this->providerFactory->has('free_money'),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'methods' => array_filter($methods, fn($m) => $m['available']),
            ],
        ]);
    }

    /**
     * Initiate a deposit
     */
    public function initiateDeposit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:100', 'max:1000000'],
            'provider' => ['required', 'in:wave,orange_money,free_money'],
            'return_url' => ['sometimes', 'url'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $amount = (float) $request->amount;
        $providerName = $request->provider;

        // Get user's main wallet
        $wallet = $user->mainWallet;
        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found',
            ], 400);
        }

        // Calculate fee
        $fee = $this->calculateDepositFee($amount, $providerName);

        try {
            $provider = $this->providerFactory->make($providerName);

            // Create transaction record
            $transaction = Transaction::create([
                'uuid' => Str::uuid(),
                'reference' => Transaction::generateReference(),
                'type' => 'deposit',
                'amount' => $amount,
                'currency' => 'XOF',
                'fee_amount' => $fee,
                'net_amount' => $amount - $fee,
                'destination_wallet_id' => $wallet->id,
                'destination_user_id' => $user->id,
                'provider' => $providerName,
                'status' => 'pending',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Create checkout session with provider
            $checkoutData = $provider->createCheckout([
                'amount' => $amount,
                'currency' => 'XOF',
                'client_reference' => $transaction->reference,
                'success_url' => $request->input('return_url', config('app.url') . '/payment/success'),
                'error_url' => $request->input('return_url', config('app.url') . '/payment/error'),
            ]);

            // Update transaction with provider reference
            $transaction->update([
                'external_reference' => $checkoutData['id'] ?? $checkoutData['session_id'] ?? null,
                'status' => 'processing',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Deposit initiated',
                'data' => [
                    'transaction_id' => $transaction->uuid,
                    'reference' => $transaction->reference,
                    'amount' => $amount,
                    'fee' => $fee,
                    'net_amount' => $amount - $fee,
                    'checkout_url' => $checkoutData['wave_launch_url'] ?? $checkoutData['payment_url'] ?? null,
                    'expires_at' => $checkoutData['when_expires'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate deposit: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get deposit status
     */
    public function depositStatus(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $transaction = Transaction::where('uuid', $id)
            ->where('destination_user_id', $user->id)
            ->where('type', 'deposit')
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
                'transaction_id' => $transaction->uuid,
                'reference' => $transaction->reference,
                'amount' => (float) $transaction->amount,
                'fee' => (float) $transaction->fee_amount,
                'net_amount' => (float) $transaction->net_amount,
                'status' => $transaction->status,
                'provider' => $transaction->provider,
                'created_at' => $transaction->created_at->toISOString(),
                'completed_at' => $transaction->completed_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Get available withdrawal methods
     */
    public function withdrawMethods(Request $request): JsonResponse
    {
        $methods = [
            [
                'id' => 'wave',
                'name' => 'Wave',
                'icon' => 'wave',
                'min_amount' => 500,
                'max_amount' => 500000,
                'fee_type' => 'percentage',
                'fee_value' => 1,
                'available' => true,
            ],
            [
                'id' => 'orange_money',
                'name' => 'Orange Money',
                'icon' => 'orange_money',
                'min_amount' => 500,
                'max_amount' => 300000,
                'fee_type' => 'percentage',
                'fee_value' => 1.5,
                'available' => true,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'methods' => $methods,
            ],
        ]);
    }

    /**
     * Initiate a withdrawal
     */
    public function initiateWithdraw(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:500', 'max:500000'],
            'provider' => ['required', 'in:wave,orange_money'],
            'recipient_phone' => ['required', 'string', 'regex:/^(\+221|221)?[0-9]{9}$/'],
            'pin' => ['required', 'string', 'size:4'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Verify PIN
        if (!$user->verifyPin($request->pin)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid PIN',
            ], 401);
        }

        $amount = (float) $request->amount;
        $wallet = $user->mainWallet;

        // Check balance
        if (!$wallet || $wallet->getAvailableBalance() < $amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance',
            ], 400);
        }

        $fee = $this->calculateWithdrawFee($amount, $request->provider);

        try {
            return DB::transaction(function () use ($request, $user, $wallet, $amount, $fee) {
                $provider = $this->providerFactory->make($request->provider);

                // Debit wallet
                $transaction = Transaction::create([
                    'uuid' => Str::uuid(),
                    'reference' => Transaction::generateReference(),
                    'type' => 'withdrawal',
                    'amount' => $amount,
                    'currency' => 'XOF',
                    'fee_amount' => $fee,
                    'net_amount' => $amount - $fee,
                    'source_wallet_id' => $wallet->id,
                    'source_user_id' => $user->id,
                    'provider' => $request->provider,
                    'status' => 'pending',
                    'ip_address' => $request->ip(),
                    'metadata' => ['recipient_phone' => $request->recipient_phone],
                ]);

                // Debit the wallet
                $wallet->debit($amount, $transaction);

                // Initiate payout
                $payoutResult = $provider->payout([
                    'amount' => $amount - $fee,
                    'currency' => 'XOF',
                    'recipient_phone' => $request->recipient_phone,
                    'client_reference' => $transaction->reference,
                ]);

                $transaction->update([
                    'external_reference' => $payoutResult['id'] ?? null,
                    'status' => 'processing',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Withdrawal initiated',
                    'data' => [
                        'transaction_id' => $transaction->uuid,
                        'reference' => $transaction->reference,
                        'amount' => $amount,
                        'fee' => $fee,
                        'net_amount' => $amount - $fee,
                        'status' => 'processing',
                    ],
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate withdrawal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get withdrawal status
     */
    public function withdrawStatus(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $transaction = Transaction::where('uuid', $id)
            ->where('source_user_id', $user->id)
            ->where('type', 'withdrawal')
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
                'transaction_id' => $transaction->uuid,
                'reference' => $transaction->reference,
                'amount' => (float) $transaction->amount,
                'fee' => (float) $transaction->fee_amount,
                'net_amount' => (float) $transaction->net_amount,
                'status' => $transaction->status,
                'provider' => $transaction->provider,
                'created_at' => $transaction->created_at->toISOString(),
                'completed_at' => $transaction->completed_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Transfer to another user
     */
    public function transfer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:100', 'max:1000000'],
            'recipient_phone' => ['required', 'string'],
            'pin' => ['required', 'string', 'size:4'],
            'description' => ['sometimes', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Verify PIN
        if (!$user->verifyPin($request->pin)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid PIN',
            ], 401);
        }

        // Find recipient
        $recipientPhone = $this->normalizePhone($request->recipient_phone);
        $recipient = User::where('phone', $recipientPhone)->first();

        if (!$recipient) {
            return response()->json([
                'success' => false,
                'message' => 'Recipient not found',
            ], 404);
        }

        if ($recipient->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot transfer to yourself',
            ], 400);
        }

        $amount = (float) $request->amount;
        $sourceWallet = $user->mainWallet;
        $destWallet = $recipient->mainWallet;

        if (!$sourceWallet || !$destWallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found',
            ], 400);
        }

        // Check balance
        if ($sourceWallet->getAvailableBalance() < $amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance',
            ], 400);
        }

        try {
            return DB::transaction(function () use ($user, $recipient, $sourceWallet, $destWallet, $amount, $request) {
                $fee = 0; // P2P transfers are free

                $transaction = Transaction::create([
                    'uuid' => Str::uuid(),
                    'reference' => Transaction::generateReference(),
                    'type' => 'transfer_p2p',
                    'amount' => $amount,
                    'currency' => 'XOF',
                    'fee_amount' => $fee,
                    'net_amount' => $amount,
                    'source_wallet_id' => $sourceWallet->id,
                    'source_user_id' => $user->id,
                    'destination_wallet_id' => $destWallet->id,
                    'destination_user_id' => $recipient->id,
                    'provider' => 'internal',
                    'status' => 'completed',
                    'completed_at' => now(),
                    'description' => $request->input('description'),
                    'ip_address' => $request->ip(),
                ]);

                // Execute transfer
                $sourceWallet->debit($amount, $transaction);
                $destWallet->credit($amount, $transaction);

                return response()->json([
                    'success' => true,
                    'message' => 'Transfer successful',
                    'data' => [
                        'transaction_id' => $transaction->uuid,
                        'reference' => $transaction->reference,
                        'amount' => $amount,
                        'recipient' => [
                            'name' => $recipient->name,
                            'phone' => $this->maskPhone($recipient->phone),
                        ],
                        'new_balance' => (float) $sourceWallet->fresh()->balance,
                    ],
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get transfer status
     */
    public function transferStatus(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $transaction = Transaction::where('uuid', $id)
            ->where(function ($q) use ($user) {
                $q->where('source_user_id', $user->id)
                  ->orWhere('destination_user_id', $user->id);
            })
            ->whereIn('type', ['transfer_p2p', 'transfer_merchant'])
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
                'transaction_id' => $transaction->uuid,
                'reference' => $transaction->reference,
                'amount' => (float) $transaction->amount,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Scan QR code for payment
     */
    public function scanQr(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'qr_data' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Parse QR data
        $qrData = $this->parseQrCode($request->qr_data);

        if (!$qrData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid QR code',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $qrData,
        ]);
    }

    /**
     * Pay via QR code
     */
    public function payQr(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'qr_uuid' => ['required', 'string', 'uuid'],
            'amount' => ['sometimes', 'numeric', 'min:100'],
            'pin' => ['required', 'string', 'size:4'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // TODO: Implement QR payment processing
        return response()->json([
            'success' => false,
            'message' => 'QR payment not yet implemented',
        ], 501);
    }

    // Helper methods

    private function calculateDepositFee(float $amount, string $provider): float
    {
        return match ($provider) {
            'wave' => round($amount * 0.01, 2), // 1%
            'orange_money' => round($amount * 0.015, 2), // 1.5%
            'free_money' => 50, // Fixed 50 XOF
            default => 0,
        };
    }

    private function calculateWithdrawFee(float $amount, string $provider): float
    {
        return match ($provider) {
            'wave' => round($amount * 0.01, 2), // 1%
            'orange_money' => round($amount * 0.015, 2), // 1.5%
            default => 0,
        };
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '221')) {
            $phone = substr($phone, 3);
        }
        return '+221' . $phone;
    }

    private function maskPhone(string $phone): string
    {
        $length = strlen($phone);
        if ($length <= 6) {
            return $phone;
        }
        return substr($phone, 0, 4) . str_repeat('*', $length - 6) . substr($phone, -2);
    }

    private function parseQrCode(string $qrData): ?array
    {
        // Try to parse as JSON
        $data = json_decode($qrData, true);
        if ($data && isset($data['type'])) {
            return $data;
        }

        // Try to parse as SalamPay QR format: SP:UUID:AMOUNT (optional)
        if (str_starts_with($qrData, 'SP:')) {
            $parts = explode(':', $qrData);
            if (count($parts) >= 2) {
                return [
                    'type' => 'salampay',
                    'uuid' => $parts[1],
                    'amount' => isset($parts[2]) ? (float) $parts[2] : null,
                ];
            }
        }

        return null;
    }
}
