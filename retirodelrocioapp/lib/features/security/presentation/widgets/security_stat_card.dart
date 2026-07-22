import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';

/// A headline counter on the security dashboard (Figma 204:3490): a small
/// tracked label, a large accent number, an optional pulse dot and a fading
/// accent underline.
class SecurityStatCard extends StatelessWidget {
  const SecurityStatCard({
    super.key,
    required this.label,
    required this.value,
    required this.accent,
    this.pulsing = false,
  });

  final String label;
  final int value;
  final Color accent;

  /// A live red pulse for ACTIVE INCIDENTS when there is one.
  final bool pulsing;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 139,
      padding: const EdgeInsets.all(20.8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.08),
          width: 0.8,
        ),
      ),
      child: Stack(
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      label,
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.35),
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 1.2,
                      ),
                    ),
                  ),
                  if (pulsing) const _PulseDot(),
                ],
              ),
              const SizedBox(height: 20),
              Text(
                '$value',
                style: AppTypography.style(
                  color: accent,
                  fontSize: 36,
                  fontWeight: FontWeight.w800,
                  height: 1,
                ),
              ),
            ],
          ),
          Positioned(
            left: -15,
            right: 0,
            bottom: 0,
            child: Container(
              height: 2,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    accent.withValues(alpha: pulsing ? 1 : 0.25),
                    Colors.transparent,
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// A soft pulsing red dot — the "live" tell on the incidents counter.
class _PulseDot extends StatefulWidget {
  const _PulseDot();

  @override
  State<_PulseDot> createState() => _PulseDotState();
}

class _PulseDotState extends State<_PulseDot>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1400),
  )..repeat();

  static const _red = Color(0xFFFF0000);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 14,
      height: 14,
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, _) {
          final t = _controller.value;
          return Stack(
            alignment: Alignment.center,
            children: [
              Container(
                width: 6 + 8 * t,
                height: 6 + 8 * t,
                decoration: BoxDecoration(
                  color: _red.withValues(alpha: (1 - t) * 0.4),
                  shape: BoxShape.circle,
                ),
              ),
              Container(
                width: 8,
                height: 8,
                decoration: const BoxDecoration(
                  color: _red,
                  shape: BoxShape.circle,
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
