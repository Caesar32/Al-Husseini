import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../data/models/staff_attendance.dart';
import '../../../providers/dashboard_provider.dart';
import '../../../providers/staff_provider.dart';
import '../../widgets/executive_app_bar.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/state/async_state_view.dart';

class StaffAttendanceTab extends StatefulWidget {
  const StaffAttendanceTab({super.key});

  @override
  State<StaffAttendanceTab> createState() => _StaffAttendanceTabState();
}

class _StaffAttendanceTabState extends State<StaffAttendanceTab> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final branchId =
          Provider.of<DashboardProvider>(context, listen: false)
              .selectedBranchId;
      Provider.of<StaffProvider>(context, listen: false)
          .fetchTodayAttendance(branchId: branchId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final branchId = Provider.of<DashboardProvider>(context).selectedBranchId;

    return Scaffold(
      backgroundColor: AppColors.scaffoldBackground,
      appBar: ExecutiveAppBar(
        title: 'رقابة حضور الفنيين والورشة',
        selectedBranchId: branchId,
        onBranchChanged: (branchId) {
          Provider.of<DashboardProvider>(context, listen: false)
              .setSelectedBranch(branchId);
          Provider.of<StaffProvider>(context, listen: false)
              .fetchTodayAttendance(branchId: branchId);
        },
        onRefresh: () {
          Provider.of<StaffProvider>(context, listen: false)
              .fetchTodayAttendance(branchId: branchId);
        },
      ),
      body: Consumer<StaffProvider>(
        builder: (context, staff, _) {
          return AsyncStateView<StaffAttendanceSummary>(
            state: staff.state,
            onRetry: () {
              staff.fetchTodayAttendance(branchId: branchId);
            },
            builder: (context, summary) {
              return _StaffAttendanceContent(summary: summary);
            },
          );
        },
      ),
    );
  }
}

class _StaffAttendanceContent extends StatelessWidget {
  const _StaffAttendanceContent({required this.summary});

  final StaffAttendanceSummary summary;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      physics: const AlwaysScrollableScrollPhysics(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
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
                _SummaryItem(
                  label: 'إجمالي الكادر',
                  value: '${summary.totalEmployees}',
                  valueColor: Colors.white,
                ),
                _SummaryItem(
                  label: 'حاضر',
                  value: '${summary.presentCount}',
                  valueColor: AppColors.safeCashSuccess,
                ),
                _SummaryItem(
                  label: 'متأخر',
                  value: '${summary.lateCount}',
                  valueColor: AppColors.warningLateness,
                ),
                _SummaryItem(
                  label: 'غائب',
                  value: '${summary.absentCount}',
                  valueColor: AppColors.stockDepletedDanger,
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          Text(
            'قائمة الفنيين والموظفين اليوم',
            style: GoogleFonts.cairo(
              fontSize: 15,
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 12),
          ...summary.staffList
              .map((member) => _StaffTile(member: member)),
        ],
      ),
    );
  }
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({
    required this.label,
    required this.value,
    required this.valueColor,
  });

  final String label;
  final String value;
  final Color valueColor;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          label,
          style: GoogleFonts.almarai(
            fontSize: 11,
            color: AppColors.textMuted,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: GoogleFonts.outfit(
            fontSize: 18,
            fontWeight: FontWeight.bold,
            color: valueColor,
          ),
        ),
      ],
    );
  }
}

class _StaffTile extends StatelessWidget {
  const _StaffTile({required this.member});

  final StaffMemberAttendance member;

  @override
  Widget build(BuildContext context) {
    final StatusBadge badge;
    if (member.status == 'present') {
      badge = StatusBadge.success(label: member.statusLabel);
    } else if (member.status == 'late') {
      badge = StatusBadge.warning(
        label: '${member.statusLabel} (${member.lateMinutes} دقيقة)',
      );
    } else {
      badge = StatusBadge.danger(label: member.statusLabel);
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.strokeBorder),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Expanded(
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: const BoxDecoration(
                    color: AppColors.elevatedSurface,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.person,
                    color: AppColors.goldAccent,
                    size: 20,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        member.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.cairo(
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      Text(
                        member.role,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.almarai(
                          fontSize: 11,
                          color: AppColors.textMuted,
                        ),
                      ),
                      if (member.checkInTime != null)
                        Text(
                          'الحضور: ${member.checkInTime}',
                          style: GoogleFonts.almarai(
                            fontSize: 11,
                            color: AppColors.safeCashSuccess,
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          badge,
        ],
      ),
    );
  }
}
