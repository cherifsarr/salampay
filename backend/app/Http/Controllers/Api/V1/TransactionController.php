<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * List user's transactions
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = Transaction::where(function ($q) use ($user) {
            $q->where('source_user_id', $user->id)
              ->orWhere('destination_user_id', $user->id);
        })
        ->orderBy('created_at', 'desc');

        // Apply filters
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($provider = $request->input('provider')) {
            $query->where('provider', $provider);
        }

        if ($fromDate = $request->input('from_date')) {
            $query->where('created_at', '>=', $fromDate);
        }

        if ($toDate = $request->input('to_date')) {
            $query->where('created_at', '<=', $toDate);
        }

        if ($minAmount = $request->input('min_amount')) {
            $query->where('amount', '>=', $minAmount);
        }

        if ($maxAmount = $request->input('max_amount')) {
            $query->where('amount', '<=', $maxAmount);
        }

        $transactions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions->map(fn($tx) => $this->formatTransaction($tx, $user)),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ],
        ]);
    }

    /**
     * Get transaction details
     */
    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();

        // Check if user is involved in this transaction
        if ($transaction->source_user_id !== $user->id && $transaction->destination_user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        $transaction->load(['sourceUser', 'destinationUser']);

        return response()->json([
            'success' => true,
            'data' => [
                'transaction' => $this->formatTransactionFull($transaction, $user),
            ],
        ]);
    }

    // Helper methods

    private function formatTransaction(Transaction $tx, $user): array
    {
        $direction = $this->getDirection($tx, $user);

        return [
            'id' => $tx->uuid,
            'reference' => $tx->reference,
            'type' => $tx->type,
            'direction' => $direction,
            'amount' => (float) $tx->amount,
            'fee_amount' => (float) $tx->fee_amount,
            'net_amount' => (float) $tx->net_amount,
            'currency' => $tx->currency,
            'status' => $tx->status,
            'description' => $tx->description,
            'provider' => $tx->provider,
            'created_at' => $tx->created_at->toISOString(),
            'completed_at' => $tx->completed_at?->toISOString(),
        ];
    }

    private function formatTransactionFull(Transaction $tx, $user): array
    {
        $direction = $this->getDirection($tx, $user);

        $data = [
            'id' => $tx->uuid,
            'reference' => $tx->reference,
            'type' => $tx->type,
            'direction' => $direction,
            'amount' => (float) $tx->amount,
            'fee_amount' => (float) $tx->fee_amount,
            'net_amount' => (float) $tx->net_amount,
            'currency' => $tx->currency,
            'status' => $tx->status,
            'status_reason' => $tx->status_reason,
            'description' => $tx->description,
            'provider' => $tx->provider,
            'metadata' => $tx->metadata,
            'created_at' => $tx->created_at->toISOString(),
            'completed_at' => $tx->completed_at?->toISOString(),
        ];

        // Add counterparty info for transfers
        if (in_array($tx->type, ['transfer_p2p', 'transfer_merchant'])) {
            if ($direction === 'out' && $tx->destinationUser) {
                $data['recipient'] = [
                    'name' => $tx->destinationUser->name,
                    'phone' => $this->maskPhone($tx->destinationUser->phone),
                ];
            } elseif ($direction === 'in' && $tx->sourceUser) {
                $data['sender'] = [
                    'name' => $tx->sourceUser->name,
                    'phone' => $this->maskPhone($tx->sourceUser->phone),
                ];
            }
        }

        return $data;
    }

    private function getDirection(Transaction $tx, $user): string
    {
        // Deposits are always "in"
        if ($tx->type === 'deposit') {
            return 'in';
        }

        // Withdrawals are always "out"
        if ($tx->type === 'withdrawal') {
            return 'out';
        }

        // For transfers and payments, check if user is sender or receiver
        if ($tx->source_user_id === $user->id) {
            return 'out';
        }

        return 'in';
    }

    private function maskPhone(string $phone): string
    {
        // Show first 4 and last 2 characters
        $length = strlen($phone);
        if ($length <= 6) {
            return $phone;
        }

        return substr($phone, 0, 4) . str_repeat('*', $length - 6) . substr($phone, -2);
    }
}
