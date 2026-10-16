import 'package:flutter_test/flutter_test.dart';
import 'package:alhusseini_app/core/network/api_failure.dart';
import 'package:alhusseini_app/core/network/envelope.dart';

void main() {
  group('Response envelope helpers', () {
    test('reads a data map', () {
      final result = requireDataMap({
        'data': {
          'id': 7,
          'name': 'أحمد',
        },
      });

      expect(result, {
        'id': 7,
        'name': 'أحمد',
      });
    });

    test('reads a data list', () {
      final result = requireDataList({
        'data': [1, 2, 3],
      });

      expect(result, [1, 2, 3]);
    });

    test('reads the root body map', () {
      final result = requireBodyMap({
        'summary': {'total': 4},
        'data': [],
      });

      expect(result['summary'], {'total': 4});
      expect(result['data'], isEmpty);
    });

    test('returns an empty list for null data', () {
      expect(requireDataList({'data': null}), isEmpty);
    });

    test('throws for missing data map', () {
      expect(
        () => requireDataMap({}),
        throwsA(isA<BadResponseFailure>()),
      );
    });

    test('throws for an incorrectly typed data map', () {
      expect(
        () => requireDataMap({'data': []}),
        throwsA(isA<BadResponseFailure>()),
      );
    });

    test('throws for missing data list', () {
      expect(
        () => requireDataList({}),
        throwsA(isA<BadResponseFailure>()),
      );
    });

    test('throws for an incorrectly typed data list', () {
      expect(
        () => requireDataList({'data': {}}),
        throwsA(isA<BadResponseFailure>()),
      );
    });

    test('throws when the body is not a map', () {
      expect(
        () => requireBodyMap([]),
        throwsA(isA<BadResponseFailure>()),
      );
    });

    test('reads complete pagination metadata', () {
      final result = readPagination({
        'pagination': {
          'current_page': 2,
          'last_page': 5,
          'per_page': 20,
          'total': 91,
          'has_more': true,
        },
      });

      expect(result.currentPage, 2);
      expect(result.lastPage, 5);
      expect(result.perPage, 20);
      expect(result.total, 91);
      expect(result.hasMore, isTrue);
    });

    test('reads pagination metadata with string integers', () {
      final result = readPagination({
        'pagination': {
          'current_page': '2',
          'last_page': '5',
          'per_page': '20',
          'total': '91',
          'has_more': false,
        },
      });

      expect(result.currentPage, 2);
      expect(result.lastPage, 5);
      expect(result.perPage, 20);
      expect(result.total, 91);
      expect(result.hasMore, isFalse);
    });

    test('uses defaults when pagination is missing', () {
      final result = readPagination(
        {'data': []},
        requestedPage: 3,
      );

      expect(result.currentPage, 3);
      expect(result.lastPage, 3);
      expect(result.perPage, 0);
      expect(result.total, 0);
      expect(result.hasMore, isFalse);
    });
  });
}
