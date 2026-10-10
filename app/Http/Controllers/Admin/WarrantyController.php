<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Sales\WarrantyServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Warranties\ProcessWarrantyClaimRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarrantyController extends Controller
{
    public function __construct(
        protected WarrantyServiceInterface $warrantyService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $filters = $request->only(['search', 'decision', 'supplier_resolution', 'supplier_id', 'branch_id']);
        $claims = $this->warrantyService->getPaginatedClaims($filters);

        if ($request->wantsJson()) {
            return response()->json($claims);
        }

        $activeWarrantiesCount = Warranty::where('status', 'active')->count();
        $pendingClaimsCount = WarrantyClaim::where('supplier_resolution', 'pending')->count();
        $suppliers = Supplier::where('is_active', true)->get();
        $technicians = Employee::where('status', 'active')->get();
        $replacementProducts = Product::where('is_battery', true)->where('is_active', true)->where('current_stock', '>', 0)->get();

        return view('admin.warranties.index', compact(
            'claims',
            'activeWarrantiesCount',
            'pendingClaimsCount',
            'suppliers',
            'technicians',
            'replacementProducts'
        ));
    }

    public function verify(Request $request): View|JsonResponse
    {
        $serial = (string) $request->get('serial_number', '');

        // Web view when accessed directly in browser without serial or with view=1
        if (!$request->expectsJson() && !$request->ajax() && (!$request->has('serial_number') || $request->has('view'))) {
            $result = null;
            if (trim($serial) !== '') {
                $result = $this->warrantyService->verifyBatterySerial($serial);
            }

            $activeWarrantiesCount = Warranty::where('status', 'active')->count();
            $pendingClaimsCount = WarrantyClaim::where('supplier_resolution', 'pending')->count();
            $suppliers = Supplier::where('is_active', true)->get();
            $technicians = Employee::where('status', 'active')->get();
            $replacementProducts = Product::where('is_battery', true)->where('is_active', true)->where('current_stock', '>', 0)->get();

            return view('admin.warranties.verify', compact(
                'serial',
                'result',
                'activeWarrantiesCount',
                'pendingClaimsCount',
                'suppliers',
                'technicians',
                'replacementProducts'
            ));
        }

        // JSON API lookup for tests and dynamic fetch calls
        if (trim($serial) === '') {
            return response()->json([
                'exists'   => false,
                'is_valid' => false,
                'message'  => 'يرجى إدخال سيريال البطارية للبحث.',
            ], 422);
        }

        $result = $this->warrantyService->verifyBatterySerial($serial);

        return response()->json($result);
    }

    public function storeClaim(ProcessWarrantyClaimRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $claim = $this->warrantyService->processInstantClaim($request->validated(), (int) auth()->id());

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "تم تسجيل مطالبة الضمان بنجاح برقم ({$claim->claim_number}).",
                    'data'    => $claim,
                ], 201);
            }

            return redirect()->route('admin.warranties.index')
                ->with('status', "تم تسجيل مطالبة الضمان بنجاح برقم ({$claim->claim_number}) وصرف البديل للعميل.");
        } catch (\DomainException|\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['claim_error' => $e->getMessage()])->withInput();
        }
    }

    public function settleSupplier(Request $request, WarrantyClaim $claim): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'action'        => ['required', 'in:sent_to_supplier,settled_replacement,settled_credit_note,rejected'],
            'credit_amount' => ['nullable', 'numeric', 'min:0'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ], [
            'action.required' => 'يرجى تحديد نوع إجراء التسوية مع المورد.',
        ]);

        try {
            $settledClaim = $this->warrantyService->settleClaimWithSupplier(
                $claim->id,
                $validated['action'],
                $validated,
                (int) auth()->id()
            );
        } catch (\DomainException|\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['settle_error' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث وتسوية حالة المطالبة مع المورد بنجاح.',
                'data'    => $settledClaim,
            ]);
        }

        return redirect()->route('admin.warranties.index')
            ->with('status', 'تمت تسوية المطالبة مع المورد بنجاح.');
    }
}
