import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../providers/credit_provider.dart';
import '../../widgets/arabic_tabular_text.dart';
import '../../widgets/executive_app_bar.dart';

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
      Provider.of<CreditProvider>(context, listen: false).fetchCreditOverview();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.scaffoldBackground,
      appBar: ExecutiveAppBar(
        title: 'رقابة مستحقات الآجل والديون',
        onRefresh: () {
          Provider.of<CreditProvider>(context, listen: false).fetchCreditOverview();
        },
      ),
      body: Consumer<CreditProvider>(
        builder: (context, credit, _) {
          if (credit.isLoading && credit.overviewData == null) {
            return const Center(child: CircularProgressIndicator(color: AppColors.goldAccent));
          }

          if (credit.error != null && credit.overviewData == null) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.error_outline, size: 48, color: AppColors.stockDepletedDanger),
                  const SizedBox(height: 12),
                  Text(credit.error!, style: GoogleFonts.almarai(color: AppColors.textSecondary)),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: () => credit.fetchCreditOverview(),
                    child: const Text('تحديث'),
                  ),
                ],
              ),
            );
          }

          final data = credit.overviewData;
          if (data == null) return const SizedBox.shrink();

          return SingleChildScrollView(
            padding: const EdgeInsets.all(16.0),
            physics: const AlwaysScrollableScrollPhysics(),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Total Debt Outstanding Hero Card
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: AppColors.stockDepletedDanger.withValues(alpha: 0.5), width: 1.5),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'إجمالي مستحقات الآجل القائمة بالسوق',
                            style: GoogleFonts.cairo(fontSize: 13, fontWeight: FontWeight.bold, color: AppColors.textSecondary),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(
                              color: AppColors.stockDepletedDanger.withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Text(
                              '${data.debtorsCount} عميل مدين',
                              style: GoogleFonts.almarai(fontSize: 11, fontWeight: FontWeight.bold, color: AppColors.stockDepletedDanger),
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

                // Collected Today
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: AppColors.safeCashSuccess.withValues(alpha: 0.3)),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.collections_bookmark_outlined, color: AppColors.safeCashSuccess, size: 22),
                          const SizedBox(width: 10),
                          Text('التحصيلات النقدية المستلمة اليوم', style: GoogleFonts.cairo(fontSize: 13, fontWeight: FontWeight.bold, color: AppColors.textPrimary)),
                        ],
                      ),
                      ArabicTabularText(text: data.collectedToday.compact, fontSize: 18, textColor: AppColors.safeCashSuccess),
                    ],
                  ),
                ),

                const SizedBox(height: 24),
                Text(
                  'أعلى 5 كبار العملاء المدينين',
                  style: GoogleFonts.cairo(fontSize: 15, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                ),
                const SizedBox(height: 12),

                ...data.topDebtors.map((debtor) {
                  return Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: AppColors.surface,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.strokeBorder),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Text(
                                debtor.name,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: GoogleFonts.cairo(fontSize: 14, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                              ),
                            ),
                            const SizedBox(width: 8),
                            ArabicTabularText(
                              text: debtor.formattedCreditBalance,
                              fontSize: 15,
                              textColor: AppColors.stockDepletedDanger,
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(debtor.phone, style: GoogleFonts.outfit(fontSize: 12, color: AppColors.textMuted)),
                            if (debtor.formattedCreditLimit != null)
                              Text('سقف الائتمان: ${debtor.formattedCreditLimit}', style: GoogleFonts.almarai(fontSize: 11, color: AppColors.textMuted)),
                          ],
                        ),
                      ],
                    ),
                  );
                }),
              ],
            ),
          );
        },
      ),
    );
  }
}
