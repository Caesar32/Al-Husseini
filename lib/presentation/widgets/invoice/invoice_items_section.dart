import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/recent_invoice.dart';
import '../arabic_tabular_text.dart';

class InvoiceItemsSection extends StatelessWidget {
  final List<InvoiceItemDetail> items;

  const InvoiceItemsSection({
    super.key,
    required this.items,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 16),
        Text(
          'أصناف الفاتورة (${items.length})',
          style: GoogleFonts.cairo(
            fontSize: 14,
            fontWeight: FontWeight.bold,
            color: AppColors.goldAccent,
          ),
        ),
        const SizedBox(height: 8),
        ...items.map(
          (item) => Container(
            margin: const EdgeInsets.only(bottom: 8),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.elevatedSurface,
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
                        item.productName,
                        style: GoogleFonts.cairo(
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ),
                    ArabicTabularText(
                      text: item.totalPrice.display,
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'الكمية: ${item.quantity} × ${item.unitPrice.display}'
                      '${item.returnedQuantity > 0 ? ' (مرتجع: ${item.returnedQuantity})' : ''}',
                      style: GoogleFonts.almarai(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                    if (item.productSku.isNotEmpty)
                      Text(
                        'SKU: ${item.productSku}',
                        style: GoogleFonts.outfit(
                          fontSize: 11,
                          color: AppColors.textMuted,
                        ),
                      ),
                  ],
                ),
                if (item.isBattery) ...[
                  const SizedBox(height: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.goldAccent.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Row(
                      children: [
                        const Icon(
                          Icons.battery_charging_full,
                          size: 14,
                          color: AppColors.goldAccent,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          'بطارية جديدة'
                          '${item.warrantyDurationMonths != null ? ' - ضمان ${item.warrantyDurationMonths} شهر' : ''}'
                          '${item.batterySerialNumber != null ? ' (سيريال: ${item.batterySerialNumber})' : ''}',
                          style: GoogleFonts.almarai(
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                            color: AppColors.goldAccent,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ],
    );
  }
}
