import 'dart:async';
import 'dart:ui';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_colors.dart';
import '../../../providers/auth_provider.dart';
import 'server_settings_sheet.dart';

class LoginForm extends StatefulWidget {
  const LoginForm({
    super.key,
    required this.onAuthenticated,
    this.allowServerSettings = kDebugMode,
  });

  final VoidCallback onAuthenticated;
  final bool allowServerSettings;

  @override
  State<LoginForm> createState() => _LoginFormState();
}

class _LoginFormState extends State<LoginForm> {
  final _formKey = GlobalKey<FormState>();
  final _loginController = TextEditingController();
  final _passwordController = TextEditingController();

  Timer? _cooldownTimer;
  bool _obscurePassword = true;

  @override
  void initState() {
    super.initState();

    final cooldown = context.read<AuthProvider>().loginCooldown;
    if (cooldown > Duration.zero) {
      _startCooldownTimer();
    }
  }

  @override
  void dispose() {
    _cooldownTimer?.cancel();
    _loginController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  void _startCooldownTimer() {
    if (_cooldownTimer != null) return;

    _cooldownTimer = Timer.periodic(
      const Duration(seconds: 1),
      (_) => _updateCooldown(),
    );
  }

  void _updateCooldown() {
    if (!mounted) {
      _cooldownTimer?.cancel();
      _cooldownTimer = null;
      return;
    }

    final cooldown = context.read<AuthProvider>().loginCooldown;
    if (cooldown <= Duration.zero) {
      _cooldownTimer?.cancel();
      _cooldownTimer = null;
    }

    setState(() {});
  }

  Future<void> _handleLogin() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    final authProvider = context.read<AuthProvider>();
    final success = await authProvider.login(
      _loginController.text.trim(),
      _passwordController.text,
    );

    if (success && mounted) {
      widget.onAuthenticated();
    }
  }

  Future<void> _showServerSettings() async {
    final authProvider = context.read<AuthProvider>();
    final newUrl = await ServerSettingsSheet.show(
      context,
      authProvider: authProvider,
    );

    if (!mounted || newUrl == null) return;

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

  @override
  Widget build(BuildContext context) {
    return Form(
      key: _formKey,
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
                Consumer<AuthProvider>(
                  builder: (context, auth, _) {
                    if (auth.errorMessage == null) {
                      return const SizedBox.shrink();
                    }

                    return Container(
                      margin: const EdgeInsets.only(bottom: 20),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.stockDepletedDanger.withValues(
                          alpha: 0.15,
                        ),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: AppColors.stockDepletedDanger,
                        ),
                      ),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.error_outline,
                            color: AppColors.stockDepletedDanger,
                            size: 20,
                          ),
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
                    prefixIcon: Icon(
                      Icons.person_outline,
                      color: AppColors.textMuted,
                    ),
                  ),
                  validator: (value) {
                    if (value == null || value.trim().isEmpty) {
                      return 'يرجى إدخال اسم المستخدم أو البريد الإلكتروني';
                    }

                    return null;
                  },
                ),
                const SizedBox(height: 20),
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
                    prefixIcon: const Icon(
                      Icons.lock_outline,
                      color: AppColors.textMuted,
                    ),
                    suffixIcon: IconButton(
                      icon: Icon(
                        _obscurePassword
                            ? Icons.visibility_off_outlined
                            : Icons.visibility_outlined,
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
                Consumer<AuthProvider>(
                  builder: (context, auth, _) {
                    final isAuthenticating =
                        auth.status == AuthStatus.authenticating;
                    final cooldown = auth.loginCooldown;
                    final isCoolingDown = cooldown > Duration.zero;

                    if (isCoolingDown) {
                      _startCooldownTimer();
                    } else {
                      _cooldownTimer?.cancel();
                      _cooldownTimer = null;
                    }

                    final seconds = isCoolingDown
                        ? (cooldown.inMilliseconds + 999) ~/ 1000
                        : 0;

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
                        onPressed: isAuthenticating || isCoolingDown
                            ? null
                            : _handleLogin,
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
                                isCoolingDown
                                    ? 'حاول بعد $seconds ث'
                                    : 'دخول لوحة المالك',
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
                if (widget.allowServerSettings) ...[
                  const SizedBox(height: 16),
                  Consumer<AuthProvider>(
                    builder: (context, auth, _) {
                      return Center(
                        child: TextButton.icon(
                          onPressed: _showServerSettings,
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
              ],
            ),
          ),
        ),
      ),
    );
  }
}
