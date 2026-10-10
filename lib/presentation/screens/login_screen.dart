import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../core/constants/api_constants.dart';
import '../../core/theme/app_colors.dart';
import '../../providers/auth_provider.dart';
import '../widgets/golden_particles_background.dart';
import '../widgets/pulsing_emblem.dart';
import 'main_surveillance_layout.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> with SingleTickerProviderStateMixin {
  final _formKey = GlobalKey<FormState>();
  final _loginController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;

  late final AnimationController _entrance;
  late final Animation<double> _headerFade;
  late final Animation<Offset> _headerSlide;
  late final Animation<double> _pillFade;
  late final Animation<Offset> _pillSlide;
  late final Animation<double> _cardFade;
  late final Animation<Offset> _cardSlide;

  @override
  void initState() {
    super.initState();

    _entrance = AnimationController(
      duration: const Duration(milliseconds: 1100),
      vsync: this,
    );

    _headerFade = CurvedAnimation(parent: _entrance, curve: const Interval(0.0, 0.55, curve: Curves.easeOut));
    _headerSlide = Tween<Offset>(begin: const Offset(0, 0.15), end: Offset.zero).animate(
      CurvedAnimation(parent: _entrance, curve: const Interval(0.0, 0.55, curve: Curves.easeOutCubic)),
    );

    _pillFade = CurvedAnimation(parent: _entrance, curve: const Interval(0.25, 0.7, curve: Curves.easeOut));
    _pillSlide = Tween<Offset>(begin: const Offset(0, 0.15), end: Offset.zero).animate(
      CurvedAnimation(parent: _entrance, curve: const Interval(0.25, 0.7, curve: Curves.easeOutCubic)),
    );

    _cardFade = CurvedAnimation(parent: _entrance, curve: const Interval(0.4, 1.0, curve: Curves.easeOut));
    _cardSlide = Tween<Offset>(begin: const Offset(0, 0.22), end: Offset.zero).animate(
      CurvedAnimation(parent: _entrance, curve: const Interval(0.4, 1.0, curve: Curves.easeOutCubic)),
    );

    _entrance.forward();
  }

  @override
  void dispose() {
    _entrance.dispose();
    _loginController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    if (!_formKey.currentState!.validate()) return;

    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final success = await authProvider.login(
      _loginController.text.trim(),
      _passwordController.text,
    );

    if (success && mounted) {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const MainSurveillanceLayout()),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.scaffoldBackground,
      body: GoldenParticlesBackground(
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Emblem + Welcome Hierarchy
                    FadeTransition(
                      opacity: _headerFade,
                      child: SlideTransition(
                        position: _headerSlide,
                        child: Column(
                          children: [
                            const Center(child: PulsingEmblem(size: 104, iconSize: 46)),
                            const SizedBox(height: 22),
                            Text(
                              'أهلاً بك في',
                              style: GoogleFonts.almarai(fontSize: 15, color: AppColors.textSecondary),
                              textAlign: TextAlign.center,
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'مركز الحسيني',
                              style: GoogleFonts.cairo(
                                fontSize: 28,
                                fontWeight: FontWeight.w800,
                                color: AppColors.textPrimary,
                              ),
                              textAlign: TextAlign.center,
                            ),
                            const SizedBox(height: 6),
                            Text(
                              'منظومة الرقابة والتحكم التنفيذي للمالك',
                              style: GoogleFonts.almarai(fontSize: 13, color: AppColors.textSoft),
                              textAlign: TextAlign.center,
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 18),

                    // Luxury Status Pill
                    FadeTransition(
                      opacity: _pillFade,
                      child: SlideTransition(
                        position: _pillSlide,
                        child: Center(
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                            decoration: BoxDecoration(
                              color: AppColors.goldAccent.withValues(alpha: 0.08),
                              borderRadius: BorderRadius.circular(30),
                              border: Border.all(color: AppColors.goldAccent.withValues(alpha: 0.45)),
                              boxShadow: [
                                BoxShadow(
                                  color: AppColors.goldAccent.withValues(alpha: 0.15),
                                  blurRadius: 14,
                                  spreadRadius: 1,
                                ),
                              ],
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(Icons.verified_user_rounded, size: 14, color: AppColors.goldAccent),
                                const SizedBox(width: 6),
                                Text(
                                  'لوحة القيادة المباشرة 24/7',
                                  style: GoogleFonts.almarai(
                                    fontSize: 11,
                                    fontWeight: FontWeight.bold,
                                    color: AppColors.goldAccent,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 28),

                    // Glassmorphic Login Card
                    FadeTransition(
                      opacity: _cardFade,
                      child: SlideTransition(
                        position: _cardSlide,
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(24),
                          child: BackdropFilter(
                            filter: ImageFilter.blur(sigmaX: 16, sigmaY: 16),
                            child: Container(
                              padding: const EdgeInsets.all(22),
                              decoration: BoxDecoration(
                                color: AppColors.glassSurface,
                                borderRadius: BorderRadius.circular(24),
                                border: Border.all(color: AppColors.glassBorder, width: 1),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  // Error Message Banner
                                  Consumer<AuthProvider>(
                                    builder: (context, auth, _) {
                                      if (auth.errorMessage == null) return const SizedBox.shrink();
                                      return Container(
                                        margin: const EdgeInsets.only(bottom: 20),
                                        padding: const EdgeInsets.all(12),
                                        decoration: BoxDecoration(
                                          color: AppColors.stockDepletedDanger.withValues(alpha: 0.15),
                                          borderRadius: BorderRadius.circular(12),
                                          border: Border.all(color: AppColors.stockDepletedDanger),
                                        ),
                                        child: Row(
                                          children: [
                                            const Icon(Icons.error_outline, color: AppColors.stockDepletedDanger, size: 20),
                                            const SizedBox(width: 10),
                                            Expanded(
                                              child: Text(
                                                auth.errorMessage!,
                                                style: GoogleFonts.almarai(
                                                  color: AppColors.stockDepletedDanger,
                                                  fontSize: 12,
                                                  fontWeight: FontWeight.bold,
                                                ),
                                              ),
                                            ),
                                          ],
                                        ),
                                      );
                                    },
                                  ),

                                  // Username / Email / Phone Input
                                  Text(
                                    'البريد الإلكتروني أو رقم الهاتف',
                                    style: GoogleFonts.cairo(
                                      fontSize: 13,
                                      fontWeight: FontWeight.bold,
                                      color: AppColors.textSecondary,
                                    ),
                                  ),
                                  const SizedBox(height: 8),
                                  TextFormField(
                                    controller: _loginController,
                                    keyboardType: TextInputType.emailAddress,
                                    textDirection: TextDirection.ltr,
                                    textAlign: TextAlign.left,
                                    style: GoogleFonts.outfit(color: AppColors.textPrimary),
                                    decoration: const InputDecoration(
                                      hintText: 'admin@alhusseini.com',
                                      prefixIcon: Icon(Icons.person_outline, color: AppColors.textMuted),
                                    ),
                                    validator: (value) {
                                      if (value == null || value.trim().isEmpty) {
                                        return 'يرجى إدخال اسم المستخدم أو البريد الإلكتروني';
                                      }
                                      return null;
                                    },
                                  ),
                                  const SizedBox(height: 20),

                                  // Password Input
                                  Text(
                                    'كلمة المرور',
                                    style: GoogleFonts.cairo(
                                      fontSize: 13,
                                      fontWeight: FontWeight.bold,
                                      color: AppColors.textSecondary,
                                    ),
                                  ),
                                  const SizedBox(height: 8),
                                  TextFormField(
                                    controller: _passwordController,
                                    obscureText: _obscurePassword,
                                    style: GoogleFonts.outfit(color: AppColors.textPrimary),
                                    decoration: InputDecoration(
                                      hintText: '••••••••',
                                      prefixIcon: const Icon(Icons.lock_outline, color: AppColors.textMuted),
                                      suffixIcon: IconButton(
                                        icon: Icon(
                                          _obscurePassword ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                                          color: AppColors.textMuted,
                                        ),
                                        onPressed: () {
                                          setState(() {
                                            _obscurePassword = !_obscurePassword;
                                          });
                                        },
                                      ),
                                    ),
                                    validator: (value) {
                                      if (value == null || value.isEmpty) {
                                        return 'يرجى إدخال كلمة المرور';
                                      }
                                      return null;
                                    },
                                  ),
                                  const SizedBox(height: 28),

                                  // Login Button
                                  Consumer<AuthProvider>(
                                    builder: (context, auth, _) {
                                      final isAuthenticating = auth.status == AuthStatus.authenticating;

                                      return Container(
                                        height: 52,
                                        decoration: BoxDecoration(
                                          borderRadius: BorderRadius.circular(12),
                                          gradient: AppColors.primaryGradient,
                                          boxShadow: [
                                            BoxShadow(
                                              color: AppColors.primary.withValues(alpha: 0.4),
                                              blurRadius: 12,
                                              offset: const Offset(0, 4),
                                            ),
                                          ],
                                        ),
                                        child: ElevatedButton(
                                          style: ElevatedButton.styleFrom(
                                            backgroundColor: Colors.transparent,
                                            shadowColor: Colors.transparent,
                                            shape: RoundedRectangleBorder(
                                              borderRadius: BorderRadius.circular(12),
                                            ),
                                          ),
                                          onPressed: isAuthenticating ? null : _handleLogin,
                                          child: isAuthenticating
                                              ? const SizedBox(
                                                  width: 24,
                                                  height: 24,
                                                  child: CircularProgressIndicator(
                                                    strokeWidth: 2,
                                                    color: Colors.white,
                                                  ),
                                                )
                                              : Text(
                                                  'دخول لوحة المالك',
                                                  style: GoogleFonts.cairo(
                                                    fontSize: 16,
                                                    fontWeight: FontWeight.bold,
                                                    color: Colors.white,
                                                  ),
                                                ),
                                        ),
                                      );
                                    },
                                  ),
                                  const SizedBox(height: 16),

                                  // Server URL indicator & config trigger
                                  Consumer<AuthProvider>(
                                    builder: (context, auth, _) {
                                      return Center(
                                        child: TextButton.icon(
                                          onPressed: () => _showServerSettingsDialog(context),
                                          icon: const Icon(
                                            Icons.settings_ethernet_outlined,
                                            size: 16,
                                            color: AppColors.textMuted,
                                          ),
                                          label: Text(
                                            'السيرفر: ${auth.baseUrl}',
                                            style: GoogleFonts.outfit(
                                              fontSize: 12,
                                              color: AppColors.textMuted,
                                            ),
                                          ),
                                        ),
                                      );
                                    },
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  void _showServerSettingsDialog(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final urlController = TextEditingController(text: authProvider.baseUrl);

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppColors.elevatedSurface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: AppColors.strokeBorder),
        ),
        title: Row(
          children: [
            const Icon(Icons.dns_outlined, color: AppColors.goldAccent),
            const SizedBox(width: 8),
            Text(
              'إعدادات السيرفر (API)',
              style: GoogleFonts.cairo(
                fontSize: 16,
                fontWeight: FontWeight.bold,
                color: AppColors.textPrimary,
              ),
            ),
          ],
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'عنوان الاتصال بالـ Backend:',
                style: GoogleFonts.cairo(
                  fontSize: 12,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: urlController,
                textDirection: TextDirection.ltr,
                textAlign: TextAlign.left,
                style: GoogleFonts.outfit(color: AppColors.textPrimary, fontSize: 13),
                decoration: const InputDecoration(
                  hintText: 'http://127.0.0.1:8000/api',
                  prefixIcon: Icon(Icons.link, color: AppColors.textMuted, size: 20),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                'خيارات سريعة:',
                style: GoogleFonts.cairo(
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textMuted,
                ),
              ),
              const SizedBox(height: 6),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  ActionChip(
                    label: Text('محلي (Web / PC)', style: GoogleFonts.cairo(fontSize: 11, color: Colors.white)),
                    backgroundColor: AppColors.surface,
                    onPressed: () {
                      urlController.text = 'http://127.0.0.1:8000/api';
                    },
                  ),
                  ActionChip(
                    label: Text('محاكي أندرويد', style: GoogleFonts.cairo(fontSize: 11, color: Colors.white)),
                    backgroundColor: AppColors.surface,
                    onPressed: () {
                      urlController.text = 'http://10.0.2.2:8000/api';
                    },
                  ),
                  ActionChip(
                    label: Text('السيرفر الحي', style: GoogleFonts.cairo(fontSize: 11, color: AppColors.goldAccent)),
                    backgroundColor: AppColors.surface,
                    onPressed: () {
                      urlController.text = ApiConstants.liveProductionUrl;
                    },
                  ),
                ],
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: Text(
              'إلغاء',
              style: GoogleFonts.cairo(color: AppColors.textMuted),
            ),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primary,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
            onPressed: () async {
              final newUrl = urlController.text.trim();
              if (newUrl.isNotEmpty) {
                await authProvider.updateBaseUrl(newUrl);
                if (ctx.mounted) Navigator.pop(ctx);
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(
                        'تم تحديث عنوان السيرفر إلى: $newUrl',
                        style: GoogleFonts.cairo(),
                      ),
                      backgroundColor: AppColors.safeCashSuccess,
                    ),
                  );
                }
              }
            },
            child: Text(
              'حفظ وتطبيق',
              style: GoogleFonts.cairo(color: Colors.white, fontWeight: FontWeight.bold),
            ),
          ),
        ],
      ),
    );
  }
}
