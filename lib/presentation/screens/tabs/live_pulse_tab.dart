import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/live_pulse_data.dart';
import '../../../providers/dashboard_provider.dart';
import '../../widgets/executive_app_bar.dart';
import '../../widgets/pulse/active_shift_card.dart';
import '../../widgets/pulse/attendance_pulse_card.dart';
import '../../widgets/pulse/critical_alerts_row.dart';
import '../../widgets/pulse/period_metrics_section.dart';
import '../../widgets/pulse/safe_cash_card.dart';
import '../../widgets/pulse/sales_today_card.dart';
import '../../widgets/state/async_state_view.dart';
import '../../widgets/state/stale_data_banner.dart';

class LivePulseTab extends StatefulWidget {
  const LivePulseTab({super.key});

  @override
  State<LivePulseTab> createState() => _LivePulseTabState();
}

class _LivePulseTabState extends State<LivePulseTab> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final dash = context.read<DashboardProvider>();
      dash.fetchLivePulse();
      dash.fetchPeriodMetrics('today');
    });
  }

  @override
  Widget build(BuildContext context) {
    final dash = context.watch<DashboardProvider>();
    final hasLiveData = dash.liveState.value != null;
    final showStaleBanner =
        hasLiveData && (dash.isStale || dash.lastPollFailure != null);

    return Scaffold(
      backgroundColor: AppColors.scaffoldBackground,
      appBar: ExecutiveAppBar(
        title: 'لوحة النبض المباشر',
        selectedBranchId: dash.selectedBranchId,
        onBranchChanged: dash.setSelectedBranch,
        onRefresh: () {
          dash.fetchLivePulse();
          dash.fetchPeriodMetrics(dash.selectedPeriod);
        },
      ),
      body: RefreshIndicator(
        color: AppColors.goldAccent,
        backgroundColor: AppColors.surface,
        onRefresh: () async {
          await dash.fetchLivePulse();
          await dash.fetchPeriodMetrics(dash.selectedPeriod);
        },
        child: Column(
          children: [
            if (showStaleBanner)
              StaleDataBanner(
                message:
                    dash.lastPollFailure?.message ?? 'البيانات قد تكون غير محدثة.',
                lastSync: dash.lastSyncTime,
                onRetry: dash.fetchLivePulse,
              ),
            Expanded(
              child: AsyncStateView<LivePulseData>(
                state: dash.liveState,
                onRetry: dash.fetchLivePulse,
                builder: (context, pulse) {
                  return _buildContent(context, dash, pulse);
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildContent(
    BuildContext context,
    DashboardProvider dash,
    LivePulseData pulse,
  ) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      physics: const AlwaysScrollableScrollPhysics(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SafeCashCard(data: pulse.safeCashNow),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(child: SalesTodayCard(data: pulse.salesToday)),
              const SizedBox(width: 12),
              Expanded(child: ActiveShiftCard(data: pulse.activeShift)),
            ],
          ),
          const SizedBox(height: 24),
          PeriodMetricsSection(
            state: dash.metricsState,
            selectedPeriod: dash.selectedPeriod,
            onPeriodSelected: (period) => dash.fetchPeriodMetrics(period),
            onRetry: () => dash.fetchPeriodMetrics(dash.selectedPeriod),
          ),
          const SizedBox(height: 24),
          const Text(
            'مؤشرات السلامة والتشغيل',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 12),
          CriticalAlertsRow(data: pulse.criticalAlerts),
          const SizedBox(height: 20),
          AttendancePulseCard(data: pulse.workshopAttendance),
        ],
      ),
    );
  }
}
