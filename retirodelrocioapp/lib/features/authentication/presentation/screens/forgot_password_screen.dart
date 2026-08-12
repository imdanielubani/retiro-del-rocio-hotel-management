import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/data/password_reset_repository.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/screens/otp_verification_screen.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/auth_primary_button.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/auth_scaffold.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/auth_text_field.dart';

/// Step 1 — enter email; the backend emails a 6-digit OTP.
class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key, this.repository});

  final PasswordResetRepository? repository;

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  late final PasswordResetRepository _repository =
      widget.repository ?? PasswordResetRepository();
  final _email = TextEditingController();

  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_loading) return;
    final email = _email.text.trim();
    if (email.isEmpty || !email.contains('@')) {
      setState(() => _error = 'Enter a valid email address.');
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await _repository.sendOtp(email);
      if (!mounted) return;
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => OtpVerificationScreen(email: email)),
      );
      setState(() => _loading = false);
    } on PasswordResetException catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = e.message;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return AuthScaffold(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: AppColors.gold.withValues(alpha: 0.15),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.lock_reset_rounded,
                color: AppColors.gold,
                size: 30,
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'Forgot Password',
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 24,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Enter your account email and we’ll send you a code to reset your password.',
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.5),
              fontSize: 14,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 28),
          AuthTextField(
            controller: _email,
            label: 'Email',
            hint: 'you@retirodelrocio.com',
            icon: Icons.mail_outline_rounded,
            keyboardType: TextInputType.emailAddress,
            onSubmitted: (_) => _submit(),
          ),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(
              _error!,
              textAlign: TextAlign.center,
              style: AppTypography.style(
                color: const Color(0xFFF87171),
                fontSize: 13,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
          const SizedBox(height: 22),
          AuthPrimaryButton(
            label: 'Send Code',
            loading: _loading,
            onTap: _submit,
          ),
        ],
      ),
    );
  }
}
