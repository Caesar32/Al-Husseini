import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../data/models/inventory_alert.dart';
import '../../../providers/inventory_provider.dart';
import '../../widgets/arabic_tabular_text.dart';
import '../../widgets/executive_app_bar.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/state/async_state_view.dart';
import '../../widgets/state/paged_list_view.dart';

class InventoryAlertsTab extends StatefulWidget {
  const InventoryAlertsTab({super.key});

  @override
  State<InventoryAlertsTab> createState() => _InventoryAlertsTabState();
}

class _InventoryAlertsTabState extends State<InventoryAlertsTab> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<InventoryProvider>(context, listen: false).fetchAlerts();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.scaffoldBackground,
      appBar: ExecutiveAppBar(
        title: 'تنبيهات النواقص والمخزون',
        onRefresh: () {
          Provider.of<InventoryProvider>(context, listen: false).fetchAlerts();
        },
      ),
      body: Consumer<InventoryProvider>(
        builder: (context, inv, _) {
          return Column(
            children: [
              Padding(
                padding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                child: Row(
                  children: [
                    FilterChip(
                      label: Text(
                        'جميع النواقص',
                        style: GoogleFonts.cairo(fontSize: 12),
                      ),
                      selected: !inv.batteriesOnly,
                      selectedColor: AppColors.goldAccent,
                      backgroundColor: AppColors.elevatedSurface,
                      onSelected: (value) {
                        if (value) {
                          inv.toggleBatteriesFilter(false);
                        }
                      },
                    ),
                    const SizedBox(width: 8),
                    FilterChip(
                      label: Text(
                        'البطاريات فقط',
                        style: GoogleFonts.cairo(fontSize: 12),
                      ),
                      selected: inv.batteriesOnly,
                      selectedColor: AppColors.goldAccent,
                      backgroundColor: AppColors.elevatedSurface,
                      onSelected: inv.toggleBatteriesFilter,
                    ),
                  ],
                ),
              ),
              Expanded(
                child: AsyncStateView<List<InventoryAlertItem>>(
                  state: inv.state,
                  onRetry: inv.fetchAlerts,
                  isEmpty: (items) => items.isEmpty,
                  emptyMessage:
                      'المخزون بوضع آمن - لا توجد نواقص تنبيهية',
                  builder: (context, items) {
                    return PagedListView<InventoryAlertItem>(
                      items: items,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 8,
                      ),
                      hasMore: inv.hasMoreAlerts,
                      isLoadingMore: inv.isLoadingMoreAlerts,
                      loadMoreError: inv.loadMoreError,
                      onLoadMore: inv.loadMoreAlerts,
                      onRefresh: inv.fetchAlerts,
                      itemBuilder: (context, item, index) {
                        return _AlertCard(item: item);
                      },
                    );
                  },
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _AlertCard extends StatelessWidget {
  const _AlertCard({required this.item});

  final InventoryAlertItem item;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      color: AppColors.surface,
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
                      if (item.isBattery)
                        const Padding(
                          padding: EdgeInsets.only(left: 6),
                          child: Icon(
                            Icons.battery_charging_full,
                            size: 18,
                            color: AppColors.goldAccent,
                          ),
                        ),
                      Expanded(
                        child: Text(
                          item.name,
                          style: GoogleFonts.cairo(
                            fontSize: 14,
                            fontWeight: FontWeight.bold,
                            color: AppColors.textPrimary,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ),
                StatusBadge.danger(label: item.stockStatusLabel),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'SKU: ${item.sku}',
                  style: GoogleFonts.outfit(
                    fontSize: 11,
                    color: AppColors.textMuted,
                  ),
                ),
                ArabicTabularText(
                  text: '${item.retailPrice} ج.م',
                  fontSize: 14,
                ),
              ],
            ),
            const SizedBox(height: 12),
            ClipRRect(
              borderRadius: BorderRadius.circular(6),
              child: LinearProgressIndicator(
                value: item.stockRatio,
                minHeight: 6,
                backgroundColor: AppColors.elevatedSurface,
                color: item.currentStock == 0
                    ? AppColors.stockDepletedDanger
                    : AppColors.warningLateness,
              ),
            ),
            const SizedBox(height: 6),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'الرصيد المتبقي: ${item.currentStock}',
                  style: GoogleFonts.almarai(
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    color: item.currentStock == 0
                        ? AppColors.stockDepletedDanger
                        : AppColors.warningLateness,
                  ),
                ),
                Text(
                  'حد إعادة أمان الطلب: ${item.reorderThreshold}',
                  style: GoogleFonts.almarai(
                    fontSize: 11,
                    color: AppColors.textMuted,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
