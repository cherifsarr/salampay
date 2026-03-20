<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MerchantController extends Controller
{
    /**
     * List merchants with filtering and pagination
     */
    public function index(Request $request)
    {
        $query = Merchant::with(['wallet', 'apiKeys', 'stores']);

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                    ->orWhere('business_email', 'like', "%{$search}%")
                    ->orWhere('business_phone', 'like', "%{$search}%");
            });
        }

        // Filter by KYB status
        if ($kybStatus = $request->get('kyb_status')) {
            $query->where('kyb_status', $kybStatus);
        }

        // Filter by account status
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Filter by business type
        if ($businessType = $request->get('business_type')) {
            $query->where('business_type', $businessType);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = $request->get('per_page', 20);
        $merchants = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $merchants->items(),
            'meta' => [
                'current_page' => $merchants->currentPage(),
                'last_page' => $merchants->lastPage(),
                'per_page' => $merchants->perPage(),
                'total' => $merchants->total(),
            ],
        ]);
    }

    /**
     * Get single merchant details
     */
    public function show(int $id)
    {
        $merchant = Merchant::with(['wallet', 'apiKeys', 'stores', 'owner'])
            ->findOrFail($id);

        // Get recent transactions
        $recentTransactions = $merchant->transactions()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get recent settlements
        $recentSettlements = $merchant->settlements()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'merchant' => $merchant,
                'recent_transactions' => $recentTransactions,
                'recent_settlements' => $recentSettlements,
            ],
        ]);
    }

    /**
     * Update merchant status (activate, suspend, block)
     */
    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'blocked'])],
            'reason' => 'nullable|string|max:500',
        ]);

        $merchant = Merchant::findOrFail($id);

        $merchant->update([
            'status' => $request->status,
            'status_reason' => $request->reason,
            'status_updated_at' => now(),
            'status_updated_by' => $request->user()->id,
        ]);

        // If suspending/blocking, also revoke API keys
        if (in_array($request->status, ['suspended', 'blocked'])) {
            ApiKey::where('merchant_id', $id)
                ->where('status', 'active')
                ->update([
                    'status' => 'inactive',
                    'revoked_at' => now(),
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Merchant status updated successfully',
            'data' => $merchant->fresh(),
        ]);
    }

    /**
     * Verify merchant KYB
     */
    public function verifyKyb(Request $request, int $id)
    {
        $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected'])],
            'notes' => 'nullable|string|max:500',
        ]);

        $merchant = Merchant::findOrFail($id);

        $merchant->update([
            'kyb_status' => $request->status,
            'kyb_verified_at' => $request->status === 'verified' ? now() : null,
            'kyb_verified_by' => $request->user()->id,
            'kyb_rejection_reason' => $request->status === 'rejected' ? $request->notes : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'KYB verification updated successfully',
            'data' => $merchant->fresh(),
        ]);
    }

    /**
     * Update merchant fee tier
     */
    public function updateFeeTier(Request $request, int $id)
    {
        $request->validate([
            'fee_tier_id' => 'required|exists:fee_tiers,id',
        ]);

        $merchant = Merchant::findOrFail($id);

        $merchant->update([
            'fee_tier_id' => $request->fee_tier_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Merchant fee tier updated successfully',
            'data' => $merchant->fresh(),
        ]);
    }

    /**
     * Get merchant transactions
     */
    public function transactions(Request $request, int $id)
    {
        $merchant = Merchant::findOrFail($id);

        $query = $merchant->transactions();

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $perPage = $request->get('per_page', 20);
        $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Get merchant API keys
     */
    public function apiKeys(int $id)
    {
        $merchant = Merchant::findOrFail($id);

        $apiKeys = $merchant->apiKeys()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($key) {
                return [
                    'id' => $key->id,
                    'name' => $key->name,
                    'key_prefix' => $key->key_prefix,
                    'is_test_mode' => $key->is_test_mode,
                    'status' => $key->status,
                    'last_used_at' => $key->last_used_at,
                    'created_at' => $key->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $apiKeys,
        ]);
    }
}
