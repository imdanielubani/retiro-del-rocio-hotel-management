import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/data/auth_repository.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/screens/forgot_password_screen.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/screens/staff_dashboard_screen.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';

/// Staff sign-in on a role-locked tablet. Same auth for every role — the tablet's
/// locked role decides which dashboard opens on success.
class StaffLoginScreen extends ConsumerStatefulWidget {
  const StaffLoginScreen({super.key, required this.device});

  /// Route name so the reset flow can return here after success.
  static const routeName = 'staff-login';

  final ProvisionedDevice device;

  @override
  ConsumerState<StaffLoginScreen> createState() => _StaffLoginScreenState();
}

class _StaffLoginScreenState extends ConsumerState<StaffLoginScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();

  bool _obscure = true;
  bool _loading = false;
  String? _error;

  String get _role => widget.device.role ?? 'staff';
  String get _roleLabel =>
      _role.isEmpty ? 'Staff' : _role[0].toUpperCase() + _role.substring(1);

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_loading) return;
    final email = _email.text.trim();
    final password = _password.text;
    if (email.isEmpty || password.isEmpty) {
      setState(() => _error = 'Enter your email and password.');
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final session = await ref.read(authControllerProvider.notifier).login(
            deviceToken: widget.device.token,
            email: email,
            password: password,
            activeRole: _role,
          );
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => StaffDashboardScreen(session: session)),
      );
    } on AuthException catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = e.message;
        });
      }
    }
  }

  void _forgotPassword() {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const ForgotPasswordScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset('assets/images/12375.jpg', fit: BoxFit.cover),
          const ColoredBox(color: AppColors.scrim),
          Center(
            child: SingleChildScrollView(
              child: Container(
                width: 460,
                padding: const EdgeInsets.symmetric(horizontal: 44, vertical: 40),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.07),
                  borderRadius: BorderRadius.circular(28),
                  border: Border.all(color: Colors.white.withValues(alpha: 0.15), width: 0.8),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Center(
                      child: Image.asset('assets/icons/Rociologosetup.png',
                          width: 84, height: 84, fit: BoxFit.contain),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      '$_roleLabel Sign In',
                      textAlign: TextAlign.center,
                      style: AppTypography.style(
                        color: Colors.white,
                        fontSize: 24,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Sign in to access the $_roleLabel dashboard',
                      textAlign: TextAlign.center,
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.5),
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 28),
                    _AuthField(
                      controller: _email,
                      label: 'Email',
                      hint: 'you@retirodelrocio.com',
                      icon: Icons.mail_outline_rounded,
                      keyboardType: TextInputType.emailAddress,
                    ),
                    const SizedBox(height: 16),
                    _AuthField(
                      controller: _password,
                      label: 'Password',
                      hint: '••••••••',
                      icon: Icons.lock_outline_rounded,
                      obscure: _obscure,
                      onSubmitted: (_) => _submit(),
                      trailing: IconButton(
                        onPressed: () => setState(() => _obscure = !_obscure),
                        icon: Icon(
                          _obscure ? Icons.visibility_off_rounded : Icons.visibility_rounded,
                          color: Colors.white.withValues(alpha: 0.5),
                          size: 20,
                        ),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Align(
                      alignment: Alignment.centerRight,
                      child: TextButton(
                        onPressed: _forgotPassword,
                        style: TextButton.styleFrom(
                          padding: EdgeInsets.zero,
                          minimumSize: const Size(0, 0),
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                        child: Text(
                          'Forgot password?',
                          style: AppTypography.style(
                            color: AppColors.gold,
                            fontSize: 13,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
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
                    SizedBox(
                      height: 54,
                      child: Material(
                        color: AppColors.gold,
                        borderRadius: BorderRadius.circular(16),
                        child: InkWell(
                          onTap: _loading ? null : _submit,
                          borderRadius: BorderRadius.circular(16),
                          child: Center(
                            child: _loading
                                ? const SizedBox(
                                    width: 22,
                                    height: 22,
                                    child: CircularProgressIndicator(
                                        strokeWidth: 2.5, color: AppColors.onGold),
                                  )
                                : Text(
                                    'Sign In',
                                    style: AppTypography.style(
                                      color: AppColors.onGold,
                                      fontSize: 16,
                                      fontWeight: FontWeight.w700,
                                    ),
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
          Positioned(
            top: 40,
            left: 24,
            child: Material(
              color: Colors.white.withValues(alpha: 0.12),
              shape: const CircleBorder(),
              child: InkWell(
                onTap: () => Navigator.of(context).maybePop(),
                customBorder: const CircleBorder(),
                child: const SizedBox(
                  width: 44,
                  height: 44,
                  child: Icon(Icons.arrow_back_rounded, color: Colors.white),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _AuthField extends StatelessWidget {
  const _AuthField({
    required this.controller,
    required this.label,
    required this.hint,
    required this.icon,
    this.obscure = false,
    this.trailing,
    this.keyboardType,
    this.onSubmitted,
  });

  final TextEditingController controller;
  final String label;
  final String hint;
  final IconData icon;
  final bool obscure;
  final Widget? trailing;
  final TextInputType? keyboardType;
  final ValueChanged<String>? onSubmitted;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.6),
            fontSize: 12,
            fontWeight: FontWeight.w500,
            letterSpacing: 0.5,
          ),
        ),
        const SizedBox(height: 8),
        Container(
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.06),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: Colors.white.withValues(alpha: 0.15), width: 0.8),
          ),
          child: Row(
            children: [
              const SizedBox(width: 14),
              Icon(icon, color: Colors.white.withValues(alpha: 0.5), size: 20),
              const SizedBox(width: 10),
              Expanded(
                child: TextField(
                  controller: controller,
                  obscureText: obscure,
                  keyboardType: keyboardType,
                  onSubmitted: onSubmitted,
                  cursorColor: AppColors.gold,
                  style: AppTypography.style(color: Colors.white, fontSize: 15),
                  decoration: InputDecoration(
                    border: InputBorder.none,
                    isCollapsed: true,
                    contentPadding: const EdgeInsets.symmetric(vertical: 17),
                    hintText: hint,
                    hintStyle: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.3),
                      fontSize: 15,
                    ),
                  ),
                ),
              ),
              if (trailing != null) trailing! else const SizedBox(width: 14),
            ],
          ),
        ),
      ],
    );
  }
}
