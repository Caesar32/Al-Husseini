import 'dart:math' as math;

import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';

/// Pure-CustomPainter dynamic bokeh/ember backdrop: gentle golden dust drifting
/// upward behind [child]. No external animation packages — a single looping
/// AnimationController drives closed-form per-particle motion so repaint cost
/// stays flat regardless of particle count.
class GoldenParticlesBackground extends StatefulWidget {
  final Widget child;
  final Gradient? gradient;
  final int particleCount;

  const GoldenParticlesBackground({
    super.key,
    required this.child,
    this.gradient,
    this.particleCount = 46,
  });

  @override
  State<GoldenParticlesBackground> createState() => _GoldenParticlesBackgroundState();
}

class _GoldenParticlesBackgroundState extends State<GoldenParticlesBackground>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final List<_Particle> _particles;

  @override
  void initState() {
    super.initState();
    final rnd = math.Random(7);
    _particles = List.generate(widget.particleCount, (_) {
      return _Particle(
        x: rnd.nextDouble(),
        y0: rnd.nextDouble(),
        size: 1.5 + rnd.nextDouble() * 2.5,
        speed: 0.35 + rnd.nextDouble() * 0.75,
        phase: rnd.nextDouble() * math.pi * 2,
        swayPhase: rnd.nextDouble() * math.pi * 2,
        baseOpacity: 0.2 + rnd.nextDouble() * 0.6,
      );
    });

    _controller = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 36),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      fit: StackFit.expand,
      children: [
        DecoratedBox(
          decoration: BoxDecoration(gradient: widget.gradient ?? AppColors.royalRadialGlow),
        ),
        Positioned.fill(
          child: RepaintBoundary(
            child: AnimatedBuilder(
              animation: _controller,
              builder: (context, _) {
                return CustomPaint(
                  painter: _GoldenParticlesPainter(_particles, _controller.value),
                );
              },
            ),
          ),
        ),
        widget.child,
      ],
    );
  }
}

class _Particle {
  final double x;
  final double y0;
  final double size;
  final double speed;
  final double phase;
  final double swayPhase;
  final double baseOpacity;

  const _Particle({
    required this.x,
    required this.y0,
    required this.size,
    required this.speed,
    required this.phase,
    required this.swayPhase,
    required this.baseOpacity,
  });
}

class _GoldenParticlesPainter extends CustomPainter {
  final List<_Particle> particles;
  final double t;

  _GoldenParticlesPainter(this.particles, this.t);

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..style = PaintingStyle.fill;

    for (final p in particles) {
      final travel = (t * p.speed) % 1.0;
      final y = ((p.y0 - travel) % 1.0 + 1.0) % 1.0;
      final sway = math.sin((t * 2 * math.pi) + p.swayPhase) * 8;
      final dx = (p.x * size.width + sway).clamp(0.0, size.width);
      final dy = y * size.height;

      final pulse = 0.5 + 0.5 * math.sin(t * 2 * math.pi * 1.6 + p.phase);
      final opacity = (p.baseOpacity * (0.55 + 0.45 * pulse)).clamp(0.0, 1.0);

      paint.color = AppColors.goldAccent.withValues(alpha: opacity);
      canvas.drawCircle(Offset(dx, dy), p.size, paint);
    }
  }

  @override
  bool shouldRepaint(covariant _GoldenParticlesPainter oldDelegate) => oldDelegate.t != t;
}
