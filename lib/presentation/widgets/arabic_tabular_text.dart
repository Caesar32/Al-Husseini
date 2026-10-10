import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/theme/app_colors.dart';

class ArabicTabularText extends StatelessWidget {
  final String text;
  final double fontSize;
  final FontWeight fontWeight;
  final Color textColor;
  final String currencySymbol;
  final bool isCurrency;

  const ArabicTabularText({
    super.key,
    required this.text,
    this.fontSize = 20,
    this.fontWeight = FontWeight.bold,
    this.textColor = AppColors.textPrimary,
    this.currencySymbol = 'ج.م',
    this.isCurrency = true,
  });

  @override
  Widget build(BuildContext context) {
    // Separate numeric value from currency label if present
    String numberPart = text;
    String currencyPart = currencySymbol;

    if (text.contains('ج.م')) {
      numberPart = text.replaceAll('ج.م', '').trim();
    } else if (text.contains('EGP')) {
      numberPart = text.replaceAll('EGP', '').trim();
    }

    return Row(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.baseline,
      textBaseline: TextBaseline.alphabetic,
      children: [
        Text(
          numberPart,
          style: GoogleFonts.outfit(
            fontSize: fontSize,
            fontWeight: fontWeight,
            color: textColor,
            fontFeatures: const [FontFeature.tabularFigures()],
          ),
        ),
        if (isCurrency) ...[
          const SizedBox(width: 4),
          Text(
            currencyPart,
            style: GoogleFonts.cairo(
              fontSize: fontSize * 0.65,
              fontWeight: FontWeight.bold,
              color: AppColors.goldAccent,
            ),
          ),
        ],
      ],
    );
  }
}
