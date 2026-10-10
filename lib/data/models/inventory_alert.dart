class InventoryAlertItem {
  final int id;
  final String name;
  final String sku;
  final String? barcode;
  final int currentStock;
  final int reorderThreshold;
  final String stockStatus;
  final String stockStatusLabel;
  final double costPrice;
  final double retailPrice;
  final bool isBattery;
  final String categoryName;

  InventoryAlertItem({
    required this.id,
    required this.name,
    required this.sku,
    this.barcode,
    required this.currentStock,
    required this.reorderThreshold,
    required this.stockStatus,
    required this.stockStatusLabel,
    required this.costPrice,
    required this.retailPrice,
    required this.isBattery,
    required this.categoryName,
  });

  factory InventoryAlertItem.fromJson(Map<String, dynamic> json) {
    final costPrice = json['cost_price'] as Map<String, dynamic>? ?? {};
    final retailPrice = json['retail_price'] as Map<String, dynamic>? ?? {};

    return InventoryAlertItem(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      name: json['name'] ?? '',
      sku: json['sku'] ?? '',
      barcode: json['barcode'],
      currentStock: json['current_stock'] ?? 0,
      reorderThreshold: json['reorder_threshold'] ?? 0,
      stockStatus: json['status'] ?? 'out_of_stock',
      stockStatusLabel: json['status_label'] ?? 'منتهي',
      costPrice: (costPrice['raw'] as num? ?? 0.0).toDouble(),
      retailPrice: (retailPrice['raw'] as num? ?? 0.0).toDouble(),
      isBattery: json['is_battery'] ?? false,
      categoryName: json['category_name'] ?? 'قسم عام',
    );
  }

  double get stockRatio {
    if (reorderThreshold <= 0) return 0.0;
    final ratio = currentStock / reorderThreshold;
    return ratio > 1.0 ? 1.0 : ratio;
  }
}
