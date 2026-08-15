import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/reauth_panel.dart';

/// "Stay signed in" — re-verify the PIN (or password) to extend the session (refresh JWT).
Future<void> showReAuthenticateDialog(BuildContext context) {
  return showDialog<void>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.4),
    builder: (_) => const _ReAuthenticateDialog(),
  );
}

class _ReAuthenticateDialog extends StatelessWidget {
  const _ReAuthenticateDialog();

  @override
  Widget build(BuildContext context) {
    return BackdropFilter(
      filter: ImageFilter.blur(sigmaX: 8, sigmaY: 8),
      child: Center(
        child: Material(
          color: Colors.transparent,
          child: Container(
            width: 420,
            padding: const EdgeInsets.symmetric(horizontal: 36, vertical: 32),
            decoration: BoxDecoration(
              color: const Color(0xFF1A1712),
              borderRadius: BorderRadius.circular(24),
              border: Border.all(
                color: Colors.white.withValues(alpha: 0.12),
                width: 0.8,
              ),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'Stay Signed In',
                  textAlign: TextAlign.center,
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'Confirm your PIN to extend your session',
                  textAlign: TextAlign.center,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.55),
                    fontSize: 14,
                  ),
                ),
                const SizedBox(height: 22),
                ReauthPanel(
                  actionLabel: 'Continue',
                  onSuccess: () => Navigator.of(context).pop(),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
