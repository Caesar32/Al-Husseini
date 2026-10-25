import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_colors.dart';
import '../../data/models/json_read.dart';
import '../../data/models/return_summary.dart';
import 'status_badge.dart';

class ReturnTile extends StatelessWidget {
  const ReturnTile({
    required this.summary,
    super.key,
  });

  final ReturnSummary summary;

  @override
  Widget build(BuildContext context) {
    final statusBadge = summary.status == 'partially_refunded'
        ? StatusBadge.warning(label: summary.statusLabel)
        : StatusBadge.success(label: summary.statusLabel);

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      color: AppColors.surface,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    'فاتورة ${summary.invoiceNumber}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: GoogleFonts.cairo(
                      fontSize: 15,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      summary.refundedAmount.display,
                      style: GoogleFonts.cairo(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: AppColors.stockDepletedDanger,
                      ),
                    ),
                    Text(
                      'من أصل: ${summary.originalAmount.display}',
                      style: GoogleFonts.almarai(
                        fontSize: 10,
                        color: AppColors.textMuted,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Row(
                    children: [
                      const Icon(
                        Icons.person_outline,
                        size: 14,
                        color: AppColors.textMuted,
                      ),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          summary.customerName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: GoogleFonts.almarai(
                            fontSize: 12,
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                Text(
                  summary.time,
                  style: GoogleFonts.almarai(
                    fontSize: 11,
                    color: AppColors.textMuted,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            statusBadge,
            if (summary.returnedItems.isNotEmpty) ...[
              const SizedBox(height: 12),
              ...summary.returnedItems.take(3).map(
                    (item) => Padding(
                      padding: const EdgeInsets.only(bottom: 6),
                      child: Text(
                        '${item.productName} × ${formatQuantity(item.returnedQuantity)} — '
                        '${item.totalRefunded.display}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.almarai(
                          fontSize: 11,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ),
                  ),
              if (summary.returnedItems.length > 3)
                Text(
                  '+${summary.returnedItems.length - 3} أصناف أخرى',
                  style: GoogleFonts.almarai(
                    fontSize: 11,
                    color: AppColors.textMuted,
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }
}
