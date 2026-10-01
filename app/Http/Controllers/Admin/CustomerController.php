<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Sales\CustomerServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Customers\CustomerVehicleRequest;
use App\Http\Requests\Admin\Customers\StoreCustomerRequest;
use App\Http\Requests\Admin\Customers\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerVehicle;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerServiceInterface $customerService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $filters = $request->only(['search', 'tier']);
        $customers = $this->customerService->getPaginatedCustomers($filters);

        if ($request->wantsJson()) {
            return response()->json($customers);
        }

        return view('admin.sales.customers', [
            'customers' => $customers,
            'stats'     => $this->customerService->getCustomerStats(),
            'filters'   => $filters,
        ]);
    }

    /**
     * Real customer lookup for other screens (POS, credit): active, non walk-in customers.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $customers = $this->customerService->search((string) $request->input('q', ''));

        return response()->json([
            'data' => $customers->map(fn(Customer $c) => [
                'id'                     => $c->id,
                'name'                   => $c->name,
                'phone'                  => $c->phone,
                'tier'                   => $c->tier,
                'credit_limit'           => (float) $c->credit_limit,
                'current_credit_balance' => (float) $c->current_credit_balance,
                'vehicles'               => $c->vehicles->map(fn(CustomerVehicle $v) => [
                    'id'           => $v->id,
                    'plate_number' => $v->plate_number,
                    'car_brand'    => $v->car_brand,
                    'car_model'    => $v->car_model,
                    'model_year'   => $v->model_year,
                ])->values(),
            ])->values(),
        ]);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json($this->customerService->getCustomerProfile($customer));
    }

    public function store(StoreCustomerRequest $request): RedirectResponse|JsonResponse
    {
        $customer = $this->customerService->createCustomer($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $customer], 201);
        }

        return redirect()->route('admin.sales.customers')
            ->with('status', "تم تسجيل العميل ({$customer->name}) بنجاح.");
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse|JsonResponse
    {
        $customer = $this->customerService->updateCustomer($customer, $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $customer]);
        }

        return redirect()->route('admin.sales.customers')
            ->with('status', "تم تحديث بيانات العميل ({$customer->name}) بنجاح.");
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse|JsonResponse
    {
        return $this->respond($request, fn() => $this->customerService->deleteCustomer($customer),
            "تم حذف العميل ({$customer->name}).");
    }

    public function storeVehicle(CustomerVehicleRequest $request, Customer $customer): RedirectResponse|JsonResponse
    {
        $vehicle = $this->customerService->addVehicle($customer, $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $vehicle], 201);
        }

        return redirect()->route('admin.sales.customers')->with('status', 'تم إضافة المركبة للعميل بنجاح.');
    }

    public function updateVehicle(CustomerVehicleRequest $request, Customer $customer, CustomerVehicle $vehicle): RedirectResponse|JsonResponse
    {
        $vehicle = $this->customerService->updateVehicle($vehicle, $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $vehicle]);
        }

        return redirect()->route('admin.sales.customers')->with('status', 'تم تحديث بيانات المركبة بنجاح.');
    }

    public function destroyVehicle(Request $request, Customer $customer, CustomerVehicle $vehicle): RedirectResponse|JsonResponse
    {
        return $this->respond($request, fn() => $this->customerService->deleteVehicle($vehicle),
            'تم حذف المركبة من ملف العميل.');
    }

    private function respond(Request $request, callable $action, string $successMessage): RedirectResponse|JsonResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->route('admin.sales.customers')->withErrors(['customer_error' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('admin.sales.customers')->with('status', $successMessage);
    }
}
