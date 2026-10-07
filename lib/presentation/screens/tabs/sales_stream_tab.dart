import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../providers/dashboard_provider.dart';
import '../../../providers/sales_provider.dart';
import '../../widgets/arabic_tabular_text.dart';
import '../../widgets/executive_app_bar.dart';
import '../../widgets/invoice_detail_sheet.dart';
import '../../widgets/status_badge.dart';

class SalesStreamTab extends StatefulWidget {
  const SalesStreamTab({super.key});

  @override
  State<SalesStreamTab> createState() => _SalesStreamTabState();
}

class _SalesStreamTabState extends State<SalesStreamTab> {
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final branchId = Provider.of<DashboardProvider>(context, listen: false).selectedBranchId;
      Provider.of<SalesProvider>(context, listen: false).fetchRecentInvoices(branchId: branchId);
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final branchId = Provider.of<DashboardProvider>(context).selectedBranchId;

    return Scaffold(
      backgroundColor: AppColors.scaffoldBackground,
      appBar: ExecutiveAppBar(
        title: 'بث المبيعات المباشر',
        selectedBranchId: branchId,
        onBranchChanged: (bId) {
          Provider.of<DashboardProvider>(context, listen: false).setSelectedBranch(bId);
          Provider.of<SalesProvider>(context, listen: false).fetchRecentInvoices(branchId: bId);
        },
        onRefresh: () {
          Provider.of<SalesProvider>(context, listen: false).fetchRecentInvoices(branchId: branchId);
        },
      ),
      body: Column(
        children: [
          // Search & Filter Header
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: TextField(
              controller: _searchController,
              onChanged: (val) {
                Provider.of<SalesProvider>(context, listen: false).setSearchQuery(val);
              },
              style: GoogleFonts.cairo(color: AppColors.textPrimary, fontSize: 14),
              decoration: InputDecoration(
                hintText: 'البحث برقم الفاتورة، اسم العميل، أو رقم الهاتف...',
                prefixIcon: const Icon(Icons.search_rounded, color: AppColors.goldAccent),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, color: AppColors.textMuted),
                        onPressed: () {
                          _searchController.clear();
                          Provider.of<SalesProvider>(context, listen: false).setSearchQuery('');
                        },
                      )
                    : null,
              ),
            ),
          ),

          // Invoices Stream List
          Expanded(
            child: Consumer<SalesProvider>(
              builder: (context, sales, _) {
                if (sales.isLoadingInvoices && sales.recentInvoices.isEmpty) {
                  return const Center(
                    child: CircularProgressIndicator(color: AppColors.goldAccent),
                  );
                }

                if (sales.invoicesError != null && sales.recentInvoices.isEmpty) {
                  return Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.error_outline, size: 48, color: AppColors.stockDepletedDanger),
                        const SizedBox(height: 12),
                        Text(sales.invoicesError!, style: GoogleFonts.almarai(color: AppColors.textSecondary)),
                        const SizedBox(height: 12),
                        ElevatedButton(
                          onPressed: () => sales.fetchRecentInvoices(branchId: branchId),
                          child: const Text('تحديث'),
                        ),
                      ],
                    ),
                  );
                }

                final invoices = sales.recentInvoices;

                if (invoices.isEmpty) {
                  return Center(
                    child: Text(
                      'لا توجد فواتير مبيعات مطابقة',
                      style: GoogleFonts.cairo(color: AppColors.textMuted, fontSize: 14),
                    ),
                  );
                }

                return ListView.builder(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  itemCount: invoices.length,
                  itemBuilder: (context, index) {
                    final inv = invoices[index];

                    return Card(
                      margin: const EdgeInsets.only(bottom: 12),
                      color: AppColors.surface,
                      child: InkWell(
                        borderRadius: BorderRadius.circular(16),
                        onTap: () async {
                          final salesProvider = Provider.of<SalesProvider>(context, listen: false);
                          await salesProvider.fetchInvoiceDetail(inv.id);
                          if (salesProvider.selectedInvoiceDetail != null && context.mounted) {
                            InvoiceDetailSheet.show(context, salesProvider.selectedInvoiceDetail!);
                          }
                        },
                        child: Padding(
                          padding: const EdgeInsets.all(16.0),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Expanded(
                                    child: Row(
                                      children: [
                                        Expanded(
                                          child: Text(
                                            'فاتورة ${inv.invoiceNumber}',
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: GoogleFonts.cairo(
                                              fontSize: 15,
                                              fontWeight: FontWeight.bold,
                                              color: AppColors.textPrimary,
                                            ),
                                          ),
                                        ),
                                        if (inv.hasWarrantyBattery) ...[
                                          const SizedBox(width: 8),
                                          const Icon(Icons.battery_charging_full, size: 16, color: AppColors.goldAccent),
                                        ],
                                      ],
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  ArabicTabularText(
                                    text: inv.formattedNetAmount,
                                    fontSize: 16,
                                    fontWeight: FontWeight.w800,
                                    textColor: AppColors.goldAccent,
                                  ),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Row(
                                    children: [
                                      const Icon(Icons.person_outline, size: 14, color: AppColors.textMuted),
                                      const SizedBox(width: 4),
                                      Text(
                                        inv.customerName,
                                        style: GoogleFonts.almarai(
                                          fontSize: 12,
                                          color: AppColors.textSecondary,
                                        ),
                                      ),
                                    ],
                                  ),
                                  Text(
                                    inv.timeAgo,
                                    style: GoogleFonts.almarai(
                                      fontSize: 11,
                                      color: AppColors.textMuted,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 10),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  StatusBadge.info(label: inv.paymentMethodLabel),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      'الكاشير: ${inv.cashierName}',
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      textAlign: TextAlign.end,
                                      style: GoogleFonts.almarai(fontSize: 11, color: AppColors.textMuted),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
