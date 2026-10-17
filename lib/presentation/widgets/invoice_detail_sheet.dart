import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../data/models/recent_invoice.dart';
import 'invoice/invoice_financials_section.dart';
import 'invoice/invoice_header_section.dart';
import 'invoice/invoice_items_section.dart';
import 'invoice/invoice_payments_section.dart';
import 'invoice/scrap_batteries_section.dart';

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
      constraints: BoxConstraints(
        maxHeight: MediaQuery.of(context).size.height * 0.85,
      ),
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
          InvoiceHeaderSection(
            invoiceNumber: invoice.invoiceNumber,
            createdAt: invoice.createdAt,
            statusLabel: invoice.statusLabel,
          ),
          Expanded(
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  InvoiceCustomerAndStaffSection(
                    customer: invoice.customer,
                    vehicle: invoice.vehicle,
                    cashierName: invoice.cashierName,
                    technicianName: invoice.technicianName,
                  ),
                  InvoiceItemsSection(items: invoice.items),
                  InvoiceScrapBatteriesSection(
                    scrapBatteries: invoice.scrapBatteries,
                  ),
                  InvoiceFinancialsSection(
                    subtotal: invoice.subtotal,
                    discountAmount: invoice.discountAmount,
                    scrapDeductionAmount: invoice.scrapDeductionAmount,
                    refundedAmount: invoice.refundedAmount,
                    netAmount: invoice.netAmount,
                  ),
                  if (invoice.payments.isNotEmpty)
                    InvoicePaymentsSection(payments: invoice.payments),
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
