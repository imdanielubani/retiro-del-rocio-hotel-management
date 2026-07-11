import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/data/password_reset_repository.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/screens/password_reset_success_screen.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/auth_primary_button.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/auth_scaffold.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/auth_text_field.dart';

/// Step 3 — set a new password with the verified [otp].
class NewPasswordScreen extends StatefulWidget {
  const NewPasswordScreen({
    super.key,
    required this.email,
    required this.otp,
    this.repository,
  });

  final String email;
  final String otp;
  final PasswordResetRepository? repository;

  @override
  State<NewPasswordScreen> createState() => _NewPasswordScreenState();
}

class _NewPasswordScreenState extends State<NewPasswordScreen> {
  late final PasswordResetRepository _repository =
      widget.repository ?? PasswordResetRepository();
  final _password = TextEditingController();
  final _confirm = TextEditingController();

  bool _obscure1 = true;
  bool _obscure2 = true;
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_loading) return;
    final password = _password.text;
    if (password.length < 8) {
      setState(() => _error = 'Password must be at least 8 characters.');
      return;
    }
    if (password != _confirm.text) {
      setState(() => _error = 'Passwords do not match.');
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await _repository.resetPassword(email: widget.email, otp: widget.otp, password: password);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const PasswordResetSuccessScreen()),
      );
    } on PasswordResetException catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = e.message;
        });
      }
    }
  }

  Widget _toggle(bool obscure, VoidCallback onTap) => IconButton(
        onPressed: onTap,
        icon: Icon(
          obscure ? Icons.visibility_off_rounded : Icons.visibility_rounded,
          color: Colors.white.withValues(alpha: 0.5),
          size: 20,
        ),
      );

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
              child: const Icon(Icons.password_rounded, color: AppColors.gold, size: 28),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'New Password',
            textAlign: TextAlign.center,
            style: AppTypography.style(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 6),
          Text(
            'Choose a new password for your account.',
            textAlign: TextAlign.center,
            style: AppTypography.style(color: Colors.white.withValues(alpha: 0.5), fontSize: 14),
          ),
          const SizedBox(height: 28),
          AuthTextField(
            controller: _password,
            label: 'New Password',
            hint: 'At least 8 characters',
            icon: Icons.lock_outline_rounded,
            obscure: _obscure1,
            trailing: _toggle(_obscure1, () => setState(() => _obscure1 = !_obscure1)),
          ),
          const SizedBox(height: 16),
          AuthTextField(
            controller: _confirm,
            label: 'Confirm Password',
            hint: 'Re-enter password',
            icon: Icons.lock_outline_rounded,
            obscure: _obscure2,
            onSubmitted: (_) => _submit(),
            trailing: _toggle(_obscure2, () => setState(() => _obscure2 = !_obscure2)),
          ),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(
              _error!,
              textAlign: TextAlign.center,
              style: AppTypography.style(color: const Color(0xFFF87171), fontSize: 13, fontWeight: FontWeight.w500),
            ),
          ],
          const SizedBox(height: 22),
          AuthPrimaryButton(label: 'Reset Password', loading: _loading, onTap: _submit),
        ],
      ),
    );
  }
}
