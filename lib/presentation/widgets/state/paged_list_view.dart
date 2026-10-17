import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../core/theme/app_colors.dart';

class PagedListView<T> extends StatefulWidget {
  const PagedListView({
    required this.items,
    required this.itemBuilder,
    required this.hasMore,
    required this.isLoadingMore,
    required this.onLoadMore,
    required this.onRefresh,
    this.loadMoreError,
    this.padding,
    this.header,
    super.key,
  });

  final List<T> items;
  final Widget Function(BuildContext, T, int) itemBuilder;
  final bool hasMore;
  final bool isLoadingMore;
  final VoidCallback onLoadMore;
  final Future<void> Function() onRefresh;
  final String? loadMoreError;
  final EdgeInsetsGeometry? padding;
  final Widget? header;

  @override
  State<PagedListView<T>> createState() => _PagedListViewState<T>();
}

class _PagedListViewState<T> extends State<PagedListView<T>> {
  late final ScrollController _scrollController;
  bool _loadMoreRequested = false;

  @override
  void initState() {
    super.initState();
    _scrollController = ScrollController()..addListener(_onScroll);
  }

  @override
  void didUpdateWidget(covariant PagedListView<T> oldWidget) {
    super.didUpdateWidget(oldWidget);

    if (oldWidget.items.length != widget.items.length ||
        oldWidget.hasMore != widget.hasMore ||
        oldWidget.isLoadingMore != widget.isLoadingMore ||
        oldWidget.loadMoreError != widget.loadMoreError) {
      _loadMoreRequested = false;
    }
  }

  @override
  void dispose() {
    _scrollController
      ..removeListener(_onScroll)
      ..dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final headerCount = widget.header == null ? 0 : 1;
    final hasFooter =
        widget.isLoadingMore || widget.loadMoreError != null;

    return RefreshIndicator(
      onRefresh: widget.onRefresh,
      child: ListView.builder(
        controller: _scrollController,
        padding: widget.padding,
        physics: const AlwaysScrollableScrollPhysics(),
        itemCount: headerCount +
            widget.items.length +
            (hasFooter ? 1 : 0),
        itemBuilder: (context, index) {
          if (widget.header != null && index == 0) {
            return widget.header!;
          }

          final itemIndex = index - headerCount;
          if (itemIndex < widget.items.length) {
            return widget.itemBuilder(
              context,
              widget.items[itemIndex],
              itemIndex,
            );
          }

          return _buildFooter();
        },
      ),
    );
  }

  void _onScroll() {
    if (!_scrollController.hasClients) {
      return;
    }

    final position = _scrollController.position;
    if (position.extentAfter > 200 ||
        !widget.hasMore ||
        widget.isLoadingMore ||
        widget.loadMoreError != null ||
        _loadMoreRequested) {
      return;
    }

    _loadMoreRequested = true;
    widget.onLoadMore();
  }

  Widget _buildFooter() {
    if (widget.isLoadingMore) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 16),
        child: Center(
          child: SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: AppColors.goldAccent,
            ),
          ),
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.only(
        left: 12,
        right: 12,
        bottom: 8,
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            'تعذر تحميل المزيد',
            style: GoogleFonts.almarai(
              color: AppColors.textSecondary,
              fontSize: 12,
            ),
          ),
          TextButton(
            onPressed: () {
              _loadMoreRequested = true;
              widget.onLoadMore();
            },
            child: Text(
              'إعادة المحاولة',
              style: GoogleFonts.cairo(
                color: AppColors.goldAccent,
                fontSize: 12,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
