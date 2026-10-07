import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../providers/cash_drawer_provider.dart';
import '../../../providers/dashboard_provider.dart';
import '../../widgets/arabic_tabular_text.dart';
import '../../widgets/executive_app_bar.dart';
import '../../widgets/status_badge.dart';

class CashDrawerTab extends StatefulWidget {
  const CashDrawerTab({super.key});

  @override
  State<CashDrawerTab> createState() => _CashDrawerTabState();
}

class _CashDrawerTabState extends State<CashDrawerTab> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final branchId = Provider.of<DashboardProvider>(context, listen: false).selectedBranchId;
      Provider.of<CashDrawerProvider>(context, listen: false).fetchCurrentShift(branchId: branchId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final branchId = Provider.of<DashboardProvider>(context).selectedBranchId;

    return Scaffold(
      backgroundColor: AppColors.scaffoldBackground,
      appBar: ExecutiveAppBar(
        title: 'رقابة درج الكاش والوردية',
        selectedBranchId: branchId,
        onBranchChanged: (bId) {
          Provider.of<DashboardProvider>(context, listen: false).setSelectedBranch(bId);
          Provider.of<CashDrawerProvider>(context, listen: false).fetchCurrentShift(branchId: bId);
        },
        onRefresh: () {
          Provider.of<CashDrawerProvider>(context, listen: false).fetchCurrentShift(branchId: branchId);
        },
      ),
      body: Consumer<CashDrawerProvider>(
        builder: (context, drawer, _) {
          if (drawer.isLoading && drawer.currentShift == null) {
            return const Center(
              child: CircularProgressIndicator(color: AppColors.goldAccent),
            );
          }

          if (drawer.error != null && drawer.currentShift == null) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.error_outline, size: 48, color: AppColors.stockDepletedDanger),
                  const SizedBox(height: 12),
                  Text(drawer.error!, style: GoogleFonts.almarai(color: AppColors.textSecondary)),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: () => drawer.fetchCurrentShift(branchId: branchId),
                    child: const Text('تحديث'),
                  ),
                ],
              ),
            );
          }

          final shift = drawer.currentShift;
          if (shift == null) return const SizedBox.shrink();

          return SingleChildScrollView(
            padding: const EdgeInsets.all(16.0),
            physics: const AlwaysScrollableScrollPhysics(),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Shift Status Header Card
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: AppColors.strokeBorder),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                color: AppColors.primaryDark,
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(Icons.badge, color: AppColors.goldAccent, size: 20),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    shift.cashierName,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: GoogleFonts.cairo(fontSize: 15, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                                  ),
                                  if (shift.openedAt != null)
                                    Text(
                                      'بدء الوردية: ${shift.openedAt}',
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: GoogleFonts.almarai(fontSize: 11, color: AppColors.textMuted),
                                    ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      StatusBadge.success(
                        label: shift.shiftStatus == 'open' ? 'وردية مفتوحة' : 'مغلقة',
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),

                // Expected Drawer Cash Hero
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    gradient: AppColors.primaryGradient,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: AppColors.goldAccent, width: 1.5),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'الكاش المتوقع في الدرج الآن (النعلي)',
                        style: GoogleFonts.cairo(fontSize: 13, fontWeight: FontWeight.bold, color: AppColors.textSecondary),
                      ),
                      const SizedBox(height: 8),
                      ArabicTabularText(
                        text: shift.formatted['expected_drawer_cash'] ?? '${shift.expectedDrawerCash} ج.م',
                        fontSize: 32,
                        fontWeight: FontWeight.w800,
                        textColor: AppColors.goldAccent,
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),

                // Cash Breakdown Itemized List
                Text(
                  'تفصيل حركة المقبوضات حسب وسيلة الدفع',
                  style: GoogleFonts.cairo(fontSize: 15, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                ),
                const SizedBox(height: 12),

                _buildDrawerRow('مبيعات كاش (نقدي)', shift.formatted['cash_sales'] ?? '', Icons.money, AppColors.safeCashSuccess),
                _buildDrawerRow('تحصيلات الآجل كاش', shift.formatted['credit_collections_cash'] ?? '', Icons.collections_bookmark_outlined, AppColors.safeCashSuccess),
                _buildDrawerRow('مبيعات البطاقات (POS)', shift.formatted['card_sales'] ?? '', Icons.credit_card, AppColors.bankCardInfo),
                _buildDrawerRow('تحويلات بنكية (InstaPay)', shift.formatted['bank_transfer_sales'] ?? '', Icons.account_balance, AppColors.bankCardInfo),
                _buildDrawerRow('مصروفات نثرية مسحوبة', shift.formatted['expenses_paid'] ?? '', Icons.remove_circle_outline, AppColors.stockDepletedDanger),

                const SizedBox(height: 20),
                // Audit Disclaimer Note
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: AppColors.elevatedSurface,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.strokeBorder),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.shield, color: AppColors.goldAccent, size: 20),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          shift.auditNotes,
                          style: GoogleFonts.almarai(fontSize: 11, color: AppColors.textMuted),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildDrawerRow(String title, String formattedValue, IconData icon, Color accentColor) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.strokeBorder),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Expanded(
            child: Row(
              children: [
                Icon(icon, color: accentColor, size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: GoogleFonts.cairo(fontSize: 13, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          ArabicTabularText(text: formattedValue, fontSize: 15, textColor: accentColor),
        ],
      ),
    );
  }
}
