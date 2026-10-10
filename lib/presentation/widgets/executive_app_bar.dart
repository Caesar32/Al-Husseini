import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/theme/app_colors.dart';
import 'live_pulse_dot.dart';

class ExecutiveAppBar extends StatelessWidget implements PreferredSizeWidget {
  final String title;
  final VoidCallback? onRefresh;
  final Function(int?)? onBranchChanged;
  final int? selectedBranchId;
  final bool showLivePulse;

  const ExecutiveAppBar({
    super.key,
    required this.title,
    this.onRefresh,
    this.onBranchChanged,
    this.selectedBranchId,
    this.showLivePulse = true,
  });

  @override
  Size get preferredSize => const Size.fromHeight(65);

  @override
  Widget build(BuildContext context) {
    return AppBar(
      backgroundColor: AppColors.scaffoldBackground,
      elevation: 0,
      scrolledUnderElevation: 0,
      titleSpacing: 16,
      title: Row(
        children: [
          // Royal Brand Emblem Icon
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: AppColors.goldAccent, width: 1.5),
              boxShadow: [
                BoxShadow(
                  color: AppColors.goldAccent.withValues(alpha: 0.3),
                  blurRadius: 8,
                ),
              ],
            ),
            child: ClipOval(
              child: Image.asset(
                'assets/images/alhusseini-icon.jpg',
                width: 34,
                height: 34,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => const Icon(
                  Icons.shield_outlined,
                  color: AppColors.goldAccent,
                  size: 18,
                ),
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: GoogleFonts.cairo(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                    color: AppColors.textPrimary,
                  ),
                ),
                Text(
                  'مركز الحسيني - الرقابة التنفيذية',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: GoogleFonts.almarai(
                    fontSize: 10,
                    color: AppColors.textMuted,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
      actions: [
        if (showLivePulse) ...[
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 4),
            child: LivePulseDot(),
          ),
        ],
        if (onBranchChanged != null) ...[
          PopupMenuButton<int?>(
            initialValue: selectedBranchId,
            tooltip: 'تحديد الفرع',
            icon: Icon(
              selectedBranchId == null ? Icons.store_outlined : Icons.store,
              color: selectedBranchId == null ? AppColors.textMuted : AppColors.goldAccent,
              size: 22,
            ),
            color: AppColors.surface,
            onSelected: onBranchChanged,
            itemBuilder: (context) => [
              PopupMenuItem<int?>(
                value: null,
                child: Text(
                  'جميع الفروع',
                  style: GoogleFonts.cairo(
                    color: selectedBranchId == null ? AppColors.goldAccent : AppColors.textPrimary,
                    fontWeight: selectedBranchId == null ? FontWeight.bold : FontWeight.normal,
                    fontSize: 13,
                  ),
                ),
              ),
              PopupMenuItem<int?>(
                value: 1,
                child: Text(
                  'الفرع الرئيسي',
                  style: GoogleFonts.cairo(
                    color: selectedBranchId == 1 ? AppColors.goldAccent : AppColors.textPrimary,
                    fontWeight: selectedBranchId == 1 ? FontWeight.bold : FontWeight.normal,
                    fontSize: 13,
                  ),
                ),
              ),
            ],
          ),
        ],
        if (onRefresh != null) ...[
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: AppColors.textSecondary, size: 22),
            onPressed: onRefresh,
            tooltip: 'تحديث البيانات',
          ),
        ],
        const SizedBox(width: 8),
      ],
    );
  }
}
