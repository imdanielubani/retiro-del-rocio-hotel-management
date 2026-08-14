import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';

/// A Smart Room control tile (Figma 410:909 etc.) — either a plain tap
/// target that opens the control's own page ([trailingSwitch] null), or a
/// self-contained toggle ([trailingSwitch] non-null) that flips on tap
/// alone, no page navigation. [active] gold-highlights the whole card,
/// matching the design's Do Not Disturb "on" state.
class SmartRoomControlCard extends StatelessWidget {
  const SmartRoomControlCard({
    super.key,
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.active = false,
    this.trailingSwitch,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final bool active;

  /// Non-null shows a toggle pill in the top-right instead of nothing; its
  /// value is this card's own on/off state.
  final bool? trailingSwitch;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: active
          ? AppColors.gold.withValues(alpha: 0.08)
          : Colors.white.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(24),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(24),
        child: Container(
          width: 232,
          height: 147,
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(24),
            border: Border.all(
              color: active
                  ? AppColors.gold.withValues(alpha: 0.25)
                  : Colors.white.withValues(alpha: 0.12),
              width: 0.8,
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: AppColors.gold.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Icon(icon, size: 22, color: AppColors.gold),
                  ),
                  if (trailingSwitch != null)
                    _MiniSwitch(value: trailingSwitch!),
                ],
              ),
              const Spacer(),
              Text(
                title,
                style: AppTypography.style(
                  color: active ? AppColors.gold : Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.35),
                  fontSize: 12,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MiniSwitch extends StatelessWidget {
  const _MiniSwitch({required this.value});

  final bool value;

  @override
  Widget build(BuildContext context) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 180),
      width: 40,
      height: 24,
      padding: const EdgeInsets.all(4),
      alignment: value ? Alignment.centerRight : Alignment.centerLeft,
      decoration: BoxDecoration(
        color: value ? AppColors.gold : Colors.white.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Container(
        width: 16,
        height: 16,
        decoration: const BoxDecoration(
          color: Colors.white,
          shape: BoxShape.circle,
        ),
      ),
    );
  }
}
