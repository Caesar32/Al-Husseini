int readInt(dynamic v, {int fallback = 0}) {
  return _readIntOrNull(v) ?? fallback;
}

int? readIntOrNull(dynamic v) {
  return _readIntOrNull(v);
}

int? _readIntOrNull(dynamic v) {
  if (v is int) {
    return v;
  }

  if (v is double) {
    return v.isFinite ? v.truncate() : null;
  }

  if (v is String) {
    final value = v.trim();
    final integer = int.tryParse(value);
    if (integer != null) {
      return integer;
    }

    final decimal = double.tryParse(value);
    if (decimal != null && decimal.isFinite) {
      return decimal.truncate();
    }
  }

  return null;
}

double readDouble(dynamic v, {double fallback = 0.0}) {
  if (v is num) {
    return v.toDouble();
  }

  if (v is String) {
    final value = v.trim().replaceAll(',', '');
    return double.tryParse(value) ?? fallback;
  }

  return fallback;
}

/// Quantity text with up to 3 decimals and no trailing zeros: 2 -> "2", 2.5 -> "2.5".
String formatQuantity(num value) {
  final fixed = value.toStringAsFixed(3);
  return fixed.replaceFirst(RegExp(r'\.?0+$'), '');
}

String readString(dynamic v, {String fallback = ''}) {
  if (v == null) {
    return fallback;
  }

  if (v is String) {
    return v;
  }

  if (v is num || v is bool) {
    return v.toString();
  }

  return fallback;
}

String? readStringOrNull(dynamic v) {
  final value = readString(v);
  return value.trim().isEmpty ? null : value;
}

bool readBool(dynamic v, {bool fallback = false}) {
  if (v is bool) {
    return v;
  }

  if (v is int) {
    if (v == 1) {
      return true;
    }

    if (v == 0) {
      return false;
    }
  }

  if (v is String) {
    switch (v.trim().toLowerCase()) {
      case '1':
      case 'true':
        return true;
      case '0':
      case 'false':
        return false;
    }
  }

  return fallback;
}

Map<String, dynamic> readMap(dynamic v) {
  if (v is! Map || v.keys.any((key) => key is! String)) {
    return const <String, dynamic>{};
  }

  return Map<String, dynamic>.from(v);
}

List<dynamic> readList(dynamic v) {
  if (v is List) {
    return v;
  }

  return const <dynamic>[];
}

List<T> readModelList<T>(
  dynamic v,
  T Function(Map<String, dynamic>) fromJson,
) {
  return readList(v)
      .whereType<Map>()
      .map((entry) => fromJson(readMap(entry)))
      .toList();
}

final class Money {
  final double raw;
  final String display;

  const Money({
    required this.raw,
    required this.display,
  });

  static const Money zero = Money(
    raw: 0.0,
    display: '0.00 ج.م',
  );

  factory Money.fromJson(dynamic v) {
    if (v == null) {
      return zero;
    }

    if (v is Map) {
      final json = readMap(v);
      final raw = readDouble(json['raw']);

      for (final key in const ['formatted', 'exact', 'compact']) {
        final display = readStringOrNull(json[key]);
        if (display != null) {
          return Money(raw: raw, display: display);
        }
      }

      return Money(raw: raw, display: _defaultDisplay(raw));
    }

    final raw = readDouble(v);
    return Money(raw: raw, display: _defaultDisplay(raw));
  }
}

String _defaultDisplay(double raw) {
  return '${raw.toStringAsFixed(2)} ج.م';
}
