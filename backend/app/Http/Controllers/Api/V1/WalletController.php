<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * List user's wallets
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallets = $user->wallets()->with('holds')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'wallets' => $wallets->map(fn($wallet) => $this->formatWallet($wallet)),
            ],
        ]);
    }

    /**
     * Get wallet details
     */
    public function show(Request $request, Wallet $wallet): JsonResponse
    {
        // Ensure wallet belongs to user
        if (!$this->userOwnsWallet($request->user(), $wallet)) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found',
            ], 404);
        }

        $wallet->load('holds');

        return response()->json([
            'success' => true,
            'data' => [
                'wallet' => $this->formatWallet($wallet),
            ],
        ]);
    }

    /**
     * Get wallet balance
     */
    public function balance(Request $request, Wallet $wallet): JsonResponse
    {
        if (!$this->userOwnsWallet($request->user(), $wallet)) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => (float) $wallet->balance,
                'available_balance' => (float) $wallet->getAvailableBalance(),
                'held_balance' => (float) $wallet->getHeldBalance(),
                'currency' => $wallet->currency,
            ],
        ]);
    }

    /**
     * Get wallet transactions
     */
    public function transactions(Request $request, Wallet $wallet): JsonResponse
    {
        if (!$this->userOwnsWallet($request->user(), $wallet)) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found',
            ], 404);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $type = $request->input('type');
        $status = $request->input('status');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $query = $wallet->ledgerEntries()
            ->with('transaction')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($type) {
            $query->whereHas('transaction', fn($q) => $q->where('type', $type));
        }

        if ($status) {
            $query->whereHas('transaction', fn($q) => $q->where('status', $status));
        }

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('created_at', '<=', $toDate);
        }

        $entries = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $entries->map(function ($entry) {
                    $tx = $entry->transaction;
                    return [
                        'id' => $tx->uuid,
                        'reference' => $tx->reference,
                        'type' => $tx->type,
                        'entry_type' => $entry->entry_type,
                        'amount' => (float) $entry->amount,
                        'balance_before' => (float) $entry->balance_before,
                        'balance_after' => (float) $entry->balance_after,
                        'currency' => $tx->currency,
                        'status' => $tx->status,
                        'description' => $tx->description,
                        'created_at' => $entry->created_at->toISOString(),
                    ];
                }),
                'pagination' => [
                    'current_page' => $entries->currentPage(),
                    'last_page' => $entries->lastPage(),
                    'per_page' => $entries->perPage(),
                    'total' => $entries->total(),
                ],
            ],
        ]);
    }

    // Helper methods

    private function userOwnsWallet($user, Wallet $wallet): bool
    {
        return $wallet->owner_type === get_class($user) && $wallet->owner_id === $user->id;
    }

    private function formatWallet(Wallet $wallet): array
    {
        $data = [
            'uuid' => $wallet->uuid,
            'wallet_type' => $wallet->wallet_type,
            'currency' => $wallet->currency,
            'balance' => (float) $wallet->balance,
            'available_balance' => (float) $wallet->getAvailableBalance(),
            'held_balance' => (float) $wallet->getHeldBalance(),
            'status' => $wallet->status,
            'created_at' => $wallet->created_at->toISOString(),
        ];

        // Include active holds
        if ($wallet->relationLoaded('holds')) {
            $activeHolds = $wallet->holds->where('status', 'active');
            if ($activeHolds->isNotEmpty()) {
                $data['holds'] = $activeHolds->map(fn($hold) => [
                    'id' => $hold->id,
                    'amount' => (float) $hold->amount,
                    'reason' => $hold->reason,
                    'expires_at' => $hold->expires_at->toISOString(),
                ]);
            }
        }

        return $data;
    }
}
