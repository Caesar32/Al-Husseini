import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:alhusseini_app/core/network/api_service.dart';
import 'package:alhusseini_app/core/storage/auth_storage.dart';
import 'package:alhusseini_app/data/repositories/auth_repository.dart';
import 'package:alhusseini_app/presentation/screens/login_screen.dart';
import 'package:alhusseini_app/presentation/widgets/login/server_settings_sheet.dart';
import 'package:alhusseini_app/providers/auth_provider.dart';

const _savedUser = <String, dynamic>{
  'id': 1,
  'name': 'Owner',
  'email': '',
  'phone': '',
  'role': 'super-admin',
};

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    GoogleFonts.config.allowRuntimeFetching = false;
  });

  testWidgets('server settings button follows the feature flag', (tester) async {
    final context = await _createContext();
    addTearDown(() => _disposeContext(context));

    await tester.pumpWidget(_wrap(
      context.provider,
      const LoginScreen(allowServerSettings: false),
    ));
    await tester.pump();

    expect(find.byIcon(Icons.settings_ethernet_outlined), findsNothing);

    await tester.pumpWidget(_wrap(
      context.provider,
      const LoginScreen(allowServerSettings: true),
    ));
    await tester.pump();

    expect(find.byIcon(Icons.settings_ethernet_outlined), findsOneWidget);
  });

  testWidgets('secure server settings reject http and accept https', (tester) async {
    final context = await _createContext();
    addTearDown(() => _disposeContext(context));

    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: Builder(
          builder: (buildContext) => TextButton(
            onPressed: () => ServerSettingsSheet.show(
              buildContext,
              authProvider: context.provider,
              allowInsecure: false,
            ),
            child: const Text('open'),
          ),
        ),
      ),
    ));
    await tester.tap(find.text('open'));
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.byType(ServerSettingsSheet), findsOneWidget);

    await tester.enterText(find.byType(TextFormField), 'http://example.com');
    await tester.tap(find.text('حفظ وتطبيق'));
    await tester.pump();

    expect(
      find.text('يجب أن يبدأ عنوان الخادم بـ https://'),
      findsOneWidget,
    );

    await tester.enterText(find.byType(TextFormField), 'https://example.com/api');
    await tester.tap(find.text('حفظ وتطبيق'));
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.byType(ServerSettingsSheet), findsNothing);
    expect(context.provider.baseUrl, 'https://example.com/api');
  });
}

Widget _wrap(AuthProvider provider, Widget child) {
  return MaterialApp(
    home: ChangeNotifierProvider.value(
      value: provider,
      child: child,
    ),
  );
}

Future<_AuthContext> _createContext() async {
  SharedPreferences.setMockInitialValues({
    'owner_auth_token': 'token',
    'owner_user_data': jsonEncode(_savedUser),
  });

  final preferences = await SharedPreferences.getInstance();
  final storage = AuthStorage(preferences);
  final apiService = ApiService(
    storage,
    client: MockClient((_) async => http.Response('', 200)),
  );
  final repository = AuthRepository(apiService, storage);
  final provider = AuthProvider(repository);

  return _AuthContext(apiService, provider);
}

Future<void> _disposeContext(_AuthContext context) async {
  context.provider.dispose();
  await context.apiService.dispose();
}

class _AuthContext {
  const _AuthContext(this.apiService, this.provider);

  final ApiService apiService;
  final AuthProvider provider;
}
