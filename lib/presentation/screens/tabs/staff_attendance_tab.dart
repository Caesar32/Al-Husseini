import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../providers/dashboard_provider.dart';
import '../../../providers/staff_provider.dart';
import '../../widgets/executive_app_bar.dart';
import '../../widgets/status_badge.dart';

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
      final branchId = Provider.of<DashboardProvider>(context, listen: false).selectedBranchId;
      Provider.of<StaffProvider>(context, listen: false).fetchTodayAttendance(branchId: branchId);
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
        onBranchChanged: (bId) {
          Provider.of<DashboardProvider>(context, listen: false).setSelectedBranch(bId);
          Provider.of<StaffProvider>(context, listen: false).fetchTodayAttendance(branchId: bId);
        },
        onRefresh: () {
          Provider.of<StaffProvider>(context, listen: false).fetchTodayAttendance(branchId: branchId);
        },
      ),
      body: Consumer<StaffProvider>(
        builder: (context, staff, _) {
          if (staff.isLoading && staff.attendanceSummary == null) {
            return const Center(child: CircularProgressIndicator(color: AppColors.goldAccent));
          }

          if (staff.error != null && staff.attendanceSummary == null) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.error_outline, size: 48, color: AppColors.stockDepletedDanger),
                  const SizedBox(height: 12),
                  Text(staff.error!, style: GoogleFonts.almarai(color: AppColors.textSecondary)),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: () => staff.fetchTodayAttendance(branchId: branchId),
                    child: const Text('تحديث'),
                  ),
                ],
              ),
            );
          }

          final summary = staff.attendanceSummary;
          if (summary == null) return const SizedBox.shrink();

          return SingleChildScrollView(
            padding: const EdgeInsets.all(16.0),
            physics: const AlwaysScrollableScrollPhysics(),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Attendance Summary Banner
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
                      _buildSummaryItem('إجمالي الكادر', '${summary.totalEmployees}', Colors.white),
                      _buildSummaryItem('حاضر', '${summary.presentCount}', AppColors.safeCashSuccess),
                      _buildSummaryItem('متأخر', '${summary.lateCount}', AppColors.warningLateness),
                      _buildSummaryItem('غائب', '${summary.absentCount}', AppColors.stockDepletedDanger),
                    ],
                  ),
                ),
                const SizedBox(height: 20),

                Text(
                  'قائمة الفنيين والموظفين اليوم',
                  style: GoogleFonts.cairo(fontSize: 15, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                ),
                const SizedBox(height: 12),

                ...summary.staffList.map((member) {
                  StatusBadge badge;
                  if (member.status == 'present') {
                    badge = StatusBadge.success(label: member.statusLabel);
                  } else if (member.status == 'late') {
                    badge = StatusBadge.warning(label: '${member.statusLabel} (${member.lateMinutes} دقيقة)');
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
                                child: const Icon(Icons.person, color: AppColors.goldAccent, size: 20),
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
                                      style: GoogleFonts.cairo(fontSize: 14, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                                    ),
                                    Text(
                                      member.role,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: GoogleFonts.almarai(fontSize: 11, color: AppColors.textMuted),
                                    ),
                                    if (member.checkInTime != null)
                                      Text('الحضور: ${member.checkInTime}', style: GoogleFonts.almarai(fontSize: 11, color: AppColors.safeCashSuccess)),
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
                }),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildSummaryItem(String label, String value, Color valueColor) {
    return Column(
      children: [
        Text(label, style: GoogleFonts.almarai(fontSize: 11, color: AppColors.textMuted)),
        const SizedBox(height: 4),
        Text(value, style: GoogleFonts.outfit(fontSize: 18, fontWeight: FontWeight.bold, color: valueColor)),
      ],
    );
  }
}
