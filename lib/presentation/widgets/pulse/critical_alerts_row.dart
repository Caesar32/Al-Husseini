import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/live_pulse_data.dart';

class CriticalAlertsRow extends StatelessWidget {
  const CriticalAlertsRow({
    required this.data,
    super.key,
  });

  final CriticalAlertsData data;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _HealthBadge(
            title: 'نواقص المخزون',
            count: '${data.lowStockCount}',
            unit: 'صنف',
            color: data.lowStockCount > 0
                ? AppColors.stockDepletedDanger
                : AppColors.safeCashSuccess,
            icon: Icons.inventory_2_outlined,
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: _HealthBadge(
            title: 'مرتجعات اليوم',
            count: '${data.returnsTodayCount}',
            unit: 'عملية',
            color: data.returnsTodayCount > 0
                ? AppColors.warningLateness
                : AppColors.safeCashSuccess,
            icon: Icons.assignment_return_outlined,
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: _HealthBadge(
            title: 'عملاء بالآجل',
            count: '${data.unsettledCreditCount}',
            unit: 'عميل',
            color: AppColors.bankCardInfo,
            icon: Icons.credit_card_outlined,
          ),
        ),
      ],
    );
  }
}

class _HealthBadge extends StatelessWidget {
  const _HealthBadge({
    required this.title,
    required this.count,
    required this.unit,
    required this.color,
    required this.icon,
  });

  final String title;
  final String count;
  final String unit;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
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
          Text(
            title,
            style: GoogleFonts.almarai(
              fontSize: 11,
              color: AppColors.textMuted,
            ),
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              Flexible(
                child: Text(
                  count,
                  overflow: TextOverflow.ellipsis,
                  style: GoogleFonts.outfit(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: color,
                  ),
                ),
              ),
              const SizedBox(width: 4),
              Text(
                unit,
                style: GoogleFonts.almarai(
                  fontSize: 10,
                  color: AppColors.textMuted,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
