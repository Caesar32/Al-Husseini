import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'core/network/api_service.dart';
import 'core/storage/auth_storage.dart';
import 'core/theme/app_theme.dart';

import 'data/repositories/auth_repository.dart';
import 'data/repositories/cash_drawer_repository.dart';
import 'data/repositories/credit_repository.dart';
import 'data/repositories/dashboard_repository.dart';
import 'data/repositories/inventory_repository.dart';
import 'data/repositories/sales_repository.dart';
import 'data/repositories/staff_repository.dart';

import 'presentation/screens/login_screen.dart';
import 'presentation/screens/splash_screen.dart';

import 'providers/auth_provider.dart';
import 'providers/cash_drawer_provider.dart';
import 'providers/credit_provider.dart';
import 'providers/dashboard_provider.dart';
import 'providers/inventory_provider.dart';
import 'providers/sales_provider.dart';
import 'providers/staff_provider.dart';

final navigatorKey = GlobalKey<NavigatorState>();
final scaffoldMessengerKey = GlobalKey<ScaffoldMessengerState>();

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Lock orientation to portrait mode for executive mobile UX
  await SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);

  // Initialize Storage & API Service
  final authStorage = await AuthStorage.init();
  final apiService = ApiService(authStorage);

  // Initialize Repositories
  final authRepository = AuthRepository(apiService, authStorage);
  final dashboardRepository = DashboardRepository(apiService);
  final salesRepository = SalesRepository(apiService);
  final cashDrawerRepository = CashDrawerRepository(apiService);
  final inventoryRepository = InventoryRepository(apiService);
  final staffRepository = StaffRepository(apiService);
  final creditRepository = CreditRepository(apiService);

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider(authRepository)),
        ChangeNotifierProvider(
          create: (_) => DashboardProvider(
            dashboardRepository,
            storage: authStorage,
          ),
        ),
        ChangeNotifierProvider(create: (_) => SalesProvider(salesRepository)),
        ChangeNotifierProvider(
          create: (_) => CashDrawerProvider(cashDrawerRepository),
        ),
        ChangeNotifierProvider(
          create: (_) => InventoryProvider(inventoryRepository),
        ),
        ChangeNotifierProvider(create: (_) => StaffProvider(staffRepository)),
        ChangeNotifierProvider(create: (_) => CreditProvider(creditRepository)),
      ],
      child: const AlHusseiniExecutiveApp(),
    ),
  );
}

class AlHusseiniExecutiveApp extends StatefulWidget {
  const AlHusseiniExecutiveApp({super.key});

  @override
  State<AlHusseiniExecutiveApp> createState() => _AlHusseiniExecutiveAppState();
}

class _AlHusseiniExecutiveAppState extends State<AlHusseiniExecutiveApp> {
  AuthProvider? _authProvider;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();

    _authProvider ??= context.read<AuthProvider>()
      ..addListener(_handleAuthChanged);
  }

  void _handleAuthChanged() {
    final authProvider = _authProvider;
    if (!mounted ||
        authProvider == null ||
        authProvider.status != AuthStatus.unauthenticated) {
      return;
    }

    context.read<DashboardProvider>().stopLivePolling();

    if (!authProvider.sessionExpired) return;

    final message = authProvider.errorMessage;
    authProvider.acknowledgeSessionExpired();

    navigatorKey.currentState?.pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (_) => false,
    );

    if (message != null && message.isNotEmpty) {
      scaffoldMessengerKey.currentState?.showSnackBar(
        SnackBar(content: Text(message)),
      );
    }
  }

  @override
  void dispose() {
    _authProvider?.removeListener(_handleAuthChanged);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      navigatorKey: navigatorKey,
      scaffoldMessengerKey: scaffoldMessengerKey,
      title: 'مركز الحسيني - لوحة المالك التنفيذية',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.midnightExecutiveTheme,
      darkTheme: AppTheme.midnightExecutiveTheme,
      themeMode: ThemeMode.dark,
      locale: const Locale('ar', 'EG'),
      supportedLocales: const [
        Locale('ar', 'EG'),
        Locale('en', 'US'),
      ],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: const SplashScreen(),
    );
  }
}
