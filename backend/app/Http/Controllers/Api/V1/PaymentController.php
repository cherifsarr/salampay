<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Provider\ProviderFactory;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        private ProviderFactory $providerFactory,
        private TransactionService $transactionService
    ) {}

    /**
     * Get available deposit methods with fee preview
     */
    public function depositMethods(Request $request): JsonResponse
    {
        $sampleAmount = 10000; // For fee preview

        $methods = [
            [
                'id' => 'wave',
                'name' => 'Wave',
                'icon' => 'wave',
                'min_amount' => 100,
                'max_amount' => 1000000,
                'fee_preview' => $this->transactionService->getFeePreview('deposit', $sampleAmount, 'wave'),
                'available' => $this->providerFactory->has('wave'),
            ],
            [
                'id' => 'orange_money',
                'name' => 'Orange Money',
                'icon' => 'orange_money',
                'min_amount' => 100,
                'max_amount' => 500000,
                'fee_preview' => $this->transactionService->getFeePreview('deposit', $sampleAmount, 'orange_money'),
                'available' => $this->providerFactory->has('orange_money'),
            ],
            [
                'id' => 'free_money',
                'name' => 'Free Money',
                'icon' => 'free_money',
                'min_amount' => 100,
                'max_amount' => 500000,
                'fee_preview' => $this->transactionService->getFeePreview('deposit', $sampleAmount, 'free_money'),
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

        // Validate deposit against wallet limits
        $validation = $this->transactionService->validateDeposit($wallet, $amount);
        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $validation['message'],
                'errors' => $validation['errors'],
            ], 400);
        }

        try {
            $provider = $this->providerFactory->make($providerName);

            // Create transaction record using service
            $transaction = $this->transactionService->createDeposit(
                $user,
                $wallet,
                $amount,
                $providerName,
                [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );

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
                    'fee' => (float) $transaction->fee_amount,
                    'net_amount' => (float) $transaction->net_amount,
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
     * Get available withdrawal methods with fee preview
     */
    public function withdrawMethods(Request $request): JsonResponse
    {
        $sampleAmount = 10000;

        $methods = [
            [
                'id' => 'wave',
                'name' => 'Wave',
                'icon' => 'wave',
                'min_amount' => 500,
                'max_amount' => 500000,
                'fee_preview' => $this->transactionService->getFeePreview('withdrawal', $sampleAmount, 'wave'),
                'available' => true,
            ],
            [
                'id' => 'orange_money',
                'name' => 'Orange Money',
                'icon' => 'orange_money',
                'min_amount' => 500,
                'max_amount' => 300000,
                'fee_preview' => $this->transactionService->getFeePreview('withdrawal', $sampleAmount, 'orange_money'),
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

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found',
            ], 400);
        }

        // Validate withdrawal against wallet limits
        $validation = $this->transactionService->validateWithdrawal($wallet, $amount);
        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $validation['message'],
                'errors' => $validation['errors'],
            ], 400);
        }

        try {
            // Create withdrawal transaction
            $transaction = $this->transactionService->createWithdrawal(
                $user,
                $wallet,
                $amount,
                $request->provider,
                $request->recipient_phone,
                ['ip_address' => $request->ip()]
            );

            $provider = $this->providerFactory->make($request->provider);

            // Initiate payout
            $payoutResult = $provider->payout([
                'amount' => $transaction->net_amount,
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
                    'fee' => (float) $transaction->fee_amount,
                    'net_amount' => (float) $transaction->net_amount,
                    'status' => 'processing',
                ],
            ]);

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

        // Validate transfer against wallet limits
        $validation = $this->transactionService->validateTransfer($sourceWallet, $destWallet, $amount);
        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $validation['message'],
                'errors' => $validation['errors'],
            ], 400);
        }

        try {
            $transaction = $this->transactionService->createTransfer(
                $user,
                $recipient,
                $sourceWallet,
                $destWallet,
                $amount,
                $request->input('description'),
                ['ip_address' => $request->ip()]
            );

            return response()->json([
                'success' => true,
                'message' => 'Transfer successful',
                'data' => [
                    'transaction_id' => $transaction->uuid,
                    'reference' => $transaction->reference,
                    'amount' => $amount,
                    'fee' => (float) $transaction->fee_amount,
                    'net_amount' => (float) $transaction->net_amount,
                    'recipient' => [
                        'name' => $recipient->name,
                        'phone' => $this->maskPhone($recipient->phone),
                    ],
                    'new_balance' => (float) $sourceWallet->fresh()->balance,
                ],
            ]);

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
                'fee' => (float) $transaction->fee_amount,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Get wallet limits and usage summary
     */
    public function walletLimits(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $user->mainWallet;

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found',
            ], 400);
        }

        $summary = $this->transactionService->getWalletUsageSummary($wallet);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Preview transaction fee
     */
    public function feePreview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:deposit,withdrawal,transfer'],
            'amount' => ['required', 'numeric', 'min:1'],
            'provider' => ['sometimes', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $preview = $this->transactionService->getFeePreview(
            $request->type,
            (float) $request->amount,
            $request->provider
        );

        return response()->json([
            'success' => true,
            'data' => $preview,
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
