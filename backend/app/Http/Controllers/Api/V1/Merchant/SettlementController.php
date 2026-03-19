<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\SettlementBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $merchant = $request->get('merchant');
        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = SettlementBatch::where('merchant_id', $merchant->id)
            ->orderBy('created_at', 'desc');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $settlements = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'settlements' => $settlements->map(fn($s) => $this->formatSettlement($s)),
                'pagination' => [
                    'current_page' => $settlements->currentPage(),
                    'last_page' => $settlements->lastPage(),
                    'per_page' => $settlements->perPage(),
                    'total' => $settlements->total(),
                ],
            ],
        ]);
    }

    public function show(Request $request, SettlementBatch $settlement): JsonResponse
    {
        $merchant = $request->get('merchant');

        if ($settlement->merchant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Settlement not found',
            ], 404);
        }

        $settlement->load('transactions');

        return response()->json([
            'success' => true,
            'data' => $this->formatSettlement($settlement, true),
        ]);
    }

    private function formatSettlement(SettlementBatch $settlement, bool $includeTransactions = false): array
    {
        $data = [
            'id' => $settlement->uuid,
            'batch_number' => $settlement->batch_number,
            'period_start' => $settlement->period_start->toISOString(),
            'period_end' => $settlement->period_end->toISOString(),
            'gross_amount' => (float) $settlement->gross_amount,
            'fee_amount' => (float) $settlement->fee_amount,
            'refund_amount' => (float) $settlement->refund_amount,
            'chargeback_amount' => (float) $settlement->chargeback_amount,
            'adjustment_amount' => (float) $settlement->adjustment_amount,
            'net_amount' => (float) $settlement->net_amount,
            'currency' => $settlement->currency,
            'settlement_method' => $settlement->settlement_method,
            'settlement_reference' => $settlement->settlement_reference,
            'settled_at' => $settlement->settled_at?->toISOString(),
            'status' => $settlement->status,
            'created_at' => $settlement->created_at->toISOString(),
        ];

        if ($includeTransactions && $settlement->relationLoaded('transactions')) {
            $data['transactions'] = $settlement->transactions->map(fn($tx) => [
                'id' => $tx->transaction->uuid,
                'reference' => $tx->transaction->reference,
                'amount' => (float) $tx->amount,
                'fee_amount' => (float) $tx->fee_amount,
            ]);
        }

        return $data;
    }
}
