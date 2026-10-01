<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Hr\HrReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrReportController extends Controller
{
    public function __construct(
        protected HrReportService $reportService
    ) {}

    public function index(): View
    {
        return view('admin.hr.reports', [
            'departments' => Department::orderBy('name')->pluck('name'),
            'years' => range((int) now()->year, (int) now()->year - 2),
        ]);
    }

    public function daily(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        return response()->json($this->reportService->dailyReport($validated['date']));
    }

    public function monthly(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year'  => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        return response()->json($this->reportService->monthlyReport((int) $validated['year'], (int) $validated['month']));
    }

    public function range(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end'   => ['required', 'date_format:Y-m-d'],
        ]);

        return response()->json($this->reportService->rangeReport($validated['start'], $validated['end']));
    }

    public function employee(Employee $employee): JsonResponse
    {
        return response()->json($this->reportService->employeeHistory($employee));
    }
}
