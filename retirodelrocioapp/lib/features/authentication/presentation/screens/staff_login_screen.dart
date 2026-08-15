import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/data/auth_repository.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/screens/staff_dashboard_screen.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';

enum _LoginMethod { password, pin }

const _redColor = Color(0xFFEF4444);

/// Staff sign-in on a role-locked tablet. Same auth for every role — the tablet's
/// locked role decides which dashboard opens on success.
class StaffLoginScreen extends ConsumerStatefulWidget {
  const StaffLoginScreen({super.key, required this.device});

  static const routeName = 'staff-login';

  final ProvisionedDevice device;

  @override
  ConsumerState<StaffLoginScreen> createState() => _StaffLoginScreenState();
}

class _StaffLoginScreenState extends ConsumerState<StaffLoginScreen> {
  static const _pinLength = 4;

  final _email = TextEditingController();
  final _password = TextEditingController();

  _LoginMethod _method = _LoginMethod.password;
  String _pin = '';
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

  void _switchMethod(_LoginMethod method) {
    setState(() {
      _method = method;
      _pin = '';
      _error = null;
    });
  }

  void _tapDigit(String digit) {
    if (_loading || _pin.length >= _pinLength) return;
    setState(() {
      _pin += digit;
      _error = null;
    });
    if (_pin.length == _pinLength) _submitPin();
  }

  void _backspace() {
    if (_loading || _pin.isEmpty) return;
    setState(() {
      _pin = _pin.substring(0, _pin.length - 1);
      _error = null;
    });
  }

  Future<void> _submitPin() async {
    if (_loading) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final session = await ref
          .read(authControllerProvider.notifier)
          .login(
            deviceToken: widget.device.token,
            email: null,
            pin: _pin,
            activeRole: _role,
          );
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => StaffDashboardScreen(session: session),
        ),
      );
    } on AuthException catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _pin = '';
          _error = e.message;
        });
      }
    }
  }

  Future<void> _submitPassword() async {
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
      final session = await ref
          .read(authControllerProvider.notifier)
          .login(
            deviceToken: widget.device.token,
            email: email,
            password: password,
            activeRole: _role,
          );
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => StaffDashboardScreen(session: session),
        ),
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

  Future<void> _forgotCredentials() async {
    await showDialog<void>(
      context: context,
      builder: (_) => AlertDialog(
        backgroundColor: const Color(0xFF1E1E1E),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Text(
          'Forgot your password or PIN?',
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 17,
            fontWeight: FontWeight.w700,
          ),
        ),
        content: Text(
          'Please see your manager — they can reset your password or PIN for you from the admin dashboard.',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.7),
            fontSize: 14,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: Text(
              'Got it',
              style: AppTypography.style(
                color: AppColors.gold,
                fontSize: 14,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
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
                padding: const EdgeInsets.symmetric(
                  horizontal: 44,
                  vertical: 40,
                ),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.07),
                  borderRadius: BorderRadius.circular(28),
                  border: Border.all(
                    color: Colors.white.withValues(alpha: 0.15),
                    width: 0.8,
                  ),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Center(
                      child: Image.asset(
                        'assets/icons/Rociologosetup.png',
                        width: 84,
                        height: 84,
                        fit: BoxFit.contain,
                      ),
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
                    const SizedBox(height: 24),
                    _MethodToggle(method: _method, onChanged: _switchMethod),
                    const SizedBox(height: 20),
                    if (_method == _LoginMethod.password)
                      _passwordForm()
                    else
                      _pinEntry(),
                    const SizedBox(height: 10),
                    Align(
                      alignment: Alignment.centerRight,
                      child: TextButton(
                        onPressed: _forgotCredentials,
                        style: TextButton.styleFrom(
                          padding: EdgeInsets.zero,
                          minimumSize: const Size(0, 0),
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                        child: Text(
                          'Forgot password or PIN?',
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
                    if (_method == _LoginMethod.password) ...[
                      const SizedBox(height: 22),
                      SizedBox(
                        height: 54,
                        child: Material(
                          color: AppColors.gold,
                          borderRadius: BorderRadius.circular(16),
                          child: InkWell(
                            onTap: _loading ? null : _submitPassword,
                            borderRadius: BorderRadius.circular(16),
                            child: Center(
                              child: _loading
                                  ? const SizedBox(
                                      width: 22,
                                      height: 22,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2.5,
                                        color: AppColors.onGold,
                                      ),
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
                    ] else if (_loading) ...[
                      const SizedBox(height: 18),
                      const Center(
                        child: SizedBox(
                          width: 24,
                          height: 24,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.5,
                            color: AppColors.gold,
                          ),
                        ),
                      ),
                    ],
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

  Widget _passwordForm() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
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
          onSubmitted: (_) => _submitPassword(),
          trailing: IconButton(
            onPressed: () => setState(() => _obscure = !_obscure),
            icon: Icon(
              _obscure
                  ? Icons.visibility_off_rounded
                  : Icons.visibility_rounded,
              color: Colors.white.withValues(alpha: 0.5),
              size: 20,
            ),
          ),
        ),
      ],
    );
  }

  // --- PIN keypad — matches the guest tablet's Room to Room dial pad -------

  Color get _pinColor {
    if (_error != null) return _redColor;
    if (_pin.isEmpty) return Colors.white.withValues(alpha: 0.2);
    return AppColors.gold;
  }

  Color get _pinBorderColor {
    if (_error != null) return _redColor.withValues(alpha: 0.4);
    if (_pin.isEmpty) return Colors.white.withValues(alpha: 0.1);
    return AppColors.gold.withValues(alpha: 0.4);
  }

  Widget _pinEntry() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'ENTER YOUR PIN',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.35),
            fontSize: 10,
            fontWeight: FontWeight.w700,
            letterSpacing: 1.5,
          ),
        ),
        const SizedBox(height: 10),
        _pinDisplay(),
        const SizedBox(height: 15),
        _pinKeypad(),
      ],
    );
  }

  Widget _pinDisplay() {
    return Container(
      height: 64,
      width: double.infinity,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: 0.35),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _pinBorderColor, width: 1),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: List.generate(_pinLength, (i) {
          final filled = i < _pin.length;
          return Container(
            width: 16,
            height: 16,
            margin: const EdgeInsets.symmetric(horizontal: 10),
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: filled ? _pinColor : Colors.transparent,
              border: Border.all(color: _pinBorderColor, width: 2),
            ),
          );
        }),
      ),
    );
  }

  Widget _pinKeypad() {
    const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', '⌫'];

    return GridView.count(
      crossAxisCount: 3,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 8,
      crossAxisSpacing: 8,
      childAspectRatio: 108 / 65,
      children: keys.map(_pinKey).toList(),
    );
  }

  Widget _pinKey(String key) {
    if (key.isEmpty) return const SizedBox.shrink();

    final isBackspace = key == '⌫';
    final color = isBackspace ? _redColor : Colors.white;

    return Material(
      color: isBackspace
          ? _redColor.withValues(alpha: 0.1)
          : Colors.white.withValues(alpha: 0.05),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: isBackspace ? _backspace : () => _tapDigit(key),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: isBackspace
                  ? _redColor.withValues(alpha: 0.25)
                  : Colors.white.withValues(alpha: 0.08),
              width: 0.8,
            ),
          ),
          alignment: Alignment.center,
          child: isBackspace
              ? Icon(Icons.backspace_outlined, size: 18, color: color)
              : Text(
                  key,
                  style: AppTypography.style(
                    color: color,
                    fontSize: 22,
                    fontWeight: FontWeight.w700,
                  ).copyWith(fontFamily: 'monospace'),
                ),
        ),
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
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.15),
              width: 0.8,
            ),
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

/// The Password / PIN tab switcher above the credential field.
class _MethodToggle extends StatelessWidget {
  const _MethodToggle({required this.method, required this.onChanged});

  final _LoginMethod method;
  final ValueChanged<_LoginMethod> onChanged;

  @override
  Widget build(BuildContext context) {
    Widget tab(_LoginMethod value, String label) {
      final selected = method == value;
      return Expanded(
        child: Material(
          color: selected ? AppColors.gold : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
          child: InkWell(
            borderRadius: BorderRadius.circular(10),
            onTap: () => onChanged(value),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 10),
              child: Text(
                label,
                textAlign: TextAlign.center,
                style: AppTypography.style(
                  color: selected
                      ? const Color(0xFF0A0F1E)
                      : Colors.white.withValues(alpha: 0.6),
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.1),
          width: 0.8,
        ),
      ),
      child: Row(
        children: [
          tab(_LoginMethod.password, 'Password'),
          tab(_LoginMethod.pin, 'PIN'),
        ],
      ),
    );
  }
}
