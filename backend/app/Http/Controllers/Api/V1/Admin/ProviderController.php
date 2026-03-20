<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Modules\Treasury\Models\ProviderAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProviderController extends Controller
{
    /**
     * List all payment providers
     */
    public function index()
    {
        $providers = ProviderAccount::all()->map(function ($provider) {
            // Get transaction stats
            $stats = Transaction::where('provider', $provider->provider)
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful')
                ->first();

            $successRate = $stats->total > 0
                ? round(($stats->successful / $stats->total) * 100, 1)
                : 0;

            return [
                'id' => $provider->id,
                'provider' => $provider->provider,
                'account_name' => $provider->account_name,
                'status' => $provider->status,
                'balance' => $provider->balance,
                'balance_updated_at' => $provider->balance_updated_at,
                'sandbox_mode' => $provider->sandbox_mode,
                'transaction_count' => $stats->total,
                'success_rate' => $successRate,
                'config' => $provider->config,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $providers,
        ]);
    }

    /**
     * Get single provider details
     */
    public function show(int $id)
    {
        $provider = ProviderAccount::findOrFail($id);

        // Get detailed stats
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $stats = Transaction::where('provider', $provider->provider)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful_transactions,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_transactions,
                SUM(CASE WHEN status = "completed" THEN amount ELSE 0 END) as total_volume,
                SUM(CASE WHEN status = "completed" THEN fee_amount ELSE 0 END) as total_fees
            ')
            ->first();

        // Daily transaction volume
        $dailyVolume = Transaction::where('provider', $provider->provider)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->where('status', 'completed')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as volume')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'provider' => $provider,
                'stats' => $stats,
                'daily_volume' => $dailyVolume,
            ],
        ]);
    }

    /**
     * Update provider status
     */
    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'maintenance'])],
        ]);

        $provider = ProviderAccount::findOrFail($id);

        $provider->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Provider status updated successfully',
            'data' => $provider->fresh(),
        ]);
    }

    /**
     * Update provider configuration
     */
    public function updateConfig(Request $request, int $id)
    {
        $request->validate([
            'config' => 'required|array',
        ]);

        $provider = ProviderAccount::findOrFail($id);

        $provider->update([
            'config' => array_merge($provider->config ?? [], $request->config),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Provider configuration updated',
            'data' => $provider->fresh(),
        ]);
    }

    /**
     * Refresh provider balance (fetch from external API)
     */
    public function refreshBalance(int $id)
    {
        $provider = ProviderAccount::findOrFail($id);

        // In a real implementation, this would call the provider's API
        // For now, we'll simulate by updating the timestamp
        $provider->update([
            'balance_updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Balance refresh initiated',
            'data' => $provider->fresh(),
        ]);
    }

    /**
     * Get provider statistics summary
     */
    public function stats()
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $stats = Transaction::whereNotNull('provider')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->select(
                'provider',
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN amount ELSE 0 END) as volume')
            )
            ->groupBy('provider')
            ->get()
            ->map(function ($item) {
                $item->success_rate = $item->total_transactions > 0
                    ? round(($item->successful / $item->total_transactions) * 100, 1)
                    : 0;
                return $item;
            });

        // Total balance across all providers
        $totalBalance = ProviderAccount::where('status', 'active')->sum('balance');

        return response()->json([
            'success' => true,
            'data' => [
                'by_provider' => $stats,
                'total_balance' => $totalBalance,
            ],
        ]);
    }
}
