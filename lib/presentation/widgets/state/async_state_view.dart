import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../providers/async_state.dart';
import 'empty_state_card.dart';
import 'error_retry_card.dart';
import 'stale_data_banner.dart';

class AsyncStateView<T> extends StatelessWidget {
  const AsyncStateView({
    required this.state,
    required this.builder,
    required this.onRetry,
    this.isEmpty,
    this.emptyMessage = 'لا توجد بيانات حالياً.',
    this.loading,
    super.key,
  });

  final AsyncState<T> state;
  final Widget Function(BuildContext, T) builder;
  final VoidCallback onRetry;
  final bool Function(T)? isEmpty;
  final String emptyMessage;
  final Widget? loading;

  @override
  Widget build(BuildContext context) {
    return state.when<Widget>(
      idle: () => _buildLoading(),
      loading: (previous) {
        if (previous == null) {
          return _buildLoading();
        }

        return Column(
          children: [
            const LinearProgressIndicator(
              minHeight: 2,
              color: AppColors.goldAccent,
            ),
            Expanded(
              child: builder(context, previous),
            ),
          ],
        );
      },
      data: (value, _) {
        if (isEmpty?.call(value) ?? false) {
          return EmptyStateCard(message: emptyMessage);
        }

        return builder(context, value);
      },
      failure: (failure, previous) {
        if (previous == null) {
          return ErrorRetryCard(
            message: failure.message,
            onRetry: onRetry,
          );
        }

        return Column(
          children: [
            StaleDataBanner(
              message: failure.message,
              onRetry: onRetry,
            ),
            Expanded(
              child: builder(context, previous),
            ),
          ],
        );
      },
    );
  }

  Widget _buildLoading() {
    return loading ??
        const Center(
          child: CircularProgressIndicator(
            color: AppColors.goldAccent,
          ),
        );
  }
}
