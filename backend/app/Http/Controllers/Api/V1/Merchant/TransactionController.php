<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $merchant = $request->get('merchant');
        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = Transaction::where('merchant_id', $merchant->id)
            ->orderBy('created_at', 'desc');

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($fromDate = $request->input('from_date')) {
            $query->where('created_at', '>=', $fromDate);
        }

        if ($toDate = $request->input('to_date')) {
            $query->where('created_at', '<=', $toDate);
        }

        $transactions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions->map(fn($tx) => $this->formatTransaction($tx)),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ],
        ]);
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $merchant = $request->get('merchant');

        if ($transaction->merchant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatTransaction($transaction),
        ]);
    }

    private function formatTransaction(Transaction $tx): array
    {
        return [
            'id' => $tx->uuid,
            'reference' => $tx->reference,
            'external_reference' => $tx->external_reference,
            'type' => $tx->type,
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
    }
}
