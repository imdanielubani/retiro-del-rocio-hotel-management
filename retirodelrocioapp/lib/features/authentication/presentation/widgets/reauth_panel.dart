import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/data/auth_repository.dart';

/// Password re-entry form that re-verifies the current staffer (refreshes the
/// JWT). Shared by the session lock screen and the re-authenticate dialog.
class ReauthPanel extends ConsumerStatefulWidget {
  const ReauthPanel({super.key, required this.onSuccess, this.actionLabel = 'Unlock'});

  final VoidCallback onSuccess;
  final String actionLabel;

  @override
  ConsumerState<ReauthPanel> createState() => _ReauthPanelState();
}

class _ReauthPanelState extends ConsumerState<ReauthPanel> {
  final _password = TextEditingController();
  bool _obscure = true;
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
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
      await ref.read(authControllerProvider.notifier).reauthenticate(_password.text);
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
        Container(
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.06),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: Colors.white.withValues(alpha: 0.15), width: 0.8),
          ),
          child: Row(
            children: [
              const SizedBox(width: 14),
              Icon(Icons.lock_outline_rounded, color: Colors.white.withValues(alpha: 0.5), size: 20),
              const SizedBox(width: 10),
              Expanded(
                child: TextField(
                  controller: _password,
                  autofocus: true,
                  obscureText: _obscure,
                  onSubmitted: (_) => _submit(),
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
                  _obscure ? Icons.visibility_off_rounded : Icons.visibility_rounded,
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
                        child: CircularProgressIndicator(strokeWidth: 2.5, color: AppColors.onGold),
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
