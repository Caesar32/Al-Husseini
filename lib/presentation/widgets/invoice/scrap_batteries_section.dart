import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/recent_invoice.dart';
import '../arabic_tabular_text.dart';

class InvoiceScrapBatteriesSection extends StatelessWidget {
  final List<ScrapBatteryDetail> scrapBatteries;

  const InvoiceScrapBatteriesSection({
    super.key,
    required this.scrapBatteries,
  });

  @override
  Widget build(BuildContext context) {
    if (scrapBatteries.isEmpty) {
      return const SizedBox.shrink();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (final scrap in scrapBatteries) ...[
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.safeCashSuccess.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: AppColors.safeCashSuccess.withValues(alpha: 0.3),
              ),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    const Icon(
                      Icons.recycling_rounded,
                      color: AppColors.safeCashSuccess,
                      size: 20,
                    ),
                    const SizedBox(width: 8),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'استبدال بطارية مستعملة (خردة)',
                          style: GoogleFonts.cairo(
                            fontSize: 13,
                            fontWeight: FontWeight.bold,
                            color: AppColors.safeCashSuccess,
                          ),
                        ),
                        Text(
                          'سعة / أمبير: ${scrap.capacityAh}',
                          style: GoogleFonts.almarai(
                            fontSize: 11,
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
                ArabicTabularText(
                  text: '- ${scrap.scrapValue.toStringAsFixed(2)} ج.م',
                  fontSize: 14,
                  textColor: AppColors.safeCashSuccess,
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}
