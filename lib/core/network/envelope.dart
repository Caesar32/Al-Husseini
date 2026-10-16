import 'api_failure.dart';

Map<String, dynamic> requireDataMap(dynamic body) {
  final root = _asStringMap(body);
  return _asStringMap(root['data']);
}

List<dynamic> requireDataList(dynamic body) {
  final root = _asStringMap(body);

  if (!root.containsKey('data')) {
    throw const BadResponseFailure();
  }

  final data = root['data'];
  if (data == null) {
    return <dynamic>[];
  }

  if (data is! List) {
    throw const BadResponseFailure();
  }

  return List<dynamic>.from(data);
}

Map<String, dynamic> requireBodyMap(dynamic body) {
  return _asStringMap(body);
}

PaginationMeta readPagination(
  dynamic body, {
  int requestedPage = 1,
}) {
  final root = _asStringMap(body);

  if (!root.containsKey('pagination')) {
    return PaginationMeta(
      currentPage: requestedPage,
      lastPage: requestedPage,
      perPage: 0,
      total: 0,
      hasMore: false,
    );
  }

  final pagination = _asStringMap(root['pagination']);

  return PaginationMeta(
    currentPage: _readInt(pagination, 'current_page'),
    lastPage: _readInt(pagination, 'last_page'),
    perPage: _readInt(pagination, 'per_page'),
    total: _readInt(pagination, 'total'),
    hasMore: _readBool(pagination, 'has_more'),
  );
}

final class PaginationMeta {
  const PaginationMeta({
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.hasMore,
  });

  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;
  final bool hasMore;
}

Map<String, dynamic> _asStringMap(dynamic value) {
  if (value is! Map) {
    throw const BadResponseFailure();
  }

  final result = <String, dynamic>{};
  for (final entry in value.entries) {
    if (entry.key is! String) {
      throw const BadResponseFailure();
    }

    result[entry.key as String] = entry.value;
  }

  return result;
}

int _readInt(Map<String, dynamic> map, String key) {
  final value = map[key];

  if (value is int) {
    return value;
  }

  if (value is String) {
    final parsed = int.tryParse(value.trim());
    if (parsed != null) {
      return parsed;
    }
  }

  throw const BadResponseFailure();
}

bool _readBool(Map<String, dynamic> map, String key) {
  final value = map[key];

  if (value is bool) {
    return value;
  }

  throw const BadResponseFailure();
}
