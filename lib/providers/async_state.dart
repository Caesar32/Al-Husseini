import '../core/network/api_failure.dart';

sealed class AsyncState<T> {
  const AsyncState();

  T? get value;

  bool get isLoading;

  bool get hasValue => value != null;

  ApiFailure? get failure;

  R when<R>({
    required R Function() idle,
    required R Function(T? previous) loading,
    required R Function(T value, DateTime fetchedAt) data,
    required R Function(ApiFailure failure, T? previous) failure,
  });

  AsyncState<T> toLoading() => AsyncLoading<T>(previous: value);

  AsyncState<T> toFailure(ApiFailure f) =>
      AsyncFailure<T>(f, previous: value);
}

final class AsyncIdle<T> extends AsyncState<T> {
  const AsyncIdle();

  @override
  T? get value => null;

  @override
  bool get isLoading => false;

  @override
  ApiFailure? get failure => null;

  @override
  R when<R>({
    required R Function() idle,
    required R Function(T? previous) loading,
    required R Function(T value, DateTime fetchedAt) data,
    required R Function(ApiFailure failure, T? previous) failure,
  }) {
    return idle();
  }
}

final class AsyncLoading<T> extends AsyncState<T> {
  const AsyncLoading({this.previous});

  final T? previous;

  @override
  T? get value => previous;

  @override
  bool get isLoading => true;

  @override
  ApiFailure? get failure => null;

  @override
  R when<R>({
    required R Function() idle,
    required R Function(T? previous) loading,
    required R Function(T value, DateTime fetchedAt) data,
    required R Function(ApiFailure failure, T? previous) failure,
  }) {
    return loading(previous);
  }
}

final class AsyncData<T> extends AsyncState<T> {
  AsyncData(this.value, {DateTime? fetchedAt})
      : fetchedAt = fetchedAt ?? DateTime.now();

  @override
  final T value;

  final DateTime fetchedAt;

  @override
  bool get isLoading => false;

  @override
  ApiFailure? get failure => null;

  @override
  R when<R>({
    required R Function() idle,
    required R Function(T? previous) loading,
    required R Function(T value, DateTime fetchedAt) data,
    required R Function(ApiFailure failure, T? previous) failure,
  }) {
    return data(value, fetchedAt);
  }
}

final class AsyncFailure<T> extends AsyncState<T> {
  const AsyncFailure(this.error, {this.previous});

  final ApiFailure error;
  final T? previous;

  @override
  T? get value => previous;

  @override
  bool get isLoading => false;

  @override
  ApiFailure get failure => error;

  @override
  R when<R>({
    required R Function() idle,
    required R Function(T? previous) loading,
    required R Function(T value, DateTime fetchedAt) data,
    required R Function(ApiFailure failure, T? previous) failure,
  }) {
    return failure(error, previous);
  }
}

ApiFailure toApiFailure(Object error) {
  return error is ApiFailure ? error : const BadResponseFailure();
}

