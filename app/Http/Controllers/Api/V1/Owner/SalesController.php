<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Owner\InvoiceDetailResource;
use App\Http\Resources\Owner\InvoiceSummaryResource;
use App\Http\Resources\Owner\ReturnResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    /**
     * Live Invoices Feed (Recent sales stream).
     * Bounded pagination (5 to 50 items).
     */
    public function recentInvoices(Request $request): JsonResponse
    {
        $perPage = max(5, min(50, (int) $request->input('per_page', 20)));
        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;

        $query = Invoice::query()
            ->countable()
            ->with([
                'customer:id,name,phone',
                'cashier:id,name',
                'items:id,invoice_id,warranty_duration_months,battery_serial_number',
            ]);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $paginator = $query->latest('id')->paginate($perPage);

        return response()->json([
            'status'     => 'success',
            'data'       => InvoiceSummaryResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'has_more'     => $paginator->hasMorePages(),
            ],
        ]);
    }

    /**
     * Detailed read-only receipt breakdown.
     */
    public function showInvoice(Request $request, int|string $id): JsonResponse
    {
        $invoice = Invoice::query()
            ->with([
                'customer:id,name,phone,current_credit_balance',
                'cashier:id,name',
                'technician:id,full_name',
                'branch:id,name,code',
                'customerVehicle:id,plate_number,make,model',
                'items.product:id,name,sku,barcode,is_battery',
                'items.warranties',
                'scrapBattery',
                'payments',
            ])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => new InvoiceDetailResource($invoice),
        ]);
    }

    /**
     * Returns and refunds surveillance feed.
     */
    public function recentReturns(Request $request): JsonResponse
    {
        $perPage = max(5, min(50, (int) $request->input('per_page', 20)));
        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;

        $query = Invoice::query()
            ->whereIn('status', ['refunded', 'partially_refunded'])
            ->with([
                'customer:id,name,phone',
                'cashier:id,name',
                'items.product:id,name,sku',
            ]);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $paginator = $query->latest('updated_at')->paginate($perPage);

        return response()->json([
            'status'     => 'success',
            'data'       => ReturnResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'has_more'     => $paginator->hasMorePages(),
            ],
        ]);
    }
}
