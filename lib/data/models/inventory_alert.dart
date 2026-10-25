import 'json_read.dart';

class InventoryAlertItem {
  final int id;
  final String name;
  final String sku;
  final String? barcode;
  final double currentStock;
  final double reorderThreshold;
  final double depletionRatio;
  final String stockStatus;
  final String stockStatusLabel;
  final double costPrice;
  final double retailPrice;
  final bool isBattery;
  final String categoryName;

  const InventoryAlertItem({
    required this.id,
    required this.name,
    required this.sku,
    this.barcode,
    required this.currentStock,
    required this.reorderThreshold,
    required this.depletionRatio,
    required this.stockStatus,
    required this.stockStatusLabel,
    required this.costPrice,
    required this.retailPrice,
    required this.isBattery,
    required this.categoryName,
  });

  factory InventoryAlertItem.fromJson(Map<String, dynamic> json) {
    final costPrice = readMap(json['cost_price']);
    final retailPrice = readMap(json['retail_price']);
    final currentStock = readDouble(json['current_stock']);
    final reorderThreshold = readDouble(json['reorder_threshold']);

    return InventoryAlertItem(
      id: readInt(json['id']),
      name: readString(json['name']),
      sku: readString(json['sku']),
      barcode: readStringOrNull(json['barcode']),
      currentStock: currentStock,
      reorderThreshold: reorderThreshold,
      depletionRatio: readDouble(
        json['depletion_ratio'],
        fallback: _stockRatio(currentStock, reorderThreshold),
      ),
      stockStatus: readString(json['status'], fallback: 'out_of_stock'),
      stockStatusLabel: readString(json['status_label'], fallback: 'منتهي'),
      costPrice: readDouble(costPrice['raw']),
      retailPrice: readDouble(retailPrice['raw']),
      isBattery: readBool(json['is_battery']),
      categoryName: readString(json['category_name'], fallback: 'قسم عام'),
    );
  }

  double get stockRatio {
    return _stockRatio(currentStock, reorderThreshold);
  }
}

double _stockRatio(double currentStock, double reorderThreshold) {
  if (reorderThreshold <= 0) {
    return 0.0;
  }

  final ratio = currentStock / reorderThreshold;
  return ratio > 1.0 ? 1.0 : ratio;
}
