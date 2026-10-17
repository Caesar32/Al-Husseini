import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../core/theme/app_colors.dart';

class StaleDataBanner extends StatelessWidget {
  const StaleDataBanner({
    required this.message,
    this.lastSync,
    this.onRetry,
    super.key,
  });

  final String message;
  final DateTime? lastSync;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: AppColors.warningLateness.withValues(alpha: 0.14),
        border: Border(
          bottom: BorderSide(
            color: AppColors.warningLateness.withValues(alpha: 0.45),
          ),
        ),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.warning_amber_rounded,
            size: 18,
            color: AppColors.warningLateness,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  message,
                  style: GoogleFonts.almarai(
                    color: AppColors.textPrimary,
                    fontSize: 12,
                  ),
                ),
                if (lastSync != null)
                  Text(
                    'آخر تحديث: ${_formatTime(lastSync!)}',
                    style: GoogleFonts.almarai(
                      color: AppColors.textSecondary,
                      fontSize: 11,
                    ),
                  ),
              ],
            ),
          ),
          if (onRetry != null)
            IconButton(
              onPressed: onRetry,
              tooltip: 'تحديث',
              visualDensity: VisualDensity.compact,
              icon: const Icon(
                Icons.refresh_rounded,
                color: AppColors.warningLateness,
                size: 20,
              ),
            ),
        ],
      ),
    );
  }

  String _formatTime(DateTime value) {
    final hour = value.hour.toString().padLeft(2, '0');
    final minute = value.minute.toString().padLeft(2, '0');
    return '$hour:$minute';
  }
}
