<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $merchant = $request->get('merchant');
        $merchant->load(['user', 'stores']);

        return response()->json([
            'success' => true,
            'data' => [
                'merchant' => [
                    'id' => $merchant->uuid,
                    'business_name' => $merchant->business_name,
                    'business_type' => $merchant->business_type,
                    'registration_number' => $merchant->registration_number,
                    'industry_code' => $merchant->industry_code,
                    'website' => $merchant->website,
                    'description' => $merchant->description,
                    'logo_url' => $merchant->logo_url,
                    'kyb_status' => $merchant->kyb_status,
                    'settlement_schedule' => $merchant->settlement_schedule,
                    'status' => $merchant->status,
                    'created_at' => $merchant->created_at->toISOString(),
                ],
                'owner' => [
                    'name' => $merchant->user->name,
                    'email' => $merchant->user->email,
                    'phone' => $merchant->user->phone,
                ],
                'stores' => $merchant->stores->map(fn($store) => [
                    'id' => $store->uuid,
                    'store_name' => $store->store_name,
                    'store_code' => $store->store_code,
                    'address' => $store->address_line1,
                    'city' => $store->city,
                    'status' => $store->status,
                ]),
            ],
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        $merchant = $request->get('merchant');
        $wallet = $merchant->wallet;

        if (!$wallet) {
            return response()->json([
                'success' => true,
                'data' => [
                    'balance' => 0,
                    'available_balance' => 0,
                    'pending_balance' => 0,
                    'currency' => 'XOF',
                ],
            ]);
        }

        // Calculate pending balance from unsettled transactions
        $pendingBalance = $merchant->transactions()
            ->where('status', 'completed')
            ->whereNull('settlement_batch_id')
            ->sum('net_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => (float) $wallet->balance,
                'available_balance' => (float) $wallet->getAvailableBalance(),
                'pending_balance' => (float) $pendingBalance,
                'currency' => $wallet->currency,
            ],
        ]);
    }
}
