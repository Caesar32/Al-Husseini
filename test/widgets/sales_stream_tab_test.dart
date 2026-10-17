import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:alhusseini_app/core/network/api_failure.dart';
import 'package:alhusseini_app/core/network/api_service.dart';
import 'package:alhusseini_app/core/network/envelope.dart';
import 'package:alhusseini_app/core/storage/auth_storage.dart';
import 'package:alhusseini_app/data/models/paged.dart';
import 'package:alhusseini_app/data/models/recent_invoice.dart';
import 'package:alhusseini_app/data/models/return_summary.dart';
import 'package:alhusseini_app/data/repositories/dashboard_repository.dart';
import 'package:alhusseini_app/data/repositories/sales_repository.dart';
import 'package:alhusseini_app/presentation/screens/tabs/sales_stream_tab.dart';
import 'package:alhusseini_app/providers/dashboard_provider.dart';
import 'package:alhusseini_app/providers/sales_provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    GoogleFonts.config.allowRuntimeFetching = false;
  });

  late ApiService apiService;

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    final preferences = await SharedPreferences.getInstance();
    apiService = ApiService(
      AuthStorage(preferences),
      client: MockClient((_) async => http.Response('', 200)),
    );
    addTearDown(apiService.dispose);
  });

  testWidgets('يعرض الفواتير والمرتجعات ورسالة خطأ التفاصيل', (tester) async {
    final repository = _FakeSalesRepository(apiService)
      ..invoicePages[1] = _invoicePage(
        List<InvoiceSummary>.generate(
          20,
          (index) => _invoice(index + 1),
        ),
        page: 1,
        hasMore: true,
      )
      ..invoicePages[2] = _invoicePage(
        [_invoice(21)],
        page: 2,
        hasMore: false,
      )
      ..returnPages[1] = _returnPage(
        [_returnSummary()],
        page: 1,
        hasMore: false,
      )
      ..detailFailure = const NetworkFailure(
        message: 'تعذر تحميل تفاصيل الفاتورة',
      );

    final sales = SalesProvider(repository);
    final dashboard = DashboardProvider(_FakeDashboardRepository(apiService));
    addTearDown(sales.dispose);
    addTearDown(dashboard.dispose);

    await tester.pumpWidget(
      _wrap(
        MultiProvider(
          providers: [
            ChangeNotifierProvider.value(value: dashboard),
            ChangeNotifierProvider.value(value: sales),
          ],
          child: const SalesStreamTab(),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    expect(find.text('فاتورة INV-1'), findsOneWidget);

    await tester.drag(
      find.byType(ListView),
      const Offset(0, -5000),
    );
    await tester.pump();
    await tester.pump();

    expect(repository.invoicePagesRequested, contains(2));

    await tester.tap(find.text('المرتجعات'));
    await tester.pump();
    await tester.pump();

    expect(find.text('فاتورة RET-1'), findsOneWidget);
    expect(find.text('250.00 ج.م'), findsOneWidget);

    await tester.tap(find.text('الفواتير'));
    await tester.pump();
    await tester.pump();
    await tester.drag(
      find.byType(ListView),
      const Offset(0, 5000),
    );
    await tester.pump();

    await tester.tap(find.text('فاتورة INV-1'));
    await tester.pump();
    await tester.pump();

    expect(find.text('تعذر تحميل تفاصيل الفاتورة'), findsOneWidget);
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

class _FakeSalesRepository extends SalesRepository {
  _FakeSalesRepository(super.apiService);

  final Map<int, Paged<InvoiceSummary>> invoicePages =
      <int, Paged<InvoiceSummary>>{};
  final Map<int, Paged<ReturnSummary>> returnPages =
      <int, Paged<ReturnSummary>>{};
  final List<int> invoicePagesRequested = <int>[];
  ApiFailure? detailFailure;

  @override
  Future<Paged<InvoiceSummary>> getRecentInvoices({
    int page = 1,
    int perPage = 20,
    int? branchId,
  }) async {
    invoicePagesRequested.add(page);
    return invoicePages[page] ??
        _invoicePage(
          const <InvoiceSummary>[],
          page: page,
          hasMore: false,
        );
  }

  @override
  Future<InvoiceDetail> getInvoiceDetail(int id) async {
    final failure = detailFailure;
    if (failure != null) {
      throw failure;
    }

    return InvoiceDetail.fromJson({
      'id': id,
      'invoice_number': 'INV-$id',
    });
  }

  @override
  Future<Paged<ReturnSummary>> getRecentReturns({
    int page = 1,
    int perPage = 20,
    int? branchId,
  }) async {
    return returnPages[page] ??
        _returnPage(
          const <ReturnSummary>[],
          page: page,
          hasMore: false,
        );
  }
}

class _FakeDashboardRepository extends DashboardRepository {
  _FakeDashboardRepository(super.apiService);
}

InvoiceSummary _invoice(int id) {
  return InvoiceSummary.fromJson({
    'id': id,
    'invoice_number': 'INV-$id',
    'customer_name': 'عميل $id',
    'customer_phone': '010000000$id',
    'final_amount': {'raw': id},
  });
}

ReturnSummary _returnSummary() {
  return ReturnSummary.fromJson({
    'id': 1,
    'invoice_number': 'RET-1',
    'customer_name': 'عميل مرتجع',
    'time': 'منذ 5 دقائق',
    'status': 'refunded',
    'refunded_amount': {
      'raw': 250,
      'formatted': '250.00 ج.م',
    },
    'original_amount': {
      'raw': 500,
      'formatted': '500.00 ج.م',
    },
  });
}

Paged<InvoiceSummary> _invoicePage(
  List<InvoiceSummary> items, {
  required int page,
  required bool hasMore,
}) {
  return Paged(
    items,
    PaginationMeta(
      currentPage: page,
      lastPage: hasMore ? page + 1 : page,
      perPage: 20,
      total: items.length,
      hasMore: hasMore,
    ),
  );
}

Paged<ReturnSummary> _returnPage(
  List<ReturnSummary> items, {
  required int page,
  required bool hasMore,
}) {
  return Paged(
    items,
    PaginationMeta(
      currentPage: page,
      lastPage: hasMore ? page + 1 : page,
      perPage: 20,
      total: items.length,
      hasMore: hasMore,
    ),
  );
}
