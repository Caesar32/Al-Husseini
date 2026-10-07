import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../providers/inventory_provider.dart';
import '../../widgets/arabic_tabular_text.dart';
import '../../widgets/executive_app_bar.dart';
import '../../widgets/status_badge.dart';

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
        title: 'تنبهات النواقص والمخزون',
        onRefresh: () {
          Provider.of<InventoryProvider>(context, listen: false).fetchAlerts();
        },
      ),
      body: Consumer<InventoryProvider>(
        builder: (context, inv, _) {
          return Column(
            children: [
              // Battery Filter Chip Bar
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                child: Row(
                  children: [
                    FilterChip(
                      label: Text('جميع النواقص', style: GoogleFonts.cairo(fontSize: 12)),
                      selected: !inv.batteriesOnly,
                      selectedColor: AppColors.goldAccent,
                      backgroundColor: AppColors.elevatedSurface,
                      onSelected: (val) {
                        if (val) inv.toggleBatteriesFilter(false);
                      },
                    ),
                    const SizedBox(width: 8),
                    FilterChip(
                      label: Text('البطاريات فقط', style: GoogleFonts.cairo(fontSize: 12)),
                      selected: inv.batteriesOnly,
                      selectedColor: AppColors.goldAccent,
                      backgroundColor: AppColors.elevatedSurface,
                      onSelected: (val) {
                        inv.toggleBatteriesFilter(val);
                      },
                    ),
                  ],
                ),
              ),

              // Alerts List
              Expanded(
                child: Builder(
                  builder: (context) {
                    if (inv.isLoading && inv.alerts.isEmpty) {
                      return const Center(child: CircularProgressIndicator(color: AppColors.goldAccent));
                    }

                    if (inv.error != null && inv.alerts.isEmpty) {
                      return Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(Icons.error_outline, size: 48, color: AppColors.stockDepletedDanger),
                            const SizedBox(height: 12),
                            Text(inv.error!, style: GoogleFonts.almarai(color: AppColors.textSecondary)),
                            const SizedBox(height: 12),
                            ElevatedButton(
                              onPressed: () => inv.fetchAlerts(),
                              child: const Text('تحديث'),
                            ),
                          ],
                        ),
                      );
                    }

                    final items = inv.alerts;
                    if (items.isEmpty) {
                      return Center(
                        child: Text(
                          'المخزون بوضع آمن - لا توجد نواقص تنبيهية',
                          style: GoogleFonts.cairo(color: AppColors.safeCashSuccess, fontSize: 14),
                        ),
                      );
                    }

                    return ListView.builder(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      itemCount: items.length,
                      itemBuilder: (context, index) {
                        final item = items[index];

                        return Card(
                          margin: const EdgeInsets.only(bottom: 12),
                          color: AppColors.surface,
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
                                          if (item.isBattery)
                                            const Padding(
                                              padding: EdgeInsets.only(left: 6),
                                              child: Icon(Icons.battery_charging_full, size: 18, color: AppColors.goldAccent),
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
                                    Text('SKU: ${item.sku}', style: GoogleFonts.outfit(fontSize: 11, color: AppColors.textMuted)),
                                    ArabicTabularText(text: '${item.retailPrice} ج.م', fontSize: 14),
                                  ],
                                ),
                                const SizedBox(height: 12),

                                // Gauge Bar
                                ClipRRect(
                                  borderRadius: BorderRadius.circular(6),
                                  child: LinearProgressIndicator(
                                    value: item.stockRatio,
                                    minHeight: 6,
                                    backgroundColor: AppColors.elevatedSurface,
                                    color: item.currentStock == 0 ? AppColors.stockDepletedDanger : AppColors.warningLateness,
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
                                        color: item.currentStock == 0 ? AppColors.stockDepletedDanger : AppColors.warningLateness,
                                      ),
                                    ),
                                    Text(
                                      'حد حد أمان الطلب: ${item.reorderThreshold}',
                                      style: GoogleFonts.almarai(fontSize: 11, color: AppColors.textMuted),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        );
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
