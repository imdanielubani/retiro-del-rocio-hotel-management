import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';

/// Warning banner shown a few minutes before the session (JWT) expires, with a
/// countdown and a "Stay signed in" action.
class SessionExpiringBanner extends StatelessWidget {
  const SessionExpiringBanner({super.key, required this.timeLeft, required this.onStay});

  final Duration timeLeft;
  final VoidCallback onStay;

  String get _countdown {
    final s = timeLeft.isNegative ? 0 : timeLeft.inSeconds;
    final m = (s ~/ 60).toString();
    final ss = (s % 60).toString().padLeft(2, '0');
    return '$m:$ss';
  }

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: Container(
        margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
        decoration: BoxDecoration(
          color: const Color(0xFF2A2010),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.gold.withValues(alpha: 0.4), width: 0.8),
        ),
        child: Row(
          children: [
            const Icon(Icons.access_time_rounded, color: AppColors.gold, size: 20),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                'Your session expires in $_countdown',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
            const SizedBox(width: 12),
            Material(
              color: AppColors.gold,
              borderRadius: BorderRadius.circular(10),
              child: InkWell(
                onTap: onStay,
                borderRadius: BorderRadius.circular(10),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: Text(
                    'Stay signed in',
                    style: AppTypography.style(
                      color: AppColors.onGold,
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
