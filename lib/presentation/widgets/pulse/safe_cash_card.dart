import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/live_pulse_data.dart';
import '../arabic_tabular_text.dart';
import '../status_badge.dart';

class SafeCashCard extends StatelessWidget {
  const SafeCashCard({
    required this.data,
    super.key,
  });

  final SafeCashData data;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: AppColors.primaryGradient,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.goldAccent, width: 1.5),
        boxShadow: [
          BoxShadow(
            color: AppColors.primaryDark.withValues(alpha: 0.5),
            blurRadius: 15,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Row(
                  children: [
                    const Icon(
                      Icons.account_balance_wallet,
                      color: AppColors.goldAccent,
                      size: 24,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        data.label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.cairo(
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              StatusBadge.success(label: 'آمن بـ 100%'),
            ],
          ),
          const SizedBox(height: 12),
          ArabicTabularText(
            text: data.exact,
            fontSize: 32,
            fontWeight: FontWeight.w800,
            textColor: AppColors.goldAccent,
          ),
          const SizedBox(height: 6),
          Text(
            'يشمل مقبوضات مبيعات الكاش والتحصيلات النقدية اليوم',
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
