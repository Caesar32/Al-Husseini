import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/credit_overview.dart';
import '../arabic_tabular_text.dart';

class DebtorTile extends StatelessWidget {
  const DebtorTile({
    super.key,required this.debtor});

  final DebtorItem debtor;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.strokeBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(
                  debtor.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: GoogleFonts.cairo(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              ArabicTabularText(
                text: debtor.formattedCreditBalance,
                fontSize: 15,
                textColor: AppColors.stockDepletedDanger,
              ),
            ],
          ),
          const SizedBox(height: 6),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                debtor.phone,
                style: GoogleFonts.outfit(
                  fontSize: 12,
                  color: AppColors.textMuted,
                ),
              ),
              Flexible(
                child: Wrap(
                  alignment: WrapAlignment.end,
                  spacing: 8,
                  children: [
                    if (debtor.formattedCreditLimit != null)
                      Text(
                        'سقف الائتمان: ${debtor.formattedCreditLimit}',
                        style: GoogleFonts.almarai(
                          fontSize: 11,
                          color: AppColors.textMuted,
                        ),
                      ),
                    if (debtor.utilizationRate != null)
                      Text(
                        'نسبة الاستخدام: ${debtor.utilizationRate}',
                        style: GoogleFonts.almarai(
                          fontSize: 11,
                          color: AppColors.textMuted,
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
