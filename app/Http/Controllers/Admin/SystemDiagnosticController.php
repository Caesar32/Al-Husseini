<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Diagnostics\SystemDiagnosticService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemDiagnosticController extends Controller
{
    public function __construct(
        protected SystemDiagnosticService $diagnosticService
    ) {}

    /**
     * عرض لوحة تحكم الفحص والتشخيص الحي للنظام
     */
    public function index(): View
    {
        $auditData = $this->diagnosticService->runFullAudit();

        return view('admin.diagnostics.index', compact('auditData'));
    }

    /**
     * تشغيل فحص تدقيق سلامة قاعدة البيانات عبر AJAX
     */
    public function runAudit(Request $request): JsonResponse
    {
        $auditData = $this->diagnosticService->runFullAudit();

        return response()->json([
            'status' => 'success',
            'data'   => $auditData,
        ]);
    }

    /**
     * تشغيل محاكاة دورة الأعمال الشاملة عبر AJAX
     */
    public function runSimulation(Request $request): JsonResponse
    {
        $rollback = $request->boolean('rollback', true);
        $simulationData = $this->diagnosticService->runLiveSimulation($rollback);

        return response()->json([
            'status' => 'success',
            'data'   => $simulationData,
        ]);
    }
}
