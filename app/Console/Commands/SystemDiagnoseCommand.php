<?php

namespace App\Console\Commands;

use App\Services\Diagnostics\SystemDiagnosticService;
use Illuminate\Console\Command;

class SystemDiagnoseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:diagnose 
                            {--audit : تنفيذ فحص وتدقيق سلامة قاعدة البيانات فقط}
                            {--simulate : تنفيذ محاكاة دورة الأعمال الشاملة}
                            {--keep : الاحتفاظ بالبيانات التجريبية الناتجة عن المحاكاة وعدم التراجع}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'أداة الفحص والتدقيق والمحاكاة التشغيلية الشاملة لمنظومة مجموعة الحسيني';

    /**
     * Execute the console command.
     */
    public function handle(SystemDiagnosticService $service): int
    {
        $this->output->title('🏢 منظومة الفحص والتشخيص والمحاكاة الذاتية - مجموعة الحسيني');

        $runAudit = $this->option('audit') || (!$this->option('audit') && !$this->option('simulate'));
        $runSimulate = $this->option('simulate') || (!$this->option('audit') && !$this->option('simulate'));
        $rollback = !$this->option('keep');

        $overallExitCode = self::SUCCESS;

        // ─── 1. فحص سلامة البيانات (Data Integrity Audit) ───────────────────
        if ($runAudit) {
            $this->info('🔍 جاري تشغيل فحص تدقيق سلامة البيانات والاتساق المحاسبي...');
            $auditRes = $service->runFullAudit();

            $rows = [];
            foreach ($auditRes['checks'] as $check) {
                $statusBadge = match ($check['status']) {
                    'passed'  => '<info>✔ اجتياز (PASSED)</info>',
                    'warning' => '<comment>⚠ تنبيه (WARNING)</comment>',
                    'failed'  => '<error>✘ فشل (FAILED)</error>',
                    default   => $check['status'],
                };

                $rows[] = [
                    $check['name'],
                    $statusBadge,
                    $check['details'],
                ];
            }

            $this->table(['معيار الفحص', 'الحالة', 'النتيجة والتفاصيل'], $rows);

            $this->line("مؤشر صحة وسلامة البيانات: <fg=green;options=bold>{$auditRes['health_score']}%</> ({$auditRes['passed_count']} من {$auditRes['total_checks']} اجتازوا بنجاح) في {$auditRes['duration_ms']}ms");
            $this->newLine();

            if (!empty($auditRes['issues'])) {
                $this->warn('تم رصد الملاحظات التالية:');
                foreach ($auditRes['issues'] as $issue) {
                    $this->line(" - <fg=yellow>{$issue}</>");
                }
                $this->newLine();
            }
        }

        // ─── 2. محاكاة دورة العمل الشاملة (Full Lifecycle Simulation) ──────
        if ($runSimulate) {
            $this->info('🚀 جاري بدء محاكاة دورة الأعمال الشاملة (End-to-End Live Simulation)...');
            $simRes = $service->runLiveSimulation($rollback);

            $simRows = [];
            foreach ($simRes['steps'] as $step) {
                $statusBadge = $step['status'] === 'passed'
                    ? '<info>✔ نجاح (SUCCESS)</info>'
                    : '<error>✘ فشل (FAILURE)</error>';

                $simRows[] = [
                    $step['sector'],
                    $step['name'],
                    $statusBadge,
                    $step['duration'] . ' ms',
                    $step['details'],
                ];
            }

            $this->table(['القطاع الوظيفي', 'العملية والمحاكاة', 'النتيجة', 'الوقت', 'التفاصيل والمعادلات'], $simRows);

            if ($simRes['all_passed']) {
                $this->info("🎉 كافة سيناريوهات العمل والعمليات الحسابية اجتازت المحاكاة بنجاح 100% ({$simRes['passed_count']}/{$simRes['total_steps']}) في {$simRes['execution_time']}ms.");
                if ($rollback) {
                    $this->comment('ℹ️ تم التراجع التلقائي عن البيانات التجريبية (Rollback) لحماية قاعدة البيانات من أي شوائب.');
                }
            } else {
                $this->error("❌ تم رصد خلل أو عدم تطابق في بعض العمليات الحسابية ({$simRes['passed_count']}/{$simRes['total_steps']}) اجتازت فقط.");
                $overallExitCode = self::FAILURE;
            }
        }

        return $overallExitCode;
    }
}
