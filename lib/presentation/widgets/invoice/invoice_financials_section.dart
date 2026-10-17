import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/json_read.dart';
import '../arabic_tabular_text.dart';

class InvoiceFinancialsSection extends StatelessWidget {
  final Money subtotal;
  final Money discountAmount;
  final Money scrapDeductionAmount;
  final Money refundedAmount;
  final Money netAmount;

  const InvoiceFinancialsSection({
    super.key,
    required this.subtotal,
    required this.discountAmount,
    required this.scrapDeductionAmount,
    required this.refundedAmount,
    required this.netAmount,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const SizedBox(height: 16),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.primaryDark,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: AppColors.goldAccent.withValues(alpha: 0.4),
            ),
          ),
          child: Column(
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'المجموع الفرعي:',
                    style: GoogleFonts.almarai(
                      color: AppColors.textSecondary,
                    ),
                  ),
                  ArabicTabularText(
                    text: subtotal.display,
                    fontSize: 14,
                  ),
                ],
              ),
              if (discountAmount.raw > 0) ...[
                const SizedBox(height: 6),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'الخصم المباشر:',
                      style: GoogleFonts.almarai(
                        color: AppColors.stockDepletedDanger,
                      ),
                    ),
                    ArabicTabularText(
                      text: '- ${discountAmount.display}',
                      fontSize: 14,
                      textColor: AppColors.stockDepletedDanger,
                    ),
                  ],
                ),
              ],
              if (scrapDeductionAmount.raw > 0) ...[
                const SizedBox(height: 6),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'خصم الخردة',
                      style: GoogleFonts.almarai(
                        color: AppColors.stockDepletedDanger,
                      ),
                    ),
                    ArabicTabularText(
                      text: '- ${scrapDeductionAmount.display}',
                      fontSize: 14,
                      textColor: AppColors.stockDepletedDanger,
                    ),
                  ],
                ),
              ],
              if (refundedAmount.raw > 0) ...[
                const SizedBox(height: 6),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'المسترد',
                      style: GoogleFonts.almarai(
                        color: AppColors.stockDepletedDanger,
                      ),
                    ),
                    ArabicTabularText(
                      text: '- ${refundedAmount.display}',
                      fontSize: 14,
                      textColor: AppColors.stockDepletedDanger,
                    ),
                  ],
                ),
              ],
              const Divider(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'الصافي النهائي:',
                    style: GoogleFonts.cairo(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: AppColors.goldAccent,
                    ),
                  ),
                  ArabicTabularText(
                    text: netAmount.display,
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    textColor: AppColors.goldAccent,
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }
}
