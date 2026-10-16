import 'package:flutter_test/flutter_test.dart';

import 'package:alhusseini_app/data/models/json_read.dart';

void main() {
  group('readInt', () {
    final cases = <({dynamic value, int expected})>[
      (value: 12, expected: 12),
      (value: 12.8, expected: 12),
      (value: -12.8, expected: -12),
      (value: '12', expected: 12),
      (value: ' 12.0 ', expected: 12),
      (value: '12.8', expected: 12),
    ];

    for (final row in cases) {
      test('reads ${row.value}', () {
        expect(readInt(row.value), row.expected);
      });
    }

    test('uses the fallback for invalid values', () {
      expect(readInt(null, fallback: 7), 7);
      expect(readInt(true, fallback: 7), 7);
      expect(readInt('garbage', fallback: 7), 7);
      expect(readInt('1,234', fallback: 7), 7);
      expect(readInt(double.infinity, fallback: 7), 7);
      expect(readInt(double.nan, fallback: 7), 7);
    });
  });

  group('readIntOrNull', () {
    final cases = <({dynamic value, int? expected})>[
      (value: 12, expected: 12),
      (value: 12.8, expected: 12),
      (value: '12.0', expected: 12),
      (value: null, expected: null),
      (value: true, expected: null),
      (value: 'garbage', expected: null),
      (value: double.infinity, expected: null),
    ];

    for (final row in cases) {
      test('reads ${row.value}', () {
        expect(readIntOrNull(row.value), row.expected);
      });
    }
  });

  group('readDouble', () {
    final cases = <({dynamic value, double expected})>[
      (value: 12, expected: 12.0),
      (value: 12.5, expected: 12.5),
      (value: '12', expected: 12.0),
      (value: ' 12.50 ', expected: 12.5),
      (value: '1,234.50', expected: 1234.5),
      (value: '-1,234', expected: -1234.0),
    ];

    for (final row in cases) {
      test('reads ${row.value}', () {
        expect(readDouble(row.value), row.expected);
      });
    }

    test('uses the fallback for invalid values', () {
      expect(readDouble(null, fallback: 7.5), 7.5);
      expect(readDouble(true, fallback: 7.5), 7.5);
      expect(readDouble('garbage', fallback: 7.5), 7.5);
    });
  });

  group('readString', () {
    final cases = <({dynamic value, String expected})>[
      (value: null, expected: ''),
      (value: 'text', expected: 'text'),
      (value: '', expected: ''),
      (value: 12, expected: '12'),
      (value: 12.5, expected: '12.5'),
      (value: true, expected: 'true'),
      (value: false, expected: 'false'),
    ];

    for (final row in cases) {
      test('reads ${row.value}', () {
        expect(readString(row.value), row.expected);
      });
    }

    test('uses the fallback for unsupported values', () {
      expect(readString({'value': 1}, fallback: 'غير متاح'), 'غير متاح');
      expect(readString([1, 2], fallback: 'غير متاح'), 'غير متاح');
    });
  });

  group('readStringOrNull', () {
    final cases = <({dynamic value, String? expected})>[
      (value: null, expected: null),
      (value: '', expected: null),
      (value: '   ', expected: null),
      (value: ' text ', expected: ' text '),
      (value: 12, expected: '12'),
      (value: false, expected: 'false'),
      (value: {'value': 1}, expected: null),
    ];

    for (final row in cases) {
      test('reads ${row.value}', () {
        expect(readStringOrNull(row.value), row.expected);
      });
    }
  });

  group('readBool', () {
    final cases = <({dynamic value, bool expected})>[
      (value: true, expected: true),
      (value: false, expected: false),
      (value: 1, expected: true),
      (value: 0, expected: false),
      (value: '1', expected: true),
      (value: '0', expected: false),
      (value: 'true', expected: true),
      (value: 'TRUE', expected: true),
      (value: ' false ', expected: false),
    ];

    for (final row in cases) {
      test('reads ${row.value}', () {
        expect(readBool(row.value), row.expected);
      });
    }

    test('uses the fallback for invalid values', () {
      expect(readBool(null, fallback: true), true);
      expect(readBool(2, fallback: true), true);
      expect(readBool('yes', fallback: true), true);
      expect(readBool(1.0, fallback: true), true);
    });
  });

  group('readMap', () {
    test('copies maps with string keys', () {
      final source = {'name': 'أحمد', 'count': 2};
      final result = readMap(source);

      expect(result, source);
      expect(identical(result, source), isFalse);
    });

    test('returns an empty map for non-string keys', () {
      expect(readMap({1: 'value'}), isEmpty);
      expect(readMap({'name': 'أحمد', 2: 'value'}), isEmpty);
      expect(readMap(null), isEmpty);
      expect(readMap([]), isEmpty);
    });
  });

  group('readList', () {
    test('returns lists unchanged', () {
      final source = [1, 'two', true];
      final result = readList(source);

      expect(result, source);
      expect(identical(result, source), isTrue);
    });

    test('returns an empty list for non-list values', () {
      expect(readList(null), isEmpty);
      expect(readList({'value': 1}), isEmpty);
      expect(readList('value'), isEmpty);
    });
  });

  group('readModelList', () {
    test('keeps maps and converts them with fromJson', () {
      final result = readModelList<int>(
        [
          {'id': '7'},
          'ignored',
          {1: 'invalid'},
          {'id': 3},
        ],
        (json) => readInt(json['id'], fallback: -1),
      );

      expect(result, [7, -1, 3]);
    });

    test('returns an empty list for non-list values', () {
      expect(
        readModelList<int>(null, (_) => 1),
        isEmpty,
      );
    });
  });

  group('Money', () {
    test('prefers formatted over exact and compact', () {
      final money = Money.fromJson({
        'raw': 1234.5,
        'formatted': '١٬٢٣٤٫٥٠ ج.م',
        'exact': '1234.50 ج.م',
        'compact': '1.23K ج.م',
      });

      expect(money.raw, 1234.5);
      expect(money.display, '١٬٢٣٤٫٥٠ ج.م');
    });

    test('uses exact when formatted is empty', () {
      final money = Money.fromJson({
        'raw': 1234.5,
        'formatted': '',
        'exact': '1234.50 ج.م',
        'compact': '1.23K ج.م',
      });

      expect(money.display, '1234.50 ج.م');
    });

    test('uses compact when formatted and exact are empty', () {
      final money = Money.fromJson({
        'raw': 1234.5,
        'formatted': '  ',
        'exact': '',
        'compact': '1.23K ج.م',
      });

      expect(money.display, '1.23K ج.م');
    });

    test('falls back to fixed raw display when no formatted value exists', () {
      final money = Money.fromJson({'raw': '1,234.5'});

      expect(money.raw, 1234.5);
      expect(money.display, '1234.50 ج.م');
    });

    test('supports bare numbers and numeric strings', () {
      final number = Money.fromJson(12.5);
      final string = Money.fromJson('1,234.50');

      expect(number.raw, 12.5);
      expect(number.display, '12.50 ج.م');
      expect(string.raw, 1234.5);
      expect(string.display, '1234.50 ج.م');
    });

    test('returns zero for null', () {
      final money = Money.fromJson(null);

      expect(money.raw, 0.0);
      expect(money.display, '0.00 ج.م');
      expect(money, Money.zero);
    });
  });
}
