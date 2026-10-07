import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/theme/app_colors.dart';

class StatusBadge extends StatelessWidget {
  final String label;
  final Color backgroundColor;
  final Color textColor;
  final IconData? icon;

  const StatusBadge({
    super.key,
    required this.label,
    required this.backgroundColor,
    required this.textColor,
    this.icon,
  });

  factory StatusBadge.success({required String label, IconData? icon}) {
    return StatusBadge(
      label: label,
      backgroundColor: AppColors.safeCashSuccess.withValues(alpha: 0.15),
      textColor: AppColors.safeCashSuccess,
      icon: icon ?? Icons.check_circle_outline,
    );
  }

  factory StatusBadge.danger({required String label, IconData? icon}) {
    return StatusBadge(
      label: label,
      backgroundColor: AppColors.stockDepletedDanger.withValues(alpha: 0.15),
      textColor: AppColors.stockDepletedDanger,
      icon: icon ?? Icons.warning_amber_rounded,
    );
  }

  factory StatusBadge.warning({required String label, IconData? icon}) {
    return StatusBadge(
      label: label,
      backgroundColor: AppColors.warningLateness.withValues(alpha: 0.15),
      textColor: AppColors.warningLateness,
      icon: icon ?? Icons.access_time_rounded,
    );
  }

  factory StatusBadge.info({required String label, IconData? icon}) {
    return StatusBadge(
      label: label,
      backgroundColor: AppColors.bankCardInfo.withValues(alpha: 0.15),
      textColor: AppColors.bankCardInfo,
      icon: icon ?? Icons.info_outline,
    );
  }

  factory StatusBadge.gold({required String label, IconData? icon}) {
    return StatusBadge(
      label: label,
      backgroundColor: AppColors.goldAccent.withValues(alpha: 0.15),
      textColor: AppColors.goldAccent,
      icon: icon ?? Icons.star_border,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: textColor.withValues(alpha: 0.3), width: 1),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 12, color: textColor),
            const SizedBox(width: 4),
          ],
          Text(
            label,
            style: GoogleFonts.almarai(
              fontSize: 11,
              fontWeight: FontWeight.bold,
              color: textColor,
            ),
          ),
        ],
      ),
    );
  }
}
