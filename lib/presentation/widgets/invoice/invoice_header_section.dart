import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/recent_invoice.dart';
import '../status_badge.dart';

class InvoiceHeaderSection extends StatelessWidget {
  final String invoiceNumber;
  final String createdAt;
  final String statusLabel;

  const InvoiceHeaderSection({
    super.key,
    required this.invoiceNumber,
    required this.createdAt,
    required this.statusLabel,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'فاتورة رقم $invoiceNumber',
                  style: GoogleFonts.cairo(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: AppColors.textPrimary,
                  ),
                ),
                Text(
                  createdAt,
                  style: GoogleFonts.almarai(
                    fontSize: 12,
                    color: AppColors.textMuted,
                  ),
                ),
              ],
            ),
            StatusBadge.gold(label: statusLabel),
          ],
        ),
        const SizedBox(height: 16),
        const Divider(),
      ],
    );
  }
}

class InvoiceCustomerAndStaffSection extends StatelessWidget {
  final InvoiceCustomerInfo? customer;
  final InvoiceVehicleInfo? vehicle;
  final String? cashierName;
  final String? technicianName;

  const InvoiceCustomerAndStaffSection({
    super.key,
    this.customer,
    this.vehicle,
    this.cashierName,
    this.technicianName,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (customer != null || vehicle != null) ...[
          const SizedBox(height: 12),
          Text(
            'بيانات العميل والمركبة',
            style: GoogleFonts.cairo(
              fontSize: 14,
              fontWeight: FontWeight.bold,
              color: AppColors.goldAccent,
            ),
          ),
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.elevatedSurface,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              children: [
                if (customer != null) ...[
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'العميل:',
                        style: GoogleFonts.almarai(
                          color: AppColors.textSecondary,
                        ),
                      ),
                      Text(
                        customer!.name,
                        style: GoogleFonts.almarai(
                          fontWeight: FontWeight.bold,
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'الهاتف:',
                        style: GoogleFonts.almarai(
                          color: AppColors.textSecondary,
                        ),
                      ),
                      Text(
                        customer!.phone,
                        style: GoogleFonts.outfit(
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ],
                  ),
                ],
                if (vehicle != null) ...[
                  const SizedBox(height: 6),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'المركبة:',
                        style: GoogleFonts.almarai(
                          color: AppColors.textSecondary,
                        ),
                      ),
                      Text(
                        '${vehicle!.make} ${vehicle!.model} '
                        '(${vehicle!.plateNumber})',
                        style: GoogleFonts.almarai(
                          fontWeight: FontWeight.bold,
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ],
        const SizedBox(height: 16),
        Row(
          children: [
            if (cashierName != null)
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppColors.elevatedSurface,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'الكاشير',
                        style: GoogleFonts.almarai(
                          fontSize: 11,
                          color: AppColors.textMuted,
                        ),
                      ),
                      Text(
                        cashierName!,
                        style: GoogleFonts.cairo(
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            if (cashierName != null && technicianName != null)
              const SizedBox(width: 8),
            if (technicianName != null)
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppColors.elevatedSurface,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'الفني المسؤول',
                        style: GoogleFonts.almarai(
                          fontSize: 11,
                          color: AppColors.textMuted,
                        ),
                      ),
                      Text(
                        technicianName!,
                        style: GoogleFonts.cairo(
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }
}
