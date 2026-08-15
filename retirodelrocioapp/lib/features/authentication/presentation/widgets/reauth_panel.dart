import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/data/auth_repository.dart';

enum _ReauthMethod { pin, password }

const _redColor = Color(0xFFEF4444);

/// PIN/password re-entry form that re-verifies the current staffer (refreshes
/// the JWT). Shared by the session lock screen and the "stay signed in"
/// dialog. Defaults to PIN — a quick 4-digit re-entry, via the same keypad
/// used for staff sign-in, is the point of unlocking a session someone
/// merely stepped away from — with a Password fallback for a staffer who
/// hasn't been given a PIN yet.
class ReauthPanel extends ConsumerStatefulWidget {
  const ReauthPanel({
    super.key,
    required this.onSuccess,
    this.actionLabel = 'Unlock',
  });

  final VoidCallback onSuccess;
  final String actionLabel;

  @override
  ConsumerState<ReauthPanel> createState() => _ReauthPanelState();
}

class _ReauthPanelState extends ConsumerState<ReauthPanel> {
  static const _pinLength = 4;

  final _password = TextEditingController();
  _ReauthMethod _method = _ReauthMethod.pin;
  String _pin = '';
  bool _obscure = true;
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _password.dispose();
    super.dispose();
  }

  void _switchMethod(_ReauthMethod method) {
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
      await ref.read(authControllerProvider.notifier).reauthenticate(pin: _pin);
      widget.onSuccess();
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
    if (_password.text.isEmpty) {
      setState(() => _error = 'Enter your password.');
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await ref
          .read(authControllerProvider.notifier)
          .reauthenticate(password: _password.text);
      widget.onSuccess();
    } on AuthException catch (e) {
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
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _MethodToggle(method: _method, onChanged: _switchMethod),
        const SizedBox(height: 16),
        if (_method == _ReauthMethod.pin) _pinEntry() else _passwordEntry(),
        if (_error != null) ...[
          const SizedBox(height: 10),
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
        if (_method == _ReauthMethod.password) ...[
          const SizedBox(height: 18),
          SizedBox(
            height: 52,
            child: Material(
              color: AppColors.gold,
              borderRadius: BorderRadius.circular(14),
              child: InkWell(
                onTap: _loading ? null : _submitPassword,
                borderRadius: BorderRadius.circular(14),
                child: Center(
                  child: _loading
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.5,
                            color: AppColors.onGold,
                          ),
                        )
                      : Text(
                          widget.actionLabel,
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
          const SizedBox(height: 16),
          const Center(
            child: SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(
                strokeWidth: 2.5,
                color: AppColors.gold,
              ),
            ),
          ),
        ],
      ],
    );
  }

  Widget _passwordEntry() {
    return Container(
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
          Icon(
            Icons.lock_outline_rounded,
            color: Colors.white.withValues(alpha: 0.5),
            size: 20,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: TextField(
              controller: _password,
              autofocus: true,
              obscureText: _obscure,
              onSubmitted: (_) => _submitPassword(),
              cursorColor: AppColors.gold,
              style: AppTypography.style(color: Colors.white, fontSize: 15),
              decoration: InputDecoration(
                border: InputBorder.none,
                isCollapsed: true,
                contentPadding: const EdgeInsets.symmetric(vertical: 17),
                hintText: 'Password',
                hintStyle: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.3),
                  fontSize: 15,
                ),
              ),
            ),
          ),
          IconButton(
            onPressed: () => setState(() => _obscure = !_obscure),
            icon: Icon(
              _obscure
                  ? Icons.visibility_off_rounded
                  : Icons.visibility_rounded,
              color: Colors.white.withValues(alpha: 0.5),
              size: 20,
            ),
          ),
        ],
      ),
    );
  }

  // --- PIN keypad — matches the staff sign-in / guest Room to Room dial pad -

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
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [_pinDisplay(), const SizedBox(height: 14), _pinKeypad()],
    );
  }

  Widget _pinDisplay() {
    return Container(
      height: 60,
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
      childAspectRatio: 108 / 56,
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

/// The PIN / Password tab switcher above the credential field.
class _MethodToggle extends StatelessWidget {
  const _MethodToggle({required this.method, required this.onChanged});

  final _ReauthMethod method;
  final ValueChanged<_ReauthMethod> onChanged;

  @override
  Widget build(BuildContext context) {
    Widget tab(_ReauthMethod value, String label) {
      final selected = method == value;
      return Expanded(
        child: Material(
          color: selected ? AppColors.gold : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
          child: InkWell(
            borderRadius: BorderRadius.circular(10),
            onTap: () => onChanged(value),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 9),
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
          tab(_ReauthMethod.pin, 'PIN'),
          tab(_ReauthMethod.password, 'Password'),
        ],
      ),
    );
  }
}
