import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/screens/staff_login_screen.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/auth_primary_button.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/auth_scaffold.dart';

/// Step 4 — password reset succeeded.
class PasswordResetSuccessScreen extends StatelessWidget {
  const PasswordResetSuccessScreen({super.key});

  void _backToSignIn(BuildContext context) {
    Navigator.of(context).popUntil(
      (route) =>
          route.settings.name == StaffLoginScreen.routeName || route.isFirst,
    );
  }

  @override
  Widget build(BuildContext context) {
    return AuthScaffold(
      showBack: false,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Container(
              width: 88,
              height: 88,
              decoration: BoxDecoration(
                color: AppColors.success.withValues(alpha: 0.12),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.check_circle_rounded,
                color: AppColors.success,
                size: 52,
              ),
            ),
          ),
          const SizedBox(height: 22),
          Text(
            'Password Reset',
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 24,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Your password has been updated. You can now sign in with your new password.',
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.55),
              fontSize: 14,
              height: 1.5,
            ),
          ),
          const SizedBox(height: 28),
          AuthPrimaryButton(
            label: 'Back to Sign In',
            onTap: () => _backToSignIn(context),
          ),
        ],
      ),
    );
  }
}
