<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\PaymentLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentLinkController extends Controller
{
    /**
     * List payment links
     */
    public function index(Request $request): JsonResponse
    {
        $merchant = $request->get('merchant');
        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = PaymentLink::where('merchant_id', $merchant->id)
            ->orderBy('created_at', 'desc');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $links = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'payment_links' => $links->map(fn($link) => $this->formatPaymentLink($link)),
                'pagination' => [
                    'current_page' => $links->currentPage(),
                    'last_page' => $links->lastPage(),
                    'per_page' => $links->perPage(),
                    'total' => $links->total(),
                ],
            ],
        ]);
    }

    /**
     * Create a payment link
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'amount' => ['sometimes', 'numeric', 'min:100'],
            'allow_tip' => ['sometimes', 'boolean'],
            'max_uses' => ['sometimes', 'integer', 'min:1'],
            'valid_until' => ['sometimes', 'date', 'after:now'],
            'redirect_url' => ['sometimes', 'url'],
            'metadata' => ['sometimes', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $merchant = $request->get('merchant');

        $paymentLink = PaymentLink::create([
            'uuid' => Str::uuid(),
            'merchant_id' => $merchant->id,
            'short_code' => $this->generateShortCode(),
            'title' => $request->title,
            'description' => $request->input('description'),
            'amount' => $request->input('amount'),
            'currency' => 'XOF',
            'allow_tip' => $request->input('allow_tip', false),
            'max_uses' => $request->input('max_uses'),
            'valid_until' => $request->input('valid_until'),
            'redirect_url' => $request->input('redirect_url'),
            'metadata' => $request->input('metadata'),
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment link created',
            'data' => $this->formatPaymentLink($paymentLink),
        ], 201);
    }

    /**
     * Get payment link details
     */
    public function show(Request $request, PaymentLink $paymentLink): JsonResponse
    {
        $merchant = $request->get('merchant');

        if ($paymentLink->merchant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Payment link not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatPaymentLink($paymentLink),
        ]);
    }

    /**
     * Update payment link
     */
    public function update(Request $request, PaymentLink $paymentLink): JsonResponse
    {
        $merchant = $request->get('merchant');

        if ($paymentLink->merchant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Payment link not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'valid_until' => ['sometimes', 'date', 'after:now'],
            'redirect_url' => ['sometimes', 'url'],
            'status' => ['sometimes', 'in:active,disabled'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $paymentLink->update($request->only([
            'title', 'description', 'valid_until', 'redirect_url', 'status'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Payment link updated',
            'data' => $this->formatPaymentLink($paymentLink),
        ]);
    }

    /**
     * Delete payment link
     */
    public function destroy(Request $request, PaymentLink $paymentLink): JsonResponse
    {
        $merchant = $request->get('merchant');

        if ($paymentLink->merchant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Payment link not found',
            ], 404);
        }

        $paymentLink->update(['status' => 'disabled']);

        return response()->json([
            'success' => true,
            'message' => 'Payment link deleted',
        ]);
    }

    // Helper methods

    private function generateShortCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (PaymentLink::where('short_code', $code)->exists());

        return $code;
    }

    private function formatPaymentLink(PaymentLink $link): array
    {
        return [
            'id' => $link->uuid,
            'short_code' => $link->short_code,
            'url' => config('app.url') . '/pay/' . $link->short_code,
            'title' => $link->title,
            'description' => $link->description,
            'amount' => $link->amount ? (float) $link->amount : null,
            'currency' => $link->currency,
            'allow_tip' => $link->allow_tip,
            'max_uses' => $link->max_uses,
            'use_count' => $link->use_count,
            'valid_until' => $link->valid_until?->toISOString(),
            'redirect_url' => $link->redirect_url,
            'metadata' => $link->metadata,
            'status' => $link->status,
            'created_at' => $link->created_at->toISOString(),
        ];
    }
}
