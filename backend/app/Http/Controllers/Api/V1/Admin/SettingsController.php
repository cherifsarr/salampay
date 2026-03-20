<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Accounting\Models\FeeConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    /**
     * Get admin profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'type' => $user->type,
                'created_at' => $user->created_at,
                'last_login_at' => $user->last_login_at,
            ],
        ]);
    }

    /**
     * Update admin profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|max:20',
        ]);

        $user->update($request->only(['first_name', 'last_name', 'email', 'phone']));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $user->fresh(),
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Get fee configurations
     */
    public function getFees()
    {
        $fees = FeeConfiguration::where('is_active', true)
            ->orderBy('transaction_type')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $fees,
        ]);
    }

    /**
     * Update fee configuration
     */
    public function updateFee(Request $request, int $id)
    {
        $request->validate([
            'percentage_rate' => 'sometimes|numeric|min:0|max:100',
            'fixed_amount' => 'sometimes|numeric|min:0',
            'min_fee' => 'sometimes|numeric|min:0',
            'max_fee' => 'sometimes|nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $fee = FeeConfiguration::findOrFail($id);

        $fee->update($request->only([
            'percentage_rate',
            'fixed_amount',
            'min_fee',
            'max_fee',
            'is_active',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Fee configuration updated',
            'data' => $fee->fresh(),
        ]);
    }

    /**
     * Create fee configuration
     */
    public function createFee(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'transaction_type' => 'required|string|max:50',
            'fee_type' => 'required|in:percentage,fixed,mixed',
            'percentage_rate' => 'required_if:fee_type,percentage,mixed|numeric|min:0|max:100',
            'fixed_amount' => 'required_if:fee_type,fixed,mixed|numeric|min:0',
            'min_fee' => 'nullable|numeric|min:0',
            'max_fee' => 'nullable|numeric|min:0',
            'applies_to' => 'nullable|in:customer,merchant,all',
        ]);

        $fee = FeeConfiguration::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => $request->name,
            'transaction_type' => $request->transaction_type,
            'fee_type' => $request->fee_type,
            'percentage_rate' => $request->percentage_rate ?? 0,
            'fixed_amount' => $request->fixed_amount ?? 0,
            'min_fee' => $request->min_fee ?? 0,
            'max_fee' => $request->max_fee,
            'applies_to' => $request->applies_to ?? 'all',
            'is_active' => true,
            'effective_from' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fee configuration created',
            'data' => $fee,
        ], 201);
    }

    /**
     * Get system settings
     */
    public function getSystemSettings()
    {
        // In a real app, these would come from a settings table
        $settings = [
            'platform_name' => config('app.name'),
            'default_currency' => 'XOF',
            'maintenance_mode' => app()->isDownForMaintenance(),
            'max_transaction_amount' => config('salampay.max_transaction_amount', 10000000),
            'min_transaction_amount' => config('salampay.min_transaction_amount', 100),
            'daily_limit_default' => config('salampay.daily_limit_default', 5000000),
            'monthly_limit_default' => config('salampay.monthly_limit_default', 50000000),
        ];

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Get activity log for admin
     */
    public function activityLog(Request $request)
    {
        // In a real app, you'd have an activity log table
        // For now, return empty array
        return response()->json([
            'success' => true,
            'data' => [],
            'message' => 'Activity logging not yet implemented',
        ]);
    }
}
