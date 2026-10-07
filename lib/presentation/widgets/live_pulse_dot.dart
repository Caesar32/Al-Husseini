import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/theme/app_colors.dart';

class LivePulseDot extends StatefulWidget {
  final String label;
  final Color dotColor;
  final bool showLabel;

  const LivePulseDot({
    super.key,
    this.label = 'نبض مباشر',
    this.dotColor = AppColors.safeCashSuccess,
    this.showLabel = true,
  });

  @override
  State<LivePulseDot> createState() => _LivePulseDotState();
}

class _LivePulseDotState extends State<LivePulseDot>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _animation;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      duration: const Duration(milliseconds: 1400),
      vsync: this,
    )..repeat(reverse: true);

    _animation = Tween<double>(begin: 0.4, end: 1.0).animate(
      CurvedAnimation(parent: _controller, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _animation,
      builder: (context, child) {
        return Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(
            color: widget.dotColor.withValues(alpha: 0.12 * _animation.value),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: widget.dotColor.withValues(alpha: 0.3 * _animation.value),
              width: 1,
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 8,
                height: 8,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: widget.dotColor,
                  boxShadow: [
                    BoxShadow(
                      color: widget.dotColor.withValues(alpha: 0.8 * _animation.value),
                      blurRadius: 6 * _animation.value,
                      spreadRadius: 2 * _animation.value,
                    ),
                  ],
                ),
              ),
              if (widget.showLabel) ...[
                const SizedBox(width: 6),
                Text(
                  widget.label,
                  style: GoogleFonts.cairo(
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    color: widget.dotColor,
                  ),
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}
