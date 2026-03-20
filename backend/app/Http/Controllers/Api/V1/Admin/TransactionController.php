<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransactionController extends Controller
{
    /**
     * List transactions with filtering and pagination
     */
    public function index(Request $request)
    {
        $query = Transaction::with([
            'user:id,first_name,last_name,phone',
            'merchant:id,business_name',
        ]);

        // Search by reference
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('external_reference', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        // Filter by status
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Filter by provider
        if ($provider = $request->get('provider')) {
            $query->where('provider', $provider);
        }

        // Filter by date range
        if ($dateFrom = $request->get('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo = $request->get('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // Filter by amount range
        if ($minAmount = $request->get('min_amount')) {
            $query->where('amount', '>=', $minAmount);
        }
        if ($maxAmount = $request->get('max_amount')) {
            $query->where('amount', '<=', $maxAmount);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = $request->get('per_page', 20);
        $transactions = $query->paginate($perPage);

        // Calculate summary stats for filtered results
        $summaryQuery = Transaction::query();
        $this->applyFilters($summaryQuery, $request);

        $summary = [
            'total_count' => $summaryQuery->count(),
            'total_amount' => $summaryQuery->where('status', 'completed')->sum('amount'),
            'total_fees' => $summaryQuery->where('status', 'completed')->sum('fee_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Get single transaction details
     */
    public function show(int $id)
    {
        $transaction = Transaction::with([
            'user:id,first_name,last_name,phone,email',
            'merchant:id,business_name,business_email',
            'settlement',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $transaction,
        ]);
    }

    /**
     * Refund a transaction
     */
    public function refund(Request $request, int $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'amount' => 'nullable|numeric|min:0',
        ]);

        $transaction = Transaction::findOrFail($id);

        if ($transaction->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Only completed transactions can be refunded',
            ], 422);
        }

        if ($transaction->refunded_at) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction has already been refunded',
            ], 422);
        }

        $refundAmount = $request->amount ?? $transaction->amount;

        if ($refundAmount > $transaction->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Refund amount cannot exceed original transaction amount',
            ], 422);
        }

        // Create refund transaction
        $refund = Transaction::create([
            'reference' => 'REF-' . strtoupper(uniqid()),
            'type' => 'refund',
            'amount' => $refundAmount,
            'fee_amount' => 0,
            'status' => 'completed',
            'user_id' => $transaction->user_id,
            'merchant_id' => $transaction->merchant_id,
            'provider' => $transaction->provider,
            'parent_transaction_id' => $transaction->id,
            'metadata' => [
                'reason' => $request->reason,
                'refunded_by' => $request->user()->id,
            ],
        ]);

        // Mark original transaction as refunded
        $transaction->update([
            'refunded_at' => now(),
            'refund_amount' => $refundAmount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaction refunded successfully',
            'data' => [
                'original' => $transaction->fresh(),
                'refund' => $refund,
            ],
        ]);
    }

    /**
     * Export transactions to CSV
     */
    public function export(Request $request)
    {
        $query = Transaction::with(['user:id,first_name,last_name,phone', 'merchant:id,business_name']);
        $this->applyFilters($query, $request);

        $transactions = $query->orderBy('created_at', 'desc')->limit(10000)->get();

        $filename = 'transactions_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Reference',
                'Type',
                'Amount',
                'Fee',
                'Net',
                'Status',
                'Provider',
                'Customer',
                'Merchant',
                'Date',
            ]);

            foreach ($transactions as $tx) {
                fputcsv($file, [
                    $tx->reference,
                    $tx->type,
                    $tx->amount,
                    $tx->fee_amount,
                    $tx->amount - $tx->fee_amount,
                    $tx->status,
                    $tx->provider,
                    $tx->user ? $tx->user->first_name . ' ' . $tx->user->last_name : '',
                    $tx->merchant?->business_name ?? '',
                    $tx->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get transaction statistics
     */
    public function stats(Request $request)
    {
        $days = $request->get('days', 30);
        $startDate = Carbon::now()->subDays($days);

        // By type
        $byType = Transaction::select('type', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as volume'))
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->groupBy('type')
            ->get();

        // By provider
        $byProvider = Transaction::select('provider', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as volume'))
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->whereNotNull('provider')
            ->groupBy('provider')
            ->get();

        // By status
        $byStatus = Transaction::select('status', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('status')
            ->get();

        // Success rate
        $total = Transaction::where('created_at', '>=', $startDate)->count();
        $completed = Transaction::where('created_at', '>=', $startDate)->where('status', 'completed')->count();
        $successRate = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'by_type' => $byType,
                'by_provider' => $byProvider,
                'by_status' => $byStatus,
                'success_rate' => $successRate,
            ],
        ]);
    }

    /**
     * Apply common filters to query
     */
    private function applyFilters($query, Request $request)
    {
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('external_reference', 'like', "%{$search}%");
            });
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($provider = $request->get('provider')) {
            $query->where('provider', $provider);
        }

        if ($dateFrom = $request->get('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if ($dateTo = $request->get('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }
    }
}
