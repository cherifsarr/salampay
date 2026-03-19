<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $merchant = $request->get('merchant');
        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = Invoice::where('merchant_id', $merchant->id)
            ->orderBy('created_at', 'desc');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $invoices = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'invoices' => $invoices->map(fn($inv) => $this->formatInvoice($inv)),
                'pagination' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['sometimes', 'email'],
            'customer_phone' => ['sometimes', 'string', 'max:20'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['sometimes', 'string', 'max:1000'],
            'terms' => ['sometimes', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $merchant = $request->get('merchant');

        // Calculate totals
        $subtotal = collect($request->line_items)->sum(function ($item) {
            return $item['quantity'] * $item['unit_price'];
        });

        $discountAmount = $request->input('discount_amount', 0);
        $taxRate = $request->input('tax_rate', 0);
        $taxAmount = ($subtotal - $discountAmount) * ($taxRate / 100);
        $totalAmount = $subtotal - $discountAmount + $taxAmount;

        $invoice = Invoice::create([
            'uuid' => Str::uuid(),
            'invoice_number' => $this->generateInvoiceNumber($merchant),
            'merchant_id' => $merchant->id,
            'customer_name' => $request->customer_name,
            'customer_email' => $request->input('customer_email'),
            'customer_phone' => $request->input('customer_phone'),
            'line_items' => $request->line_items,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'currency' => 'XOF',
            'issue_date' => now()->toDateString(),
            'due_date' => $request->due_date,
            'notes' => $request->input('notes'),
            'terms' => $request->input('terms'),
            'status' => 'draft',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invoice created',
            'data' => $this->formatInvoice($invoice),
        ], 201);
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $merchant = $request->get('merchant');

        if ($invoice->merchant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatInvoice($invoice),
        ]);
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $merchant = $request->get('merchant');

        if ($invoice->merchant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        if (!in_array($invoice->status, ['draft', 'sent'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update paid or cancelled invoice',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'customer_name' => ['sometimes', 'string', 'max:255'],
            'customer_email' => ['sometimes', 'email'],
            'due_date' => ['sometimes', 'date', 'after_or_equal:today'],
            'notes' => ['sometimes', 'string', 'max:1000'],
            'status' => ['sometimes', 'in:draft,sent,cancelled'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $invoice->update($request->only([
            'customer_name', 'customer_email', 'due_date', 'notes', 'status'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Invoice updated',
            'data' => $this->formatInvoice($invoice),
        ]);
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        $merchant = $request->get('merchant');

        if ($invoice->merchant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        if ($invoice->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete paid invoice',
            ], 400);
        }

        $invoice->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Invoice cancelled',
        ]);
    }

    public function send(Request $request, Invoice $invoice): JsonResponse
    {
        $merchant = $request->get('merchant');

        if ($invoice->merchant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        if (!$invoice->customer_email && !$invoice->customer_phone) {
            return response()->json([
                'success' => false,
                'message' => 'Customer email or phone required to send invoice',
            ], 400);
        }

        // TODO: Send invoice via email/SMS
        $invoice->update(['status' => 'sent']);

        return response()->json([
            'success' => true,
            'message' => 'Invoice sent',
            'data' => $this->formatInvoice($invoice),
        ]);
    }

    private function generateInvoiceNumber($merchant): string
    {
        $prefix = 'INV';
        $year = date('Y');
        $count = Invoice::where('merchant_id', $merchant->id)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('%s-%s-%05d', $prefix, $year, $count);
    }

    private function formatInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->uuid,
            'invoice_number' => $invoice->invoice_number,
            'customer' => [
                'name' => $invoice->customer_name,
                'email' => $invoice->customer_email,
                'phone' => $invoice->customer_phone,
            ],
            'line_items' => $invoice->line_items,
            'subtotal' => (float) $invoice->subtotal,
            'tax_amount' => (float) $invoice->tax_amount,
            'discount_amount' => (float) $invoice->discount_amount,
            'total_amount' => (float) $invoice->total_amount,
            'currency' => $invoice->currency,
            'issue_date' => $invoice->issue_date,
            'due_date' => $invoice->due_date,
            'paid_at' => $invoice->paid_at?->toISOString(),
            'notes' => $invoice->notes,
            'terms' => $invoice->terms,
            'status' => $invoice->status,
            'created_at' => $invoice->created_at->toISOString(),
        ];
    }
}
