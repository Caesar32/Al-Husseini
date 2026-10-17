import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_colors.dart';

enum SalesSegment { invoices, returns }

class SalesSegmentSwitch extends StatelessWidget {
  const SalesSegmentSwitch({
    super.key,
    required this.selected,
    required this.onSelected,
  });

  final SalesSegment selected;
  final ValueChanged<SalesSegment> onSelected;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: Row(
        children: [
          FilterChip(
            label: Text(
              'الفواتير',
              style: GoogleFonts.cairo(fontSize: 12),
            ),
            selected: selected == SalesSegment.invoices,
            selectedColor: AppColors.goldAccent,
            backgroundColor: AppColors.elevatedSurface,
            onSelected: (value) {
              if (value) {
                onSelected(SalesSegment.invoices);
              }
            },
          ),
          const SizedBox(width: 8),
          FilterChip(
            label: Text(
              'المرتجعات',
              style: GoogleFonts.cairo(fontSize: 12),
            ),
            selected: selected == SalesSegment.returns,
            selectedColor: AppColors.goldAccent,
            backgroundColor: AppColors.elevatedSurface,
            onSelected: (value) {
              if (value) {
                onSelected(SalesSegment.returns);
              }
            },
          ),
        ],
      ),
    );
  }
}
