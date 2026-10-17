import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/recent_invoice.dart';
import '../../../data/models/return_summary.dart';
import '../../../providers/dashboard_provider.dart';
import '../../../providers/sales_provider.dart';
import '../../widgets/executive_app_bar.dart';
import '../../widgets/sales/invoice_tile.dart';
import '../../widgets/sales/sales_segment_switch.dart';
import '../../widgets/invoice_detail_sheet.dart';
import '../../widgets/return_tile.dart';
import '../../widgets/state/async_state_view.dart';
import '../../widgets/state/paged_list_view.dart';

class SalesStreamTab extends StatefulWidget {
  const SalesStreamTab({super.key});

  @override
  State<SalesStreamTab> createState() => _SalesStreamTabState();
}

class _SalesStreamTabState extends State<SalesStreamTab> {
  final TextEditingController _searchController = TextEditingController();
  SalesSegment _segment = SalesSegment.invoices;
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
          SalesSegmentSwitch(
            selected: _segment,
            onSelected: _selectSegment,
          ),
          Expanded(
            child: Consumer<SalesProvider>(
              builder: (context, sales, _) {
                if (_segment == SalesSegment.invoices) {
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

  void _selectSegment(SalesSegment segment) {
    if (_segment == segment) {
      return;
    }

    setState(() => _segment = segment);

    if (segment == SalesSegment.returns && !_returnsRequested) {
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

    if (_segment == SalesSegment.invoices) {
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
            return InvoiceTile(
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
