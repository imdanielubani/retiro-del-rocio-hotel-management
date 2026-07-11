import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/data/password_reset_repository.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/screens/new_password_screen.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/auth_primary_button.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/auth_scaffold.dart';

/// Step 2 — enter the 6-digit code emailed to [email].
class OtpVerificationScreen extends StatefulWidget {
  const OtpVerificationScreen({super.key, required this.email, this.repository});

  final String email;
  final PasswordResetRepository? repository;

  @override
  State<OtpVerificationScreen> createState() => _OtpVerificationScreenState();
}

class _OtpVerificationScreenState extends State<OtpVerificationScreen> {
  static const _length = 6;

  late final PasswordResetRepository _repository =
      widget.repository ?? PasswordResetRepository();
  final _otp = TextEditingController();
  final _focus = FocusNode();

  bool _loading = false;
  bool _resending = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _focus.requestFocus());
  }

  @override
  void dispose() {
    _otp.dispose();
    _focus.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_loading) return;
    final code = _otp.text.trim();
    if (code.length < _length) {
      setState(() => _error = 'Enter the 6-digit code.');
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await _repository.verifyOtp(widget.email, code);
      if (!mounted) return;
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => NewPasswordScreen(email: widget.email, otp: code),
        ),
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

  Future<void> _resend() async {
    if (_resending) return;
    setState(() {
      _resending = true;
      _error = null;
    });
    try {
      await _repository.sendOtp(widget.email);
      if (mounted) {
        _otp.clear();
        setState(() {});
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('A new code has been sent.')),
        );
      }
    } on PasswordResetException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _resending = false);
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
              child: const Icon(Icons.mark_email_read_outlined, color: AppColors.gold, size: 30),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'Enter Code',
            textAlign: TextAlign.center,
            style: AppTypography.style(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 6),
          Text.rich(
            TextSpan(
              style: AppTypography.style(color: Colors.white.withValues(alpha: 0.5), fontSize: 14, height: 1.4),
              children: [
                const TextSpan(text: 'We sent a 6-digit code to\n'),
                TextSpan(
                  text: widget.email,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.8),
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 26),
          _OtpBoxes(
            length: _length,
            controller: _otp,
            focusNode: _focus,
            onChanged: (v) {
              setState(() => _error = null);
              if (v.length == _length) _submit();
            },
          ),
          if (_error != null) ...[
            const SizedBox(height: 14),
            Text(
              _error!,
              textAlign: TextAlign.center,
              style: AppTypography.style(color: const Color(0xFFF87171), fontSize: 13, fontWeight: FontWeight.w500),
            ),
          ],
          const SizedBox(height: 22),
          AuthPrimaryButton(label: 'Verify', loading: _loading, onTap: _submit),
          const SizedBox(height: 12),
          Center(
            child: TextButton(
              onPressed: _resending ? null : _resend,
              child: Text(
                _resending ? 'Sending…' : 'Didn’t get it? Resend code',
                style: AppTypography.style(color: AppColors.gold, fontSize: 13, fontWeight: FontWeight.w500),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Six boxes backed by a single hidden field.
class _OtpBoxes extends StatelessWidget {
  const _OtpBoxes({
    required this.length,
    required this.controller,
    required this.focusNode,
    required this.onChanged,
  });

  final int length;
  final TextEditingController controller;
  final FocusNode focusNode;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final text = controller.text;
    return GestureDetector(
      onTap: focusNode.requestFocus,
      child: Stack(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: List.generate(length, (i) {
              final filled = i < text.length;
              final active = i == text.length;
              return Container(
                width: 48,
                height: 58,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.06),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: active
                        ? AppColors.gold
                        : Colors.white.withValues(alpha: filled ? 0.35 : 0.15),
                    width: active ? 1.4 : 0.8,
                  ),
                ),
                child: Text(
                  filled ? text[i] : '',
                  style: AppTypography.style(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w700),
                ),
              );
            }),
          ),
          Positioned.fill(
            child: Opacity(
              opacity: 0,
              child: TextField(
                controller: controller,
                focusNode: focusNode,
                keyboardType: TextInputType.number,
                inputFormatters: [
                  FilteringTextInputFormatter.digitsOnly,
                  LengthLimitingTextInputFormatter(length),
                ],
                showCursor: false,
                onChanged: onChanged,
                decoration: const InputDecoration(border: InputBorder.none, counterText: ''),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
