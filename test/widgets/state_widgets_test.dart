import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alhusseini_app/core/network/api_failure.dart';
import 'package:alhusseini_app/presentation/widgets/state/async_state_view.dart';
import 'package:alhusseini_app/presentation/widgets/state/paged_list_view.dart';
import 'package:alhusseini_app/presentation/widgets/state/stale_data_banner.dart';
import 'package:alhusseini_app/providers/async_state.dart';

void main() {
  setUpAll(() {
    GoogleFonts.config.allowRuntimeFetching = false;
  });

  group('AsyncStateView', () {
    testWidgets('يعرض حالة التحميل بدون بيانات سابقة', (tester) async {
      await tester.pumpWidget(
        _wrap(
          AsyncStateView<int>(
            state: const AsyncLoading<int>(),
            builder: (context, value) => Text('القيمة $value'),
            onRetry: () {},
          ),
        ),
      );

      expect(find.byType(CircularProgressIndicator), findsOneWidget);
    });

    testWidgets('يعرض البيانات والحالة الفارغة', (tester) async {
      await tester.pumpWidget(
        _wrap(
          AsyncStateView<int>(
            state: AsyncData<int>(1),
            builder: (context, value) => Text('القيمة $value'),
            onRetry: () {},
          ),
        ),
      );

      expect(find.text('القيمة 1'), findsOneWidget);

      await tester.pumpWidget(
        _wrap(
          AsyncStateView<int>(
            state: AsyncData<int>(0),
            builder: (context, value) => Text('القيمة $value'),
            onRetry: () {},
            isEmpty: (value) => value == 0,
            emptyMessage: 'لا توجد عناصر',
          ),
        ),
      );

      expect(find.text('لا توجد عناصر'), findsOneWidget);
      expect(find.byIcon(Icons.inbox_outlined), findsOneWidget);
    });

    testWidgets('يعرض الخطأ ويستدعي إعادة المحاولة', (tester) async {
      var retryCount = 0;

      await tester.pumpWidget(
        _wrap(
          AsyncStateView<int>(
            state: const AsyncFailure<int>(
              BadResponseFailure(message: 'تعذر الاتصال'),
            ),
            builder: (context, value) => Text('القيمة $value'),
            onRetry: () => retryCount++,
          ),
        ),
      );

      expect(find.text('تعذر الاتصال'), findsOneWidget);
      expect(find.text('تحديث'), findsOneWidget);

      await tester.tap(find.text('تحديث'));
      expect(retryCount, 1);
    });

    testWidgets('يعرض البيانات السابقة مع رسالة الخطأ', (tester) async {
      await tester.pumpWidget(
        _wrap(
          AsyncStateView<int>(
            state: const AsyncFailure<int>(
              BadResponseFailure(message: 'بيانات قديمة'),
              previous: 7,
            ),
            builder: (context, value) => Text('القيمة $value'),
            onRetry: () {},
          ),
        ),
      );

      expect(find.text('بيانات قديمة'), findsOneWidget);
      expect(find.text('القيمة 7'), findsOneWidget);
      expect(find.byIcon(Icons.warning_amber_rounded), findsOneWidget);
    });

    testWidgets('يعرض البيانات السابقة أثناء التحميل', (tester) async {
      await tester.pumpWidget(
        _wrap(
          AsyncStateView<int>(
            state: const AsyncLoading<int>(previous: 7),
            builder: (context, value) => Text('القيمة $value'),
            onRetry: () {},
          ),
        ),
      );

      expect(find.text('القيمة 7'), findsOneWidget);
      expect(find.byType(LinearProgressIndicator), findsOneWidget);
    });

    testWidgets('يعرض آخر وقت مزامنة بصيغة آمنة', (tester) async {
      await tester.pumpWidget(
        _wrap(
          StaleDataBanner(
            message: 'تعذر تحديث البيانات',
            lastSync: DateTime(2026, 10, 17, 9, 5),
          ),
        ),
      );

      expect(find.text('آخر تحديث: 09:05'), findsOneWidget);
    });
  });

  group('PagedListView', () {
    testWidgets('يستدعي تحميل المزيد عند الوصول لنهاية القائمة', (tester) async {
      var loadMoreCount = 0;

      await tester.pumpWidget(
        _wrap(
          SizedBox(
            height: 300,
            child: PagedListView<int>(
              items: List<int>.generate(30, (index) => index),
              itemBuilder: (context, item, index) {
                return SizedBox(
                  height: 48,
                  child: Text('عنصر $item'),
                );
              },
              hasMore: true,
              isLoadingMore: false,
              onLoadMore: () => loadMoreCount++,
              onRefresh: () async {},
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();
      await tester.drag(
        find.byType(ListView),
        const Offset(0, -1400),
      );
      await tester.pump();

      expect(loadMoreCount, 1);
    });

    testWidgets('يعرض خطأ تحميل المزيد وزر إعادة المحاولة', (tester) async {
      await tester.pumpWidget(
        _wrap(
          SizedBox(
            height: 300,
            child: PagedListView<int>(
              items: const [1],
              itemBuilder: (context, item, index) {
                return SizedBox(
                  height: 48,
                  child: Text('عنصر $item'),
                );
              },
              hasMore: true,
              isLoadingMore: false,
              onLoadMore: () {},
              onRefresh: () async {},
              loadMoreError: 'تعذر تحميل الصفحة التالية',
            ),
          ),
        ),
      );

      expect(find.text('تعذر تحميل المزيد'), findsOneWidget);
      expect(find.text('إعادة المحاولة'), findsOneWidget);
      expect(find.byType(TextButton), findsOneWidget);
    });
  });
}

Widget _wrap(Widget child) {
  return MaterialApp(
    home: Directionality(
      textDirection: TextDirection.rtl,
      child: child,
    ),
  );
}
