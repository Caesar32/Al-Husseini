import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../data/models/staff_attendance.dart';
import '../../../providers/dashboard_provider.dart';
import '../../../providers/staff_provider.dart';
import '../../widgets/executive_app_bar.dart';
import '../../widgets/staff/staff_summary_item.dart';
import '../../widgets/staff/staff_tile.dart';
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
                SummaryItem(
                  label: 'إجمالي الكادر',
                  value: '${summary.totalEmployees}',
                  valueColor: Colors.white,
                ),
                SummaryItem(
                  label: 'حاضر',
                  value: '${summary.presentCount}',
                  valueColor: AppColors.safeCashSuccess,
                ),
                SummaryItem(
                  label: 'متأخر',
                  value: '${summary.lateCount}',
                  valueColor: AppColors.warningLateness,
                ),
                SummaryItem(
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
              .map((member) => StaffTile(member: member)),
        ],
      ),
    );
  }
}
