import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/live_pulse_data.dart';
import '../executive_kpi_card.dart';

class SalesTodayCard extends StatelessWidget {
  const SalesTodayCard({
    required this.data,
    super.key,
  });

  final SalesTodayData data;

  @override
  Widget build(BuildContext context) {
    return ExecutiveKpiCard(
      title: 'مبيعات اليوم',
      value: data.formatted,
      subtitle: '${data.invoicesCount} فاتورة صادرة',
      trendText: data.comparedToYesterday,
      isPositiveTrend: !data.comparedToYesterday.contains('-'),
      icon: Icons.trending_up_rounded,
      accentColor: AppColors.safeCashSuccess,
    );
  }
}
