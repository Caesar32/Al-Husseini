import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../data/models/cash_drawer_shift.dart';
import '../../../providers/cash_drawer_provider.dart';
import '../../../providers/dashboard_provider.dart';
import '../../widgets/arabic_tabular_text.dart';
import '../../widgets/drawer/drawer_breakdown.dart';
import '../../widgets/executive_app_bar.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/state/async_state_view.dart';
import '../../widgets/state/empty_state_card.dart';

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
      final branchId =
          Provider.of<DashboardProvider>(context, listen: false)
              .selectedBranchId;
      Provider.of<CashDrawerProvider>(context, listen: false)
          .fetchCurrentShift(branchId: branchId);
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
        onBranchChanged: (branchId) {
          Provider.of<DashboardProvider>(context, listen: false)
              .setSelectedBranch(branchId);
          Provider.of<CashDrawerProvider>(context, listen: false)
              .fetchCurrentShift(branchId: branchId);
        },
        onRefresh: () {
          Provider.of<CashDrawerProvider>(context, listen: false)
              .fetchCurrentShift(branchId: branchId);
        },
      ),
      body: Consumer<CashDrawerProvider>(
        builder: (context, drawer, _) {
          return AsyncStateView<CashDrawerShift>(
            state: drawer.state,
            onRetry: () {
              drawer.fetchCurrentShift(branchId: branchId);
            },
            builder: (context, shift) {
              return _CashDrawerContent(shift: shift);
            },
          );
        },
      ),
    );
  }
}

class _CashDrawerContent extends StatelessWidget {
  const _CashDrawerContent({required this.shift});

  final CashDrawerShift shift;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      physics: const AlwaysScrollableScrollPhysics(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
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
                        decoration: const BoxDecoration(
                          color: AppColors.primaryDark,
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.badge,
                          color: AppColors.goldAccent,
                          size: 20,
                        ),
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
                              style: GoogleFonts.cairo(
                                fontSize: 15,
                                fontWeight: FontWeight.bold,
                                color: AppColors.textPrimary,
                              ),
                            ),
                            if (shift.openedAt != null)
                              Text(
                                'بدء الوردية: ${shift.openedAt}',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: GoogleFonts.almarai(
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
                const SizedBox(width: 8),
                StatusBadge.success(
                  label: shift.shiftStatus == 'open' ? 'وردية مفتوحة' : 'مغلقة',
                ),
              ],
            ),
          ),
          if (!shift.hasActiveShift) ...[
            const SizedBox(height: 16),
            const EmptyStateCard(
              message: 'لا توجد وردية مفتوحة حالياً.',
              icon: Icons.info_outline,
            ),
          ],
          const SizedBox(height: 16),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: AppColors.primaryGradient,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(
                color: AppColors.goldAccent,
                width: 1.5,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'الكاش المتوقع في الدرج الآن (الفعلي)',
                  style: GoogleFonts.cairo(
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: AppColors.textSecondary,
                  ),
                ),
                const SizedBox(height: 8),
                ArabicTabularText(
                  text: shift.formatted['expected_drawer_cash'] ??
                      '${shift.expectedDrawerCash} ج.م',
                  fontSize: 32,
                  fontWeight: FontWeight.w800,
                  textColor: AppColors.goldAccent,
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          Text(
            'تفصيل حركة المقبوضات حسب وسيلة الدفع',
            style: GoogleFonts.cairo(
              fontSize: 15,
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 12),
          DrawerBreakdown(
            title: 'مبيعات كاش (نقدي)',
            formattedValue: shift.formatted['cash_sales'] ?? '',
            icon: Icons.money,
            accentColor: AppColors.safeCashSuccess,
          ),
          DrawerBreakdown(
            title: 'تحصيلات الآجل كاش',
            formattedValue:
                shift.formatted['credit_collections_cash'] ?? '',
            icon: Icons.collections_bookmark_outlined,
            accentColor: AppColors.safeCashSuccess,
          ),
          DrawerBreakdown(
            title: 'مبيعات البطاقات (POS)',
            formattedValue: shift.formatted['card_sales'] ?? '',
            icon: Icons.credit_card,
            accentColor: AppColors.bankCardInfo,
          ),
          DrawerBreakdown(
            title: 'تحويلات بنكية (InstaPay)',
            formattedValue: shift.formatted['bank_transfer_sales'] ?? '',
            icon: Icons.account_balance,
            accentColor: AppColors.bankCardInfo,
          ),
          DrawerBreakdown(
            title: 'مصروفات نثرية مسحوبة',
            formattedValue: shift.formatted['expenses_paid'] ?? '',
            icon: Icons.remove_circle_outline,
            accentColor: AppColors.stockDepletedDanger,
          ),
          if (shift.auditNotes.isNotEmpty) ...[
            const SizedBox(height: 20),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: AppColors.elevatedSurface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppColors.strokeBorder),
              ),
              child: Row(
                children: [
                  const Icon(
                    Icons.shield,
                    color: AppColors.goldAccent,
                    size: 20,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      shift.auditNotes,
                      style: GoogleFonts.almarai(
                        fontSize: 11,
                        color: AppColors.textMuted,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}
