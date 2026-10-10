<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Owner\InventoryAlertResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * Operational Health: Low-stock and critical inventory alerts.
     * Orders items with fastest-depleting stock ratio first.
     */
    public function alerts(Request $request): JsonResponse
    {
        $perPage = max(5, min(50, (int) $request->input('per_page', 20)));

        $query = Product::query()
            ->active()
            ->lowStock()
            ->with('category:id,name')
            ->select([
                'id',
                'category_id',
                'name',
                'sku',
                'barcode',
                'current_stock',
                'reorder_threshold',
                'cost_price',
                'retail_price',
                'is_battery',
            ])
            ->orderByRaw('(current_stock * 1.0 / NULLIF(reorder_threshold, 0)) ASC')
            ->orderBy('current_stock', 'asc');

        if ($request->boolean('batteries_only')) {
            $query->batteriesOnly();
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'status'     => 'success',
            'data'       => InventoryAlertResource::collection($paginator->items()),
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
