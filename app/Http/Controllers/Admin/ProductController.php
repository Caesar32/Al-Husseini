<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Catalog\ProductServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Products\StoreProductRequest;
use App\Http\Requests\Admin\Products\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        protected ProductServiceInterface $productService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $filters = $request->only(['search', 'category', 'is_active']);
        $products = $this->productService->getPaginatedProducts($filters);

        if ($request->wantsJson()) {
            return response()->json($products);
        }

        return view('admin.sales.products', [
            'products'   => $products,
            'stats'      => $this->productService->getCatalogStats(),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'slug']),
            'filters'    => $filters,
        ]);
    }

    /**
     * Real catalog lookup for other screens (POS, purchases): active products only.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $products = $this->productService->search((string) $request->input('q', ''));

        return response()->json([
            'data' => $products->map(fn(Product $p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'brand'           => $p->brand,
                'sku'             => $p->sku,
                'barcode'         => $p->barcode,
                'capacity_ah'     => $p->capacity_ah,
                'retail_price'    => (float) $p->retail_price,
                'current_stock'   => (int) $p->current_stock,
                'is_battery'      => (bool) $p->is_battery,
                'warranty_months' => (int) $p->warranty_months,
                'category_slug'   => $p->category?->slug,
                'category_name'   => $p->category?->name,
            ])->values(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse|JsonResponse
    {
        $product = $this->productService->createProduct($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $product], 201);
        }

        return redirect()->route('admin.sales.products')
            ->with('status', "تم إضافة الصنف ({$product->name}) إلى الكتالوج بنجاح.");
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse|JsonResponse
    {
        $product = $this->productService->updateProduct($product, $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $product]);
        }

        return redirect()->route('admin.sales.products')
            ->with('status', "تم تحديث بيانات الصنف ({$product->name}) بنجاح.");
    }

    public function destroy(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        try {
            $this->productService->deleteProduct($product);
        } catch (DomainException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->route('admin.sales.products')->withErrors(['product_error' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('admin.sales.products')
            ->with('status', "تم حذف الصنف ({$product->name}) من الكتالوج.");
    }
}
