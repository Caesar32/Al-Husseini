import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/theme/app_colors.dart';
import '../../data/models/recent_invoice.dart';
import 'arabic_tabular_text.dart';
import 'status_badge.dart';

class InvoiceDetailSheet extends StatelessWidget {
  final InvoiceDetail invoice;

  const InvoiceDetailSheet({
    super.key,
    required this.invoice,
  });

  static void show(BuildContext context, InvoiceDetail invoice) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => InvoiceDetailSheet(invoice: invoice),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.85),
      decoration: const BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        border: Border(
          top: BorderSide(color: AppColors.goldAccent, width: 1.5),
        ),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Drag handle
          Center(
            child: Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.strokeBorder,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          const SizedBox(height: 16),

          // Receipt Header
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'فاتورة رقم ${invoice.invoiceNumber}',
                    style: GoogleFonts.cairo(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  Text(
                    invoice.createdAt,
                    style: GoogleFonts.almarai(
                      fontSize: 12,
                      color: AppColors.textMuted,
                    ),
                  ),
                ],
              ),
              StatusBadge.gold(label: invoice.statusLabel),
            ],
          ),
          const SizedBox(height: 16),
          const Divider(),

          Expanded(
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Customer & Vehicle Section
                  if (invoice.customer != null || invoice.vehicle != null) ...[
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
                          if (invoice.customer != null) ...[
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text('العميل:', style: GoogleFonts.almarai(color: AppColors.textSecondary)),
                                Text(invoice.customer!.name, style: GoogleFonts.almarai(fontWeight: FontWeight.bold, color: AppColors.textPrimary)),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text('الهاتف:', style: GoogleFonts.almarai(color: AppColors.textSecondary)),
                                Text(invoice.customer!.phone, style: GoogleFonts.outfit(color: AppColors.textPrimary)),
                              ],
                            ),
                          ],
                          if (invoice.vehicle != null) ...[
                            const SizedBox(height: 6),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text('المركبة:', style: GoogleFonts.almarai(color: AppColors.textSecondary)),
                                Text(
                                  '${invoice.vehicle!.make} ${invoice.vehicle!.model} (${invoice.vehicle!.plateNumber})',
                                  style: GoogleFonts.almarai(fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                                ),
                              ],
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],

                  // Staff / Cashier Section
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      if (invoice.cashierName != null)
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
                                Text('الكاشير', style: GoogleFonts.almarai(fontSize: 11, color: AppColors.textMuted)),
                                Text(invoice.cashierName!, style: GoogleFonts.cairo(fontSize: 13, fontWeight: FontWeight.bold, color: AppColors.textPrimary)),
                              ],
                            ),
                          ),
                        ),
                      if (invoice.cashierName != null && invoice.technicianName != null)
                        const SizedBox(width: 8),
                      if (invoice.technicianName != null)
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
                                Text('الفني المسؤول', style: GoogleFonts.almarai(fontSize: 11, color: AppColors.textMuted)),
                                Text(invoice.technicianName!, style: GoogleFonts.cairo(fontSize: 13, fontWeight: FontWeight.bold, color: AppColors.textPrimary)),
                              ],
                            ),
                          ),
                        ),
                    ],
                  ),

                  // Items List
                  const SizedBox(height: 16),
                  Text(
                    'اصناف الفاتورة (${invoice.items.length})',
                    style: GoogleFonts.cairo(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      color: AppColors.goldAccent,
                    ),
                  ),
                  const SizedBox(height: 8),
                  ...invoice.items.map((item) => Container(
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
                                  text: '${item.totalPrice} ج.م',
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
                                  'الكمية: ${item.quantity} × ${item.unitPrice} ج.م',
                                  style: GoogleFonts.almarai(fontSize: 12, color: AppColors.textSecondary),
                                ),
                                if (item.productSku.isNotEmpty)
                                  Text(
                                    'SKU: ${item.productSku}',
                                    style: GoogleFonts.outfit(fontSize: 11, color: AppColors.textMuted),
                                  ),
                              ],
                            ),
                            if (item.isBattery) ...[
                              const SizedBox(height: 6),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                decoration: BoxDecoration(
                                  color: AppColors.goldAccent.withValues(alpha: 0.1),
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Row(
                                  children: [
                                    const Icon(Icons.battery_charging_full, size: 14, color: AppColors.goldAccent),
                                    const SizedBox(width: 4),
                                    Text(
                                      'بطارية جديدة'
                                      '${item.warrantyDurationMonths != null ? ' - ضمان ${item.warrantyDurationMonths} شهر' : ''}'
                                      '${item.batterySerialNumber != null ? ' (سيريال: ${item.batterySerialNumber})' : ''}',
                                      style: GoogleFonts.almarai(fontSize: 11, fontWeight: FontWeight.bold, color: AppColors.goldAccent),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ],
                        ),
                      )),

                  // Scrap Battery Trade-in if present
                  if (invoice.scrapBattery != null) ...[
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.safeCashSuccess.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppColors.safeCashSuccess.withValues(alpha: 0.3)),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Row(
                            children: [
                              const Icon(Icons.recycling_rounded, color: AppColors.safeCashSuccess, size: 20),
                              const SizedBox(width: 8),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('استبدال بطارية مستعملة (خردة)', style: GoogleFonts.cairo(fontSize: 13, fontWeight: FontWeight.bold, color: AppColors.safeCashSuccess)),
                                  Text('سعة / أمبير: ${invoice.scrapBattery!.capacityAh} A', style: GoogleFonts.almarai(fontSize: 11, color: AppColors.textSecondary)),
                                ],
                              ),
                            ],
                          ),
                          ArabicTabularText(
                            text: '- ${invoice.scrapBattery!.scrapValue} ج.م',
                            fontSize: 14,
                            textColor: AppColors.safeCashSuccess,
                          ),
                        ],
                      ),
                    ),
                  ],

                  // Financial Summary Total
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: AppColors.primaryDark,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: AppColors.goldAccent.withValues(alpha: 0.4)),
                    ),
                    child: Column(
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text('المجموع الفرعي:', style: GoogleFonts.almarai(color: AppColors.textSecondary)),
                            ArabicTabularText(text: '${invoice.subtotal} ج.م', fontSize: 14),
                          ],
                        ),
                        if (invoice.discountAmount > 0) ...[
                          const SizedBox(height: 6),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text('الخصم المباشر:', style: GoogleFonts.almarai(color: AppColors.stockDepletedDanger)),
                              ArabicTabularText(text: '- ${invoice.discountAmount} ج.م', fontSize: 14, textColor: AppColors.stockDepletedDanger),
                            ],
                          ),
                        ],
                        const Divider(height: 16),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text('الصافي النهائي:', style: GoogleFonts.cairo(fontSize: 16, fontWeight: FontWeight.bold, color: AppColors.goldAccent)),
                            ArabicTabularText(text: '${invoice.netAmount} ج.م', fontSize: 20, fontWeight: FontWeight.bold, textColor: AppColors.goldAccent),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
