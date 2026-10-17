import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/period_metrics.dart';
import '../../../providers/async_state.dart';
import '../../widgets/arabic_tabular_text.dart';
import '../../widgets/state/async_state_view.dart';

class PeriodMetricsSection extends StatelessWidget {
  const PeriodMetricsSection({
    required this.state,
    required this.selectedPeriod,
    required this.onPeriodSelected,
    required this.onRetry,
    super.key,
  });

  final AsyncState<PeriodMetrics> state;
  final String selectedPeriod;
  final ValueChanged<String> onPeriodSelected;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'تحليل المبيعات حسب الفترة',
          style: GoogleFonts.cairo(
            fontSize: 16,
            fontWeight: FontWeight.bold,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 12),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: [
              _buildPeriodChip('today', 'اليوم'),
              _buildPeriodChip('week', 'هذا الأسبوع'),
              _buildPeriodChip('month', 'هذا الشهر'),
              _buildPeriodChip('year', 'هذا العام'),
              _buildPeriodChip('all', 'الكل'),
            ],
          ),
        ),
        const SizedBox(height: 12),
        LimitedBox(
          maxHeight: 120,
          child: AsyncStateView<PeriodMetrics>(
            state: state,
            onRetry: onRetry,
            builder: (context, period) {
              return _buildMetricsCard(period);
            },
          ),
        ),
      ],
    );
  }

  Widget _buildPeriodChip(String periodKey, String label) {
    final isSelected = selectedPeriod == periodKey;

    return Padding(
      padding: const EdgeInsets.only(left: 8),
      child: ChoiceChip(
        label: Text(label),
        selected: isSelected,
        selectedColor: AppColors.goldAccent,
        backgroundColor: AppColors.elevatedSurface,
        labelStyle: GoogleFonts.cairo(
          fontSize: 12,
          fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
          color: isSelected ? Colors.black : AppColors.textSecondary,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: BorderSide(
            color: isSelected
                ? AppColors.goldAccent
                : AppColors.strokeBorder,
          ),
        ),
        onSelected: (_) => onPeriodSelected(periodKey),
      ),
    );
  }

  Widget _buildMetricsCard(PeriodMetrics period) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.strokeBorder),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          Column(
            children: [
              Text(
                'إجمالي الإيرادات',
                style: GoogleFonts.almarai(
                  fontSize: 12,
                  color: AppColors.textMuted,
                ),
              ),
              const SizedBox(height: 4),
              ArabicTabularText(
                text: period.revenue.compact,
                fontSize: 18,
                textColor: AppColors.goldAccent,
              ),
            ],
          ),
          Container(
            width: 1,
            height: 36,
            color: AppColors.strokeBorder,
          ),
          Column(
            children: [
              Text(
                'عدد الفواتير',
                style: GoogleFonts.almarai(
                  fontSize: 12,
                  color: AppColors.textMuted,
                ),
              ),
              const SizedBox(height: 4),
              ArabicTabularText(
                text: '${period.totalSalesCount}',
                fontSize: 18,
                isCurrency: false,
              ),
            ],
          ),
          Container(
            width: 1,
            height: 36,
            color: AppColors.strokeBorder,
          ),
          Column(
            children: [
              Text(
                'مقبوضات الآجل',
                style: GoogleFonts.almarai(
                  fontSize: 12,
                  color: AppColors.textMuted,
                ),
              ),
              const SizedBox(height: 4),
              ArabicTabularText(
                text: period.creditCollected.compact,
                fontSize: 18,
                textColor: AppColors.safeCashSuccess,
              ),
            ],
          ),
        ],
      ),
    );
  }
}
