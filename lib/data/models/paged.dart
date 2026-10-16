import '../../core/network/envelope.dart';

final class Paged<T> {
  final List<T> items;
  final PaginationMeta meta;

  const Paged(this.items, this.meta);

  bool get hasMore => meta.hasMore;
}
