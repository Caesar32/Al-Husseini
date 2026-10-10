import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../providers/dashboard_provider.dart';
import '../../widgets/arabic_tabular_text.dart';
import '../../widgets/executive_app_bar.dart';
import '../../widgets/executive_kpi_card.dart';
import '../../widgets/status_badge.dart';

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
      final dash = Provider.of<DashboardProvider>(context, listen: false);
      dash.fetchLivePulse();
      dash.fetchPeriodMetrics('today');
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.scaffoldBackground,
      appBar: ExecutiveAppBar(
        title: 'لوحة النبض المباشر',
        selectedBranchId: Provider.of<DashboardProvider>(context).selectedBranchId,
        onBranchChanged: (branchId) {
          Provider.of<DashboardProvider>(context, listen: false).setSelectedBranch(branchId);
        },
        onRefresh: () {
          final dash = Provider.of<DashboardProvider>(context, listen: false);
          dash.fetchLivePulse();
          dash.fetchPeriodMetrics(dash.selectedPeriod);
        },
      ),
      body: RefreshIndicator(
        color: AppColors.goldAccent,
        backgroundColor: AppColors.surface,
        onRefresh: () async {
          final dash = Provider.of<DashboardProvider>(context, listen: false);
          await dash.fetchLivePulse();
          await dash.fetchPeriodMetrics(dash.selectedPeriod);
        },
        child: Consumer<DashboardProvider>(
          builder: (context, dash, _) {
            if (dash.isLoadingLive && dash.livePulse == null) {
              return const Center(
                child: CircularProgressIndicator(color: AppColors.goldAccent),
              );
            }

            if (dash.liveError != null && dash.livePulse == null) {
              return Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.wifi_off_rounded, size: 48, color: AppColors.stockDepletedDanger),
                    const SizedBox(height: 16),
                    Text(dash.liveError!, style: GoogleFonts.almarai(color: AppColors.textSecondary)),
                    const SizedBox(height: 16),
                    ElevatedButton(
                      onPressed: () => dash.fetchLivePulse(),
                      child: const Text('إعادة المحاولة'),
                    ),
                  ],
                ),
              );
            }

            final pulse = dash.livePulse;
            final period = dash.periodMetrics;

            return SingleChildScrollView(
              padding: const EdgeInsets.all(16.0),
              physics: const AlwaysScrollableScrollPhysics(),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Section 1: Hero KPI Cards Grid
                  if (pulse != null) ...[
                    // Safe Cash Hero Card (Full Width)
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        gradient: AppColors.primaryGradient,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: AppColors.goldAccent, width: 1.5),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.primaryDark.withValues(alpha: 0.5),
                            blurRadius: 15,
                            offset: const Offset(0, 6),
                          ),
                        ],
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Expanded(
                                child: Row(
                                  children: [
                                    const Icon(Icons.account_balance_wallet, color: AppColors.goldAccent, size: 24),
                                    const SizedBox(width: 8),
                                    Expanded(
                                      child: Text(
                                        pulse.safeCashNow.label,
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: GoogleFonts.cairo(
                                          fontSize: 14,
                                          fontWeight: FontWeight.bold,
                                          color: AppColors.textSecondary,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(width: 8),
                              StatusBadge.success(label: 'آمن بـ 100%'),
                            ],
                          ),
                          const SizedBox(height: 12),
                          ArabicTabularText(
                            text: pulse.safeCashNow.exact,
                            fontSize: 32,
                            fontWeight: FontWeight.w800,
                            textColor: AppColors.goldAccent,
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'يشمل مقبوضات مبيعات الكاش والتحصيلات النقدية اليوم',
                            style: GoogleFonts.almarai(
                              fontSize: 11,
                              color: AppColors.textMuted,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Sales Today + Active Shift Dual Cards
                    Row(
                      children: [
                        Expanded(
                          child: ExecutiveKpiCard(
                            title: 'مبيعات اليوم',
                            value: pulse.salesToday.formatted,
                            subtitle: '${pulse.salesToday.invoicesCount} فاتورة صادرة',
                            trendText: pulse.salesToday.comparedToYesterday,
                            isPositiveTrend: !pulse.salesToday.comparedToYesterday.contains('-'),
                            icon: Icons.trending_up_rounded,
                            accentColor: AppColors.safeCashSuccess,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: ExecutiveKpiCard(
                            title: 'الوردية الحالية',
                            value: pulse.activeShift.isOpen ? pulse.activeShift.cashierName : 'مغلقة',
                            subtitle: pulse.activeShift.isOpen ? 'فتح: ${pulse.activeShift.openedAt}' : 'لا يوجد كاشير متواجد',
                            icon: Icons.badge_outlined,
                            accentColor: pulse.activeShift.isOpen ? AppColors.bankCardInfo : AppColors.warningLateness,
                          ),
                        ),
                      ],
                    ),
                  ],

                  const SizedBox(height: 24),

                  // Section 2: Period Metrics Toggle & Breakdown
                  Text(
                    'تحليل المبيعات حسب الفترة',
                    style: GoogleFonts.cairo(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 12),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        _buildPeriodChip(context, 'today', 'اليوم'),
                        _buildPeriodChip(context, 'week', 'هذا الأسبوع'),
                        _buildPeriodChip(context, 'month', 'هذا الشهر'),
                        _buildPeriodChip(context, 'year', 'هذا العام'),
                        _buildPeriodChip(context, 'all', 'الكل'),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),

                  if (dash.isLoadingMetrics)
                    const Padding(padding: EdgeInsets.all(20), child: Center(child: CircularProgressIndicator(color: AppColors.goldAccent)))
                  else if (period != null)
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: AppColors.surface,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: AppColors.strokeBorder),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceAround,
                        children: [
                          Column(
                            children: [
                              Text('إجمالي الإيرادات', style: GoogleFonts.almarai(fontSize: 12, color: AppColors.textMuted)),
                              const SizedBox(height: 4),
                              ArabicTabularText(text: period.revenue.compact, fontSize: 18, textColor: AppColors.goldAccent),
                            ],
                          ),
                          Container(width: 1, height: 36, color: AppColors.strokeBorder),
                          Column(
                            children: [
                              Text('عدد الفواتير', style: GoogleFonts.almarai(fontSize: 12, color: AppColors.textMuted)),
                              const SizedBox(height: 4),
                              ArabicTabularText(text: '${period.totalSalesCount}', fontSize: 18, isCurrency: false),
                            ],
                          ),
                          Container(width: 1, height: 36, color: AppColors.strokeBorder),
                          Column(
                            children: [
                              Text('مقبوضات الآجل', style: GoogleFonts.almarai(fontSize: 12, color: AppColors.textMuted)),
                              const SizedBox(height: 4),
                              ArabicTabularText(text: period.creditCollected.compact, fontSize: 18, textColor: AppColors.safeCashSuccess),
                            ],
                          ),
                        ],
                      ),
                    ),

                  const SizedBox(height: 24),

                  // Section 3: Critical Operational Health Badges
                  if (pulse != null) ...[
                    Text(
                      'مؤشرات السلامة والتشغيل',
                      style: GoogleFonts.cairo(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 12),

                    Row(
                      children: [
                        Expanded(
                          child: _buildHealthBadge(
                            context,
                            title: 'نواقص المخزون',
                            count: '${pulse.criticalAlerts.lowStockCount}',
                            unit: 'صنف',
                            color: pulse.criticalAlerts.lowStockCount > 0 ? AppColors.stockDepletedDanger : AppColors.safeCashSuccess,
                            icon: Icons.inventory_2_outlined,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: _buildHealthBadge(
                            context,
                            title: 'مرتجعات اليوم',
                            count: '${pulse.criticalAlerts.returnsTodayCount}',
                            unit: 'عملية',
                            color: pulse.criticalAlerts.returnsTodayCount > 0 ? AppColors.warningLateness : AppColors.safeCashSuccess,
                            icon: Icons.assignment_return_outlined,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: _buildHealthBadge(
                            context,
                            title: 'عملاء بالآجل',
                            count: '${pulse.criticalAlerts.unsettledCreditCount}',
                            unit: 'عميل',
                            color: AppColors.bankCardInfo,
                            icon: Icons.credit_card_outlined,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),

                    // Section 4: Workshop Attendance Gauge
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: AppColors.surface,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: AppColors.strokeBorder),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Expanded(
                                child: Text(
                                  'حضور الفنيين والورشة اليوم',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: GoogleFonts.cairo(fontSize: 14, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                                ),
                              ),
                              const SizedBox(width: 8),
                              StatusBadge.info(label: pulse.workshopAttendance.attendanceRate),
                            ],
                          ),
                          const SizedBox(height: 12),
                          ClipRRect(
                            borderRadius: BorderRadius.circular(8),
                            child: LinearProgressIndicator(
                              value: pulse.workshopAttendance.totalEmployees > 0
                                  ? pulse.workshopAttendance.presentCount / pulse.workshopAttendance.totalEmployees
                                  : 0,
                              minHeight: 8,
                              backgroundColor: AppColors.elevatedSurface,
                              color: AppColors.safeCashSuccess,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'تواجد ${pulse.workshopAttendance.presentCount} فني من أصل ${pulse.workshopAttendance.totalEmployees} موظف مقيد بالمحل',
                            style: GoogleFonts.almarai(fontSize: 11, color: AppColors.textMuted),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  Widget _buildPeriodChip(BuildContext context, String periodKey, String label) {
    final dash = Provider.of<DashboardProvider>(context);
    final isSelected = dash.selectedPeriod == periodKey;

    return Padding(
      padding: const EdgeInsets.only(left: 8.0),
      child: ChoiceChip(
        label: Text(label),
        selected: isSelected,
        selectedColor: AppColors.goldAccent,
        backgroundColor: AppColors.elevatedSurface,
        labelStyle: GoogleFonts.cairo(
          fontSize: 12,
          fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
          color: isSelected ? Colors.black : AppColors.textSecondary,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: BorderSide(
            color: isSelected ? AppColors.goldAccent : AppColors.strokeBorder,
          ),
        ),
        onSelected: (_) {
          dash.fetchPeriodMetrics(periodKey);
        },
      ),
    );
  }

  Widget _buildHealthBadge(
    BuildContext context, {
    required String title,
    required String count,
    required String unit,
    required Color color,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 18),
          const SizedBox(height: 8),
          Text(title, style: GoogleFonts.almarai(fontSize: 11, color: AppColors.textMuted)),
          const SizedBox(height: 4),
          Row(
            children: [
              Flexible(
                child: Text(
                  count,
                  overflow: TextOverflow.ellipsis,
                  style: GoogleFonts.outfit(fontSize: 18, fontWeight: FontWeight.bold, color: color),
                ),
              ),
              const SizedBox(width: 4),
              Text(unit, style: GoogleFonts.almarai(fontSize: 10, color: AppColors.textMuted)),
            ],
          ),
        ],
      ),
    );
  }
}
