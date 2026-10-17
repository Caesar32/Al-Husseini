import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/staff_attendance.dart';
import '../status_badge.dart';

class StaffTile extends StatelessWidget {
  const StaffTile({super.key, required this.member});

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
