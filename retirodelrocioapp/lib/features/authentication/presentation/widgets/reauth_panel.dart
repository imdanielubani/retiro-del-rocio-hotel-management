import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/data/auth_repository.dart';

enum _ReauthMethod { pin, password }

/// PIN/password re-entry form that re-verifies the current staffer (refreshes
/// the JWT). Shared by the session lock screen and the "stay signed in"
/// dialog. Defaults to PIN — a quick 4-digit re-entry is the point of
/// unlocking a session someone merely stepped away from — with a Password
/// fallback for a staffer who hasn't been given a PIN yet.
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
  final _pin = TextEditingController();
  final _password = TextEditingController();
  _ReauthMethod _method = _ReauthMethod.pin;
  bool _obscure = true;
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _pin.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_loading) return;
    final usingPin = _method == _ReauthMethod.pin;
    final credential = usingPin ? _pin.text.trim() : _password.text;

    if (credential.isEmpty) {
      setState(
        () => _error = usingPin ? 'Enter your PIN.' : 'Enter your password.',
      );
      return;
    }
    if (usingPin && credential.length != 4) {
      setState(() => _error = 'Your PIN is 4 digits.');
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await ref
          .read(authControllerProvider.notifier)
          .reauthenticate(
            password: usingPin ? null : credential,
            pin: usingPin ? credential : null,
          );
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
    final usingPin = _method == _ReauthMethod.pin;

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _MethodToggle(
          method: _method,
          onChanged: (m) => setState(() {
            _method = m;
            _error = null;
          }),
        ),
        const SizedBox(height: 14),
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
              Icon(
                usingPin ? Icons.dialpad_rounded : Icons.lock_outline_rounded,
                color: Colors.white.withValues(alpha: 0.5),
                size: 20,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: TextField(
                  key: ValueKey(_method),
                  controller: usingPin ? _pin : _password,
                  autofocus: true,
                  obscureText: _obscure,
                  keyboardType: usingPin ? TextInputType.number : null,
                  maxLength: usingPin ? 4 : null,
                  inputFormatters: usingPin
                      ? [FilteringTextInputFormatter.digitsOnly]
                      : null,
                  onSubmitted: (_) => _submit(),
                  cursorColor: AppColors.gold,
                  style: AppTypography.style(color: Colors.white, fontSize: 15),
                  decoration: InputDecoration(
                    border: InputBorder.none,
                    isCollapsed: true,
                    counterText: '',
                    contentPadding: const EdgeInsets.symmetric(vertical: 17),
                    hintText: usingPin ? 'PIN' : 'Password',
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
        ),
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
        const SizedBox(height: 18),
        SizedBox(
          height: 52,
          child: Material(
            color: AppColors.gold,
            borderRadius: BorderRadius.circular(14),
            child: InkWell(
              onTap: _loading ? null : _submit,
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
      ],
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
