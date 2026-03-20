<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Merchant;
use App\Models\Transaction;
use App\Models\SettlementBatch;
use App\Models\KycDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function stats(Request $request)
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();

        // User stats
        $totalUsers = User::where('type', 'customer')->count();
        $activeUsers = User::where('type', 'customer')
            ->where('status', 'active')
            ->count();
        $newUsersToday = User::where('type', 'customer')
            ->whereDate('created_at', $today)
            ->count();

        // Merchant stats
        $totalMerchants = Merchant::count();
        $activeMerchants = Merchant::where('status', 'active')->count();
        $pendingKyb = Merchant::where('kyb_status', 'pending')->count();

        // Transaction stats
        $todayTransactions = Transaction::whereDate('created_at', $today)->count();
        $todayVolume = Transaction::whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('amount');

        $monthTransactions = Transaction::where('created_at', '>=', $startOfMonth)->count();
        $monthVolume = Transaction::where('created_at', '>=', $startOfMonth)
            ->where('status', 'completed')
            ->sum('amount');

        // Pending actions
        $pendingKyc = KycDocument::where('status', 'pending')->count();
        $pendingSettlements = SettlementBatch::where('status', 'pending')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'users' => [
                    'total' => $totalUsers,
                    'active' => $activeUsers,
                    'new_today' => $newUsersToday,
                ],
                'merchants' => [
                    'total' => $totalMerchants,
                    'active' => $activeMerchants,
                    'pending_kyb' => $pendingKyb,
                ],
                'transactions' => [
                    'today_count' => $todayTransactions,
                    'today_volume' => $todayVolume,
                    'month_count' => $monthTransactions,
                    'month_volume' => $monthVolume,
                ],
                'pending_actions' => [
                    'kyc_verifications' => $pendingKyc,
                    'settlements' => $pendingSettlements,
                ],
            ],
        ]);
    }

    /**
     * Get chart data for dashboard
     */
    public function chartData(Request $request)
    {
        $days = $request->get('days', 30);
        $startDate = Carbon::now()->subDays($days);

        // Daily transaction volumes
        $dailyVolumes = Transaction::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(CASE WHEN status = "completed" THEN amount ELSE 0 END) as volume')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Transaction breakdown by type
        $typeBreakdown = Transaction::select(
            'type',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(amount) as volume')
        )
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->groupBy('type')
            ->get();

        // Provider breakdown
        $providerBreakdown = Transaction::select(
            'provider',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(amount) as volume')
        )
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->whereNotNull('provider')
            ->groupBy('provider')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'daily_volumes' => $dailyVolumes,
                'type_breakdown' => $typeBreakdown,
                'provider_breakdown' => $providerBreakdown,
            ],
        ]);
    }

    /**
     * Get recent transactions for dashboard
     */
    public function recentTransactions(Request $request)
    {
        $limit = $request->get('limit', 10);

        $transactions = Transaction::with(['user:id,first_name,last_name,phone', 'merchant:id,business_name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'fee' => $transaction->fee_amount,
                    'status' => $transaction->status,
                    'provider' => $transaction->provider,
                    'user' => $transaction->user ? [
                        'name' => $transaction->user->first_name . ' ' . $transaction->user->last_name,
                        'phone' => $transaction->user->phone,
                    ] : null,
                    'merchant' => $transaction->merchant?->business_name,
                    'created_at' => $transaction->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }
}
