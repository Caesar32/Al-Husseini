import 'package:flutter_test/flutter_test.dart';

import 'package:alhusseini_app/data/models/inventory_alert.dart';

void main() {
  group('InventoryAlertItem.fromJson', () {
    test('reads the complete server shape', () {
      final item = InventoryAlertItem.fromJson({
        'id': 6,
        'name': 'بطارية 70 أمبير',
        'sku': 'BAT-70',
        'barcode': '622000000001',
        'category_name': 'بطاريات',
        'is_battery': true,
        'current_stock': 3,
        'reorder_threshold': 10,
        'depletion_ratio': 0.3,
        'status': 'critical_low',
        'status_label': 'نقص حرج جداً',
        'cost_price': {
          'raw': 1200,
          'formatted': '1,200.00 ج.م',
        },
        'retail_price': {
          'raw': 1500,
          'formatted': '1,500.00 ج.م',
        },
      });

      expect(item.id, 6);
      expect(item.name, 'بطارية 70 أمبير');
      expect(item.sku, 'BAT-70');
      expect(item.barcode, '622000000001');
      expect(item.categoryName, 'بطاريات');
      expect(item.isBattery, isTrue);
      expect(item.currentStock, 3);
      expect(item.reorderThreshold, 10);
      expect(item.depletionRatio, 0.3);
      expect(item.stockRatio, 0.3);
      expect(item.stockStatus, 'critical_low');
      expect(item.costPrice, 1200.0);
      expect(item.retailPrice, 1500.0);
    });

    test('uses defaults for null and missing values', () {
      final item = InventoryAlertItem.fromJson({
        'id': null,
        'name': null,
        'sku': null,
        'barcode': null,
        'current_stock': null,
        'reorder_threshold': null,
        'depletion_ratio': null,
        'status': null,
        'status_label': null,
        'cost_price': null,
        'retail_price': null,
        'is_battery': null,
        'category_name': null,
      });

      expect(item.id, 0);
      expect(item.name, '');
      expect(item.sku, '');
      expect(item.barcode, isNull);
      expect(item.currentStock, 0);
      expect(item.reorderThreshold, 0);
      expect(item.depletionRatio, 0.0);
      expect(item.stockRatio, 0.0);
      expect(item.stockStatus, 'out_of_stock');
      expect(item.stockStatusLabel, 'منتهي');
      expect(item.costPrice, 0.0);
      expect(item.retailPrice, 0.0);
      expect(item.isBattery, isFalse);
      expect(item.categoryName, 'قسم عام');
    });

    test('accepts numeric strings and falls back to stockRatio', () {
      final item = InventoryAlertItem.fromJson({
        'id': '4',
        'current_stock': '2',
        'reorder_threshold': '4',
        'cost_price': {'raw': '100.50'},
        'retail_price': {'raw': '125.75'},
        'is_battery': '1',
      });

      expect(item.id, 4);
      expect(item.currentStock, 2);
      expect(item.reorderThreshold, 4);
      expect(item.depletionRatio, 0.5);
      expect(item.stockRatio, 0.5);
      expect(item.costPrice, 100.5);
      expect(item.retailPrice, 125.75);
      expect(item.isBattery, isTrue);
    });
  });
}
