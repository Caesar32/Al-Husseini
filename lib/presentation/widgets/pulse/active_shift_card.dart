import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/live_pulse_data.dart';
import '../executive_kpi_card.dart';

class ActiveShiftCard extends StatelessWidget {
  const ActiveShiftCard({
    required this.data,
    super.key,
  });

  final ActiveShiftData data;

  @override
  Widget build(BuildContext context) {
    return ExecutiveKpiCard(
      title: 'الوردية الحالية',
      value: data.isOpen ? data.cashierName : 'مغلقة',
      subtitle: data.isOpen
          ? 'فتح: ${data.openedAt}'
          : 'لا يوجد كاشير متواجد',
      icon: Icons.badge_outlined,
      accentColor: data.isOpen
          ? AppColors.bankCardInfo
          : AppColors.warningLateness,
    );
  }
}
