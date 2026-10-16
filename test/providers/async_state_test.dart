import 'package:flutter_test/flutter_test.dart';
import 'package:alhusseini_app/core/network/api_failure.dart';
import 'package:alhusseini_app/providers/async_state.dart';

void main() {
  group('AsyncState', () {
    test('propagates value and previous through loading and failure', () {
      final fetchedAt = DateTime(2026, 10, 17);
      final data = AsyncData<int>(42, fetchedAt: fetchedAt);
      final loading = data.toLoading();
      final failure = const BadResponseFailure();
      final failed = loading.toFailure(failure);

      expect(data.value, 42);
      expect(data.hasValue, isTrue);
      expect(data.fetchedAt, fetchedAt);
      expect(loading.value, 42);
      expect(loading.isLoading, isTrue);
      expect(failed.value, 42);
      expect(failed.failure, same(failure));
      expect(failed.isLoading, isFalse);
    });

    test('dispatches when for every state', () {
      const failure = BadResponseFailure();
      final fetchedAt = DateTime(2026, 10, 17);

      expect(
        const AsyncIdle<int>().when<String>(
          idle: () => 'idle',
          loading: (previous) => 'loading',
          data: (value, date) => 'data',
          failure: (error, previous) => 'failure',
        ),
        'idle',
      );
      expect(
        const AsyncLoading<int>(previous: 1).when<String>(
          idle: () => 'idle',
          loading: (previous) => 'loading $previous',
          data: (value, date) => 'data',
          failure: (error, previous) => 'failure',
        ),
        'loading 1',
      );
      expect(
        AsyncData<int>(2, fetchedAt: fetchedAt).when<String>(
          idle: () => 'idle',
          loading: (previous) => 'loading',
          data: (value, date) => 'data $value ${date == fetchedAt}',
          failure: (error, previous) => 'failure',
        ),
        'data 2 true',
      );
      expect(
        const AsyncFailure<int>(failure, previous: 3).when<String>(
          idle: () => 'idle',
          loading: (previous) => 'loading',
          data: (value, date) => 'data',
          failure: (error, previous) => 'failure ${error == failure} $previous',
        ),
        'failure true 3',
      );
    });

    test('converts ApiFailure without exposing another error', () {
      const failure = NetworkFailure();

      expect(toApiFailure(failure), same(failure));
      expect(toApiFailure(TypeError()), isA<BadResponseFailure>());
      expect(
        toApiFailure(const FormatException('invalid response')),
        isA<BadResponseFailure>(),
      );
    });
  });
}

