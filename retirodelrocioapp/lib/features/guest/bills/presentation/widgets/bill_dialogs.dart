import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';

/// The "Bill Confirmed!" success popup (Figma 140:2877). "Back to Home"
/// closes the dialog and returns all the way to the guest home screen — the
/// same `popUntil((route) => route.isFirst)` the idle timeout uses.
Future<void> showBillConfirmedDialog(BuildContext context) {
  return showDialog<void>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.9),
    barrierDismissible: false,
    builder: (_) => const _BillConfirmedDialog(),
  );
}

class _BillConfirmedDialog extends StatelessWidget {
  const _BillConfirmedDialog();

  void _backToHome(BuildContext context) {
    Navigator.of(context).pop();
    Navigator.of(context).popUntil((route) => route.isFirst);
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 480),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(24),
          child: BackdropFilter(
            filter: ImageFilter.blur(sigmaX: 18, sigmaY: 18),
            child: Container(
              padding: const EdgeInsets.all(38.2),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.10),
                borderRadius: BorderRadius.circular(24),
                border: Border.all(
                  color: Colors.white.withValues(alpha: 0.1),
                  width: 0.8,
                ),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 96,
                    height: 96,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: const Color(0xFF00FF00).withValues(alpha: 0.2),
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: const Color(0xFF00FF00).withValues(alpha: 0.5),
                        width: 1.6,
                      ),
                    ),
                    child: const Icon(
                      Icons.check_circle_rounded,
                      size: 48,
                      color: Color(0xFF00FF00),
                    ),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    'Bill Confirmed!',
                    textAlign: TextAlign.center,
                    style: AppTypography.style(
                      color: Colors.white,
                      fontSize: 28,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Your bills have been confirmed successfully',
                    textAlign: TextAlign.center,
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.5),
                      fontSize: 14,
                    ),
                  ),
                  const SizedBox(height: 24),
                  Material(
                    color: AppColors.gold,
                    borderRadius: BorderRadius.circular(16),
                    child: InkWell(
                      onTap: () => _backToHome(context),
                      borderRadius: BorderRadius.circular(16),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 24,
                          vertical: 12,
                        ),
                        child: Text(
                          'Back to Home',
                          style: AppTypography.style(
                            color: Colors.black.withValues(alpha: 0.9),
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
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
    );
  }
}
