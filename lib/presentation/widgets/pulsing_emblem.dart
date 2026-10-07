import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';

/// Central executive emblem with a breathing golden radial glow
/// displaying the official Al-Husseini dashboard brand logo medallion.
class PulsingEmblem extends StatefulWidget {
  final double size;
  final double iconSize;
  final IconData icon;
  final String? imageAsset;

  const PulsingEmblem({
    super.key,
    this.size = 120,
    this.iconSize = 52,
    this.icon = Icons.shield_moon_rounded,
    this.imageAsset = 'assets/images/alhusseini-icon.jpg',
  });

  @override
  State<PulsingEmblem> createState() => _PulsingEmblemState();
}

class _PulsingEmblemState extends State<PulsingEmblem> with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _glow;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2200),
    )..repeat(reverse: true);

    _glow = CurvedAnimation(parent: _controller, curve: Curves.easeInOut);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _glow,
      builder: (context, child) {
        final glow = _glow.value;
        return Container(
          width: widget.size,
          height: widget.size,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: const Color(0xFF0C141F),
            border: Border.all(
              color: AppColors.goldAccent.withValues(alpha: 0.6 + 0.3 * glow),
              width: 2.0,
            ),
            boxShadow: [
              BoxShadow(
                color: AppColors.goldAccent.withValues(alpha: 0.25 + 0.25 * glow),
                blurRadius: 22 + 18 * glow,
                spreadRadius: 2 + 5 * glow,
              ),
              BoxShadow(
                color: AppColors.goldAccent.withValues(alpha: 0.10 + 0.15 * glow),
                blurRadius: 52 + 28 * glow,
                spreadRadius: 6 + 8 * glow,
              ),
            ],
          ),
          child: ClipOval(
            child: widget.imageAsset != null
                ? Image.asset(
                    widget.imageAsset!,
                    width: widget.size,
                    height: widget.size,
                    fit: BoxFit.cover,
                    errorBuilder: (context, error, stackTrace) => Center(
                      child: Icon(
                        widget.icon,
                        size: widget.iconSize,
                        color: AppColors.goldAccent,
                      ),
                    ),
                  )
                : Center(
                    child: Icon(
                      widget.icon,
                      size: widget.iconSize,
                      color: AppColors.goldAccent,
                    ),
                  ),
          ),
        );
      },
    );
  }
}
