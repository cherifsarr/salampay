<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SettlementBatch;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SettlementController extends Controller
{
    /**
     * List settlements with filtering and pagination
     */
    public function index(Request $request)
    {
        $query = SettlementBatch::with(['merchant:id,business_name,business_email']);

        // Search by merchant name
        if ($search = $request->get('search')) {
            $query->whereHas('merchant', function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Filter by merchant
        if ($merchantId = $request->get('merchant_id')) {
            $query->where('merchant_id', $merchantId);
        }

        // Filter by date range
        if ($dateFrom = $request->get('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo = $request->get('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = $request->get('per_page', 20);
        $settlements = $query->paginate($perPage);

        // Summary stats
        $summary = [
            'pending_count' => SettlementBatch::where('status', 'pending')->count(),
            'pending_amount' => SettlementBatch::where('status', 'pending')->sum('net_amount'),
            'processing_count' => SettlementBatch::where('status', 'processing')->count(),
            'completed_this_month' => SettlementBatch::where('status', 'completed')
                ->where('settled_at', '>=', Carbon::now()->startOfMonth())
                ->sum('net_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $settlements->items(),
            'meta' => [
                'current_page' => $settlements->currentPage(),
                'last_page' => $settlements->lastPage(),
                'per_page' => $settlements->perPage(),
                'total' => $settlements->total(),
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Get single settlement details
     */
    public function show(int $id)
    {
        $settlement = SettlementBatch::with([
            'merchant:id,business_name,business_email,business_phone',
            'transactions.transaction',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $settlement,
        ]);
    }

    /**
     * Process a settlement (approve and initiate payout)
     */
    public function process(Request $request, int $id)
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $settlement = SettlementBatch::findOrFail($id);

        if ($settlement->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending settlements can be processed',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $settlement->update([
                'status' => 'processing',
                'processed_at' => now(),
                'processed_by' => $request->user()->id,
                'processing_notes' => $request->notes,
            ]);

            // Here you would initiate the actual payout via provider
            // For now, we mark as processing and it would be completed by webhook

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Settlement processing initiated',
                'data' => $settlement->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process settlement: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a settlement
     */
    public function reject(Request $request, int $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $settlement = SettlementBatch::findOrFail($id);

        if (!in_array($settlement->status, ['pending', 'processing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reject a completed or failed settlement',
            ], 422);
        }

        $settlement->update([
            'status' => 'failed',
            'failure_reason' => $request->reason,
            'failed_at' => now(),
            'failed_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Settlement rejected',
            'data' => $settlement->fresh(),
        ]);
    }

    /**
     * Mark settlement as completed (usually called after webhook confirmation)
     */
    public function complete(Request $request, int $id)
    {
        $request->validate([
            'settlement_reference' => 'required|string|max:100',
        ]);

        $settlement = SettlementBatch::findOrFail($id);

        if ($settlement->status !== 'processing') {
            return response()->json([
                'success' => false,
                'message' => 'Only processing settlements can be marked as completed',
            ], 422);
        }

        $settlement->update([
            'status' => 'completed',
            'settled_at' => now(),
            'settlement_reference' => $request->settlement_reference,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Settlement completed',
            'data' => $settlement->fresh(),
        ]);
    }

    /**
     * Get settlement statistics
     */
    public function stats(Request $request)
    {
        $days = $request->get('days', 30);
        $startDate = Carbon::now()->subDays($days);

        // Daily settlements
        $daily = SettlementBatch::select(
            DB::raw('DATE(settled_at) as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(net_amount) as amount')
        )
            ->where('status', 'completed')
            ->where('settled_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // By merchant (top 10)
        $byMerchant = SettlementBatch::select(
            'merchant_id',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(net_amount) as amount')
        )
            ->with('merchant:id,business_name')
            ->where('status', 'completed')
            ->where('settled_at', '>=', $startDate)
            ->groupBy('merchant_id')
            ->orderByDesc('amount')
            ->limit(10)
            ->get();

        // By method
        $byMethod = SettlementBatch::select(
            'settlement_method',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(net_amount) as amount')
        )
            ->where('status', 'completed')
            ->where('settled_at', '>=', $startDate)
            ->groupBy('settlement_method')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'daily' => $daily,
                'by_merchant' => $byMerchant,
                'by_method' => $byMethod,
            ],
        ]);
    }
}
