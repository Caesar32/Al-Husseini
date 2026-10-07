import 'package:flutter/material.dart';

/// Royal Brand & Executive Color Palette extracted from Al-Husseini Web Platform.
class AppColors {
  AppColors._();

  // Primary Brand (Royal Sapphire Blue)
  static const Color primary = Color(0xFF1A4480);
  static const Color primaryDark = Color(0xFF123363);
  static const Color primaryLight = Color(0xFF2563EB);

  static const LinearGradient primaryGradient = LinearGradient(
    colors: [Color(0xFF1F4F96), Color(0xFF163B70)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  // Royal Amber Gold (Accent & Emblems)
  static const Color gold = Color(0xFFC59B27);
  static const Color goldAccent = Color(0xFFD4AF37);
  static const Color softGoldTint = Color(0xFFFDF5D7);

  static const LinearGradient goldGradient = LinearGradient(
    colors: [Color(0xFFD4AF37), Color(0xFFB8860B)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  // Midnight Dark Theme (Default Executive Surveillance Mode)
  static const Color scaffoldBackground = Color(0xFF0C141F);
  static const Color surface = Color(0xFF18202B);
  static const Color elevatedSurface = Color(0xFF1F2A38);
  static const Color strokeBorder = Color(0xFF283546);

  static const Color textPrimary = Color(0xFFF1F5F9);
  static const Color textSecondary = Color(0xFF94A3B8);
  static const Color textMuted = Color(0xFF64748B);
  static const Color textSoft = Color(0xFFCBD5E1);

  // Operational Semantic Accents
  static const Color safeCashSuccess = Color(0xFF0AB39C);
  static const Color stockDepletedDanger = Color(0xFFF06548);
  static const Color bankCardInfo = Color(0xFF299CDB);
  static const Color warningLateness = Color(0xFFF7B84B);

  // Glassmorphism Overlay Colors
  static Color glassSurface = const Color(0xFF18202B).withValues(alpha: 0.85);
  static Color glassBorder = const Color(0xFFD4AF37).withValues(alpha: 0.25);

  // Royal Midnight Radial Backdrop (Splash / Login dynamic screens)
  static const RadialGradient royalRadialGlow = RadialGradient(
    center: Alignment(0, -0.45),
    radius: 1.3,
    colors: [Color(0xFF1A4480), Color(0xFF163B70), Color(0xFF0C141F)],
    stops: [0.0, 0.45, 1.0],
  );
}
