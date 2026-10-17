import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/live_pulse_data.dart';
import '../status_badge.dart';

class AttendancePulseCard extends StatelessWidget {
  const AttendancePulseCard({
    required this.data,
    super.key,
  });

  final WorkshopAttendanceData data;

  @override
  Widget build(BuildContext context) {
    return Container(
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
                  style: GoogleFonts.cairo(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              StatusBadge.info(label: data.attendanceRate),
            ],
          ),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: LinearProgressIndicator(
              value: data.totalEmployees > 0
                  ? data.presentCount / data.totalEmployees
                  : 0,
              minHeight: 8,
              backgroundColor: AppColors.elevatedSurface,
              color: AppColors.safeCashSuccess,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'تواجد ${data.presentCount} فني من أصل ${data.totalEmployees} موظف مقيد بالمحل',
            style: GoogleFonts.almarai(
              fontSize: 11,
              color: AppColors.textMuted,
            ),
          ),
        ],
      ),
    );
  }
}
