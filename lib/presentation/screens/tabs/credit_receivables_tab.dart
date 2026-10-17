import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../data/models/credit_overview.dart';
import '../../../providers/credit_provider.dart';
import '../../widgets/arabic_tabular_text.dart';
import '../../widgets/credit/debtor_tile.dart';
import '../../widgets/executive_app_bar.dart';
import '../../widgets/state/async_state_view.dart';

class CreditReceivablesTab extends StatefulWidget {
  const CreditReceivablesTab({super.key});

  @override
  State<CreditReceivablesTab> createState() => _CreditReceivablesTabState();
}

class _CreditReceivablesTabState extends State<CreditReceivablesTab> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<CreditProvider>(context, listen: false)
          .fetchCreditOverview();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.scaffoldBackground,
      appBar: ExecutiveAppBar(
        title: 'رقابة مستحقات الآجل والديون',
        onRefresh: () {
          Provider.of<CreditProvider>(context, listen: false)
              .fetchCreditOverview();
        },
      ),
      body: Consumer<CreditProvider>(
        builder: (context, credit, _) {
          return AsyncStateView<CreditOverviewData>(
            state: credit.state,
            onRetry: credit.fetchCreditOverview,
            builder: (context, data) {
              return _CreditOverviewContent(data: data);
            },
          );
        },
      ),
    );
  }
}

class _CreditOverviewContent extends StatelessWidget {
  const _CreditOverviewContent({required this.data});

  final CreditOverviewData data;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      physics: const AlwaysScrollableScrollPhysics(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(
                color: AppColors.stockDepletedDanger.withValues(alpha: 0.5),
                width: 1.5,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'إجمالي مستحقات الآجل القائمة بالسوق',
                      style: GoogleFonts.cairo(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: AppColors.textSecondary,
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.stockDepletedDanger.withValues(
                          alpha: 0.15,
                        ),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        '${data.debtorsCount} عميل مدين',
                        style: GoogleFonts.almarai(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: AppColors.stockDepletedDanger,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                ArabicTabularText(
                  text: data.totalOutstanding.exact,
                  fontSize: 30,
                  fontWeight: FontWeight.w800,
                  textColor: AppColors.stockDepletedDanger,
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: AppColors.safeCashSuccess.withValues(alpha: 0.3),
              ),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    const Icon(
                      Icons.collections_bookmark_outlined,
                      color: AppColors.safeCashSuccess,
                      size: 22,
                    ),
                    const SizedBox(width: 10),
                    Text(
                      'التحصيلات النقدية المستلمة اليوم',
                      style: GoogleFonts.cairo(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ],
                ),
                ArabicTabularText(
                  text: data.collectedToday.compact,
                  fontSize: 18,
                  textColor: AppColors.safeCashSuccess,
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          Text(
            'أعلى 5 كبار العملاء المدينين',
            style: GoogleFonts.cairo(
              fontSize: 15,
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 12),
          ...data.topDebtors.map((debtor) => DebtorTile(debtor: debtor)),
        ],
      ),
    );
  }
}
