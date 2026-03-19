<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class QrCodeController extends Controller
{
    /**
     * List merchant's QR codes
     */
    public function index(Request $request): JsonResponse
    {
        $merchant = $request->get('merchant');
        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = QrCode::where('merchant_id', $merchant->id)
            ->orderBy('created_at', 'desc');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('type')) {
            $query->where('qr_type', $type);
        }

        $qrCodes = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'qr_codes' => $qrCodes->map(fn($qr) => $this->formatQrCode($qr)),
                'pagination' => [
                    'current_page' => $qrCodes->currentPage(),
                    'last_page' => $qrCodes->lastPage(),
                    'per_page' => $qrCodes->perPage(),
                    'total' => $qrCodes->total(),
                ],
            ],
        ]);
    }

    /**
     * Create a new QR code
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:static,dynamic'],
            'amount' => ['required_if:type,dynamic', 'nullable', 'numeric', 'min:100'],
            'description' => ['sometimes', 'string', 'max:255'],
            'store_id' => ['sometimes', 'integer', 'exists:merchant_stores,id'],
            'valid_until' => ['sometimes', 'date', 'after:now'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $merchant = $request->get('merchant');

        // Verify store belongs to merchant
        if ($storeId = $request->input('store_id')) {
            $store = $merchant->stores()->find($storeId);
            if (!$store) {
                return response()->json([
                    'success' => false,
                    'message' => 'Store not found',
                ], 404);
            }
        }

        $uuid = Str::uuid();
        $qrData = $this->generateQrData($uuid, $request->input('amount'));

        $qrCode = QrCode::create([
            'uuid' => $uuid,
            'merchant_id' => $merchant->id,
            'store_id' => $request->input('store_id'),
            'qr_type' => $request->type,
            'amount' => $request->input('amount'),
            'description' => $request->input('description'),
            'qr_data' => $qrData,
            'valid_until' => $request->input('valid_until'),
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QR code created',
            'data' => $this->formatQrCode($qrCode),
        ], 201);
    }

    /**
     * Get QR code details
     */
    public function show(Request $request, QrCode $qrCode): JsonResponse
    {
        $merchant = $request->get('merchant');

        if ($qrCode->merchant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatQrCode($qrCode),
        ]);
    }

    /**
     * Update QR code
     */
    public function update(Request $request, QrCode $qrCode): JsonResponse
    {
        $merchant = $request->get('merchant');

        if ($qrCode->merchant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'description' => ['sometimes', 'string', 'max:255'],
            'valid_until' => ['sometimes', 'date', 'after:now'],
            'status' => ['sometimes', 'in:active,disabled'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $qrCode->update($request->only(['description', 'valid_until', 'status']));

        return response()->json([
            'success' => true,
            'message' => 'QR code updated',
            'data' => $this->formatQrCode($qrCode),
        ]);
    }

    /**
     * Delete QR code
     */
    public function destroy(Request $request, QrCode $qrCode): JsonResponse
    {
        $merchant = $request->get('merchant');

        if ($qrCode->merchant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not found',
            ], 404);
        }

        $qrCode->update(['status' => 'disabled']);

        return response()->json([
            'success' => true,
            'message' => 'QR code deleted',
        ]);
    }

    // Helper methods

    private function generateQrData(string $uuid, ?float $amount): string
    {
        if ($amount) {
            return "SP:{$uuid}:{$amount}";
        }
        return "SP:{$uuid}";
    }

    private function formatQrCode(QrCode $qrCode): array
    {
        return [
            'id' => $qrCode->uuid,
            'type' => $qrCode->qr_type,
            'amount' => $qrCode->amount ? (float) $qrCode->amount : null,
            'description' => $qrCode->description,
            'qr_data' => $qrCode->qr_data,
            'qr_image_url' => $qrCode->qr_image_url,
            'store_id' => $qrCode->store_id,
            'scan_count' => $qrCode->scan_count,
            'valid_until' => $qrCode->valid_until?->toISOString(),
            'status' => $qrCode->status,
            'created_at' => $qrCode->created_at->toISOString(),
        ];
    }
}
