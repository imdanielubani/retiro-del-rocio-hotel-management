import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';

/// The emergency red used across SOS (Figma 113:725).
const Color kSosRed = Color(0xFFFF0000);
const Color kSosGlow = Color(0xFFEF4444);

/// The 176px SOS button, with the expanding rings that make it unmistakable.
///
/// The rings are decorative and non-interactive; the whole button is one hit
/// target — a guest in trouble should not have to aim.
class SosButton extends StatefulWidget {
  const SosButton({super.key, required this.onPressed});

  final VoidCallback onPressed;

  static const double diameter = 176;

  @override
  State<SosButton> createState() => _SosButtonState();
}

class _SosButtonState extends State<SosButton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1800),
  )..repeat();

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 290,
      height: 290,
      child: Stack(
        alignment: Alignment.center,
        children: [
          // Three rings, staggered so one is always expanding outward.
          for (var i = 0; i < 3; i++)
            AnimatedBuilder(
              animation: _pulse,
              builder: (context, _) {
                final t = (_pulse.value + i / 3) % 1.0;
                return IgnorePointer(
                  child: Opacity(
                    opacity: (1 - t) * 0.35,
                    child: Container(
                      width: SosButton.diameter + (t * 114),
                      height: SosButton.diameter + (t * 114),
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: kSosGlow.withValues(alpha: 0.3),
                          width: 2.4,
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          _button(),
        ],
      ),
    );
  }

  Widget _button() {
    return Container(
      width: SosButton.diameter,
      height: SosButton.diameter,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        boxShadow: [
          BoxShadow(color: kSosGlow.withValues(alpha: 0.5), blurRadius: 30),
          BoxShadow(color: kSosGlow.withValues(alpha: 0.2), blurRadius: 60),
        ],
      ),
      child: Material(
        color: kSosRed,
        shape: CircleBorder(
          side: BorderSide(color: kSosGlow.withValues(alpha: 0.6), width: 2.4),
        ),
        child: InkWell(
          onTap: widget.onPressed,
          customBorder: const CircleBorder(),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.warning_amber_rounded, size: 44, color: Colors.white),
              const SizedBox(height: 6),
              Text(
                'SOS',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.4,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
