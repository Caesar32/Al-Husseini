import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/recent_invoice.dart';
import '../../../data/models/return_summary.dart';
import '../../../providers/dashboard_provider.dart';
import '../../../providers/sales_provider.dart';
import '../../widgets/arabic_tabular_text.dart';
import '../../widgets/executive_app_bar.dart';
import '../../widgets/invoice_detail_sheet.dart';
import '../../widgets/return_tile.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/state/async_state_view.dart';
import '../../widgets/state/paged_list_view.dart';

enum _SalesSegment { invoices, returns }

class SalesStreamTab extends StatefulWidget {
  const SalesStreamTab({super.key});

  @override
  State<SalesStreamTab> createState() => _SalesStreamTabState();
}

class _SalesStreamTabState extends State<SalesStreamTab> {
  final TextEditingController _searchController = TextEditingController();
  _SalesSegment _segment = _SalesSegment.invoices;
  bool _returnsRequested = false;
  String _searchText = '';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final branchId =
          context.read<DashboardProvider>().selectedBranchId;
      unawaited(
        context.read<SalesProvider>().fetchRecentInvoices(
              branchId: branchId,
            ),
      );
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final branchId = context.watch<DashboardProvider>().selectedBranchId;

    return Scaffold(
      backgroundColor: AppColors.scaffoldBackground,
      appBar: ExecutiveAppBar(
        title: 'بث المبيعات المباشر',
        selectedBranchId: branchId,
        onBranchChanged: (branchId) {
          context.read<DashboardProvider>().setSelectedBranch(branchId);
          _refreshActiveSegment(branchId: branchId);
        },
        onRefresh: _refreshActiveSegment,
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              controller: _searchController,
              onChanged: (value) {
                setState(() => _searchText = value);
                context.read<SalesProvider>().setSearchQuery(value);
              },
              style: GoogleFonts.cairo(
                color: AppColors.textPrimary,
                fontSize: 14,
              ),
              decoration: InputDecoration(
                hintText: 'البحث برقم الفاتورة، اسم العميل، أو رقم الهاتف...',
                prefixIcon: const Icon(
                  Icons.search_rounded,
                  color: AppColors.goldAccent,
                ),
                suffixIcon: _searchText.isNotEmpty
                    ? IconButton(
                        icon: const Icon(
                          Icons.clear,
                          color: AppColors.textMuted,
                        ),
                        onPressed: () {
                          _searchController.clear();
                          setState(() => _searchText = '');
                          context.read<SalesProvider>().setSearchQuery('');
                        },
                      )
                    : null,
              ),
            ),
          ),
          _SalesSegmentSwitch(
            selected: _segment,
            onSelected: _selectSegment,
          ),
          Expanded(
            child: Consumer<SalesProvider>(
              builder: (context, sales, _) {
                if (_segment == _SalesSegment.invoices) {
                  return _buildInvoices(context, sales, branchId);
                }

                return _buildReturns(sales, branchId);
              },
            ),
          ),
        ],
      ),
    );
  }

  void _selectSegment(_SalesSegment segment) {
    if (_segment == segment) {
      return;
    }

    setState(() => _segment = segment);

    if (segment == _SalesSegment.returns && !_returnsRequested) {
      _returnsRequested = true;
      final branchId = context.read<DashboardProvider>().selectedBranchId;
      unawaited(
        context.read<SalesProvider>().fetchRecentReturns(
              branchId: branchId,
            ),
      );
    }
  }

  void _refreshActiveSegment({int? branchId}) {
    final currentBranchId =
        branchId ?? context.read<DashboardProvider>().selectedBranchId;
    final sales = context.read<SalesProvider>();

    if (_segment == _SalesSegment.invoices) {
      unawaited(
        sales.fetchRecentInvoices(branchId: currentBranchId),
      );
    } else {
      unawaited(
        sales.fetchRecentReturns(branchId: currentBranchId),
      );
    }
  }

  Widget _buildInvoices(
    BuildContext context,
    SalesProvider sales,
    int? branchId,
  ) {
    return AsyncStateView<List<InvoiceSummary>>(
      state: sales.invoicesState,
      onRetry: () => sales.fetchRecentInvoices(branchId: branchId),
      isEmpty: (_) => sales.recentInvoices.isEmpty,
      emptyMessage: 'لا توجد فواتير مبيعات مطابقة',
      builder: (context, _) {
        return PagedListView<InvoiceSummary>(
          items: sales.recentInvoices,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          hasMore: sales.hasMoreInvoices,
          isLoadingMore: sales.isLoadingMoreInvoices,
          loadMoreError: sales.loadMoreError,
          onLoadMore: sales.loadMoreInvoices,
          onRefresh: () => sales.fetchRecentInvoices(branchId: branchId),
          itemBuilder: (context, invoice, _) {
            return _InvoiceTile(
              invoice: invoice,
              onTap: () => _openInvoice(context, invoice.id),
            );
          },
        );
      },
    );
  }

  Widget _buildReturns(SalesProvider sales, int? branchId) {
    return AsyncStateView<List<ReturnSummary>>(
      state: sales.returnsState,
      onRetry: () => sales.fetchRecentReturns(branchId: branchId),
      isEmpty: (items) => items.isEmpty,
      emptyMessage: 'لا توجد مرتجعات مسجلة.',
      builder: (context, items) {
        return PagedListView<ReturnSummary>(
          items: items,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          hasMore: sales.hasMoreReturns,
          isLoadingMore: sales.isLoadingMoreReturns,
          loadMoreError: sales.loadMoreError,
          onLoadMore: sales.loadMoreReturns,
          onRefresh: () => sales.fetchRecentReturns(branchId: branchId),
          itemBuilder: (_, item, _) => ReturnTile(summary: item),
        );
      },
    );
  }

  Future<void> _openInvoice(BuildContext context, int invoiceId) async {
    final sales = context.read<SalesProvider>();
    await sales.fetchInvoiceDetail(invoiceId);

    if (!context.mounted) {
      return;
    }

    final detail = sales.selectedInvoiceDetail;
    if (detail != null) {
      InvoiceDetailSheet.show(context, detail);
      return;
    }

    final error = sales.detailError;
    if (error != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error)),
      );
    }
  }
}

class _SalesSegmentSwitch extends StatelessWidget {
  const _SalesSegmentSwitch({
    required this.selected,
    required this.onSelected,
  });

  final _SalesSegment selected;
  final ValueChanged<_SalesSegment> onSelected;

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
            selected: selected == _SalesSegment.invoices,
            selectedColor: AppColors.goldAccent,
            backgroundColor: AppColors.elevatedSurface,
            onSelected: (value) {
              if (value) {
                onSelected(_SalesSegment.invoices);
              }
            },
          ),
          const SizedBox(width: 8),
          FilterChip(
            label: Text(
              'المرتجعات',
              style: GoogleFonts.cairo(fontSize: 12),
            ),
            selected: selected == _SalesSegment.returns,
            selectedColor: AppColors.goldAccent,
            backgroundColor: AppColors.elevatedSurface,
            onSelected: (value) {
              if (value) {
                onSelected(_SalesSegment.returns);
              }
            },
          ),
        ],
      ),
    );
  }
}

class _InvoiceTile extends StatelessWidget {
  const _InvoiceTile({
    required this.invoice,
    required this.onTap,
  });

  final InvoiceSummary invoice;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      color: AppColors.surface,
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
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
                            'فاتورة ${invoice.invoiceNumber}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: GoogleFonts.cairo(
                              fontSize: 15,
                              fontWeight: FontWeight.bold,
                              color: AppColors.textPrimary,
                            ),
                          ),
                        ),
                        if (invoice.hasWarrantyBattery) ...[
                          const SizedBox(width: 8),
                          const Icon(
                            Icons.battery_charging_full,
                            size: 16,
                            color: AppColors.goldAccent,
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  ArabicTabularText(
                    text: invoice.netAmount.display,
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
                      const Icon(
                        Icons.person_outline,
                        size: 14,
                        color: AppColors.textMuted,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        invoice.customerName,
                        style: GoogleFonts.almarai(
                          fontSize: 12,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                  Text(
                    invoice.timeAgo,
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
                  StatusBadge.info(label: invoice.paymentMethodLabel),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'الكاشير: ${invoice.cashierName}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.end,
                      style: GoogleFonts.almarai(
                        fontSize: 11,
                        color: AppColors.textMuted,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
