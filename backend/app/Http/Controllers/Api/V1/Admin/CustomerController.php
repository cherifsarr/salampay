<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\KycDocument;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * List customers with filtering and pagination
     */
    public function index(Request $request)
    {
        $query = User::where('type', 'customer')
            ->with(['wallet', 'kycDocuments']);

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by KYC status
        if ($kycStatus = $request->get('kyc_status')) {
            $query->where('kyc_status', $kycStatus);
        }

        // Filter by account status
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = $request->get('per_page', 20);
        $customers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $customers->items(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    /**
     * Get single customer details
     */
    public function show(int $id)
    {
        $customer = User::where('type', 'customer')
            ->with(['wallet', 'kycDocuments', 'profile'])
            ->findOrFail($id);

        // Get recent transactions
        $recentTransactions = $customer->transactions()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => $customer,
                'recent_transactions' => $recentTransactions,
            ],
        ]);
    }

    /**
     * Update customer status (activate, suspend, block)
     */
    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'blocked'])],
            'reason' => 'nullable|string|max:500',
        ]);

        $customer = User::where('type', 'customer')->findOrFail($id);

        $customer->update([
            'status' => $request->status,
            'status_reason' => $request->reason,
            'status_updated_at' => now(),
            'status_updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer status updated successfully',
            'data' => $customer->fresh(),
        ]);
    }

    /**
     * Verify customer KYC
     */
    public function verifyKyc(Request $request, int $id)
    {
        $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected'])],
            'notes' => 'nullable|string|max:500',
        ]);

        $customer = User::where('type', 'customer')->findOrFail($id);

        // Update all pending KYC documents
        KycDocument::where('user_id', $id)
            ->where('status', 'pending')
            ->update([
                'status' => $request->status,
                'verified_at' => $request->status === 'verified' ? now() : null,
                'verified_by' => $request->user()->id,
                'rejection_reason' => $request->status === 'rejected' ? $request->notes : null,
            ]);

        // Update user KYC status
        $customer->update([
            'kyc_status' => $request->status,
            'kyc_verified_at' => $request->status === 'verified' ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'KYC verification updated successfully',
            'data' => $customer->fresh(['kycDocuments']),
        ]);
    }

    /**
     * Get customer transaction history
     */
    public function transactions(Request $request, int $id)
    {
        $customer = User::where('type', 'customer')->findOrFail($id);

        $query = $customer->transactions();

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
}
