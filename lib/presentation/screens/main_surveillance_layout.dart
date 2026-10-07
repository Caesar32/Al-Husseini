import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../providers/auth_provider.dart';
import '../../providers/dashboard_provider.dart';
import 'login_screen.dart';
import 'tabs/cash_drawer_tab.dart';
import 'tabs/credit_receivables_tab.dart';
import 'tabs/inventory_alerts_tab.dart';
import 'tabs/live_pulse_tab.dart';
import 'tabs/sales_stream_tab.dart';
import 'tabs/staff_attendance_tab.dart';

class MainSurveillanceLayout extends StatefulWidget {
  const MainSurveillanceLayout({super.key});

  @override
  State<MainSurveillanceLayout> createState() => _MainSurveillanceLayoutState();
}

class _MainSurveillanceLayoutState extends State<MainSurveillanceLayout> {
  int _currentIndex = 0;

  final List<Widget> _tabs = [
    const LivePulseTab(),
    const SalesStreamTab(),
    const CashDrawerTab(),
    const InventoryAlertsTab(),
    const StaffAttendanceTab(),
    const CreditReceivablesTab(),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<DashboardProvider>(context, listen: false).startLivePolling();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.scaffoldBackground,
      body: IndexedStack(
        index: _currentIndex,
        children: _tabs,
      ),
      bottomNavigationBar: Container(
        decoration: const BoxDecoration(
          color: AppColors.surface,
          border: Border(
            top: BorderSide(color: AppColors.strokeBorder, width: 1),
          ),
        ),
        child: NavigationBar(
          selectedIndex: _currentIndex,
          onDestinationSelected: (index) {
            setState(() {
              _currentIndex = index;
            });
          },
          destinations: const [
            NavigationDestination(
              icon: Icon(Icons.monitor_heart_outlined),
              selectedIcon: Icon(Icons.monitor_heart, color: AppColors.goldAccent),
              label: 'النبض المباشر',
            ),
            NavigationDestination(
              icon: Icon(Icons.receipt_long_outlined),
              selectedIcon: Icon(Icons.receipt_long, color: AppColors.goldAccent),
              label: 'المبيعات',
            ),
            NavigationDestination(
              icon: Icon(Icons.account_balance_wallet_outlined),
              selectedIcon: Icon(Icons.account_balance_wallet, color: AppColors.goldAccent),
              label: 'الخزينة',
            ),
            NavigationDestination(
              icon: Icon(Icons.inventory_2_outlined),
              selectedIcon: Icon(Icons.inventory_2, color: AppColors.goldAccent),
              label: 'النواقص',
            ),
            NavigationDestination(
              icon: Icon(Icons.badge_outlined),
              selectedIcon: Icon(Icons.badge, color: AppColors.goldAccent),
              label: 'الحضور',
            ),
            NavigationDestination(
              icon: Icon(Icons.credit_card_outlined),
              selectedIcon: Icon(Icons.credit_card, color: AppColors.goldAccent),
              label: 'الآجل',
            ),
          ],
        ),
      ),
      drawer: _buildExecutiveDrawer(context),
    );
  }

  Widget _buildExecutiveDrawer(BuildContext context) {
    final user = Provider.of<AuthProvider>(context).currentUser;

    return Drawer(
      backgroundColor: AppColors.surface,
      child: SafeArea(
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: const BoxDecoration(
                color: AppColors.elevatedSurface,
                border: Border(bottom: BorderSide(color: AppColors.strokeBorder)),
              ),
              child: Row(
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: AppColors.primaryGradient,
                      border: Border.all(color: AppColors.goldAccent),
                    ),
                    child: const Icon(Icons.person, color: AppColors.goldAccent),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          user?.name ?? 'مالك المنشأة',
                          style: GoogleFonts.cairo(
                            fontSize: 15,
                            fontWeight: FontWeight.bold,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        Text(
                          user?.email ?? '',
                          style: GoogleFonts.outfit(
                            fontSize: 11,
                            color: AppColors.textMuted,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            ListTile(
              leading: const Icon(Icons.refresh, color: AppColors.goldAccent),
              title: Text('إعادة مزامنة النبض المباشر', style: GoogleFonts.cairo(color: AppColors.textPrimary)),
              onTap: () {
                Navigator.pop(context);
                Provider.of<DashboardProvider>(context, listen: false).fetchLivePulse(isBackground: false);
              },
            ),
            const Spacer(),
            const Divider(),
            ListTile(
              leading: const Icon(Icons.logout, color: AppColors.stockDepletedDanger),
              title: Text('تسجيل الخروج', style: GoogleFonts.cairo(color: AppColors.stockDepletedDanger, fontWeight: FontWeight.bold)),
              onTap: () async {
                final auth = Provider.of<AuthProvider>(context, listen: false);
                await auth.logout();
                if (context.mounted) {
                  Navigator.of(context).pushReplacement(
                    MaterialPageRoute(builder: (_) => const LoginScreen()),
                  );
                }
              },
            ),
            const SizedBox(height: 12),
          ],
        ),
      ),
    );
  }
}
