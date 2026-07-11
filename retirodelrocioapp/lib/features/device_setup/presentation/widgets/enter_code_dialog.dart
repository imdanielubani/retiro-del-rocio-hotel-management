import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/data/provisioning_repository.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';

/// 0.6 — Enter Setup Code pop-up (Figma node 67:2763).
///
/// Pops with the [ProvisionedDevice] on success, or `null` if dismissed.
class EnterCodeDialog extends StatefulWidget {
  const EnterCodeDialog({super.key, this.repository});

  final ProvisioningRepository? repository;

  @override
  State<EnterCodeDialog> createState() => _EnterCodeDialogState();
}

class _EnterCodeDialogState extends State<EnterCodeDialog> {
  late final ProvisioningRepository _repository =
      widget.repository ?? ProvisioningRepository();
  final TextEditingController _controller = TextEditingController();

  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _activate() async {
    final code = _controller.text.trim();
    if (code.isEmpty) {
      setState(() => _error = 'Enter the setup code from super admin.');
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final device = await _repository.provisionWithCode(code);
      if (mounted) Navigator.of(context).pop(device);
    } on ProvisioningException catch (e) {
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
    return Material(
      color: Colors.transparent,
      child: Stack(
        children: [
          Positioned.fill(
            child: BackdropFilter(
              filter: ImageFilter.blur(sigmaX: 16, sigmaY: 16),
              child: const ColoredBox(color: Color(0x80000000)),
            ),
          ),
          Center(
            child: Container(
              width: 662,
              padding: const EdgeInsets.fromLTRB(60, 42, 60, 42),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(50),
                border: Border.all(color: Colors.white.withValues(alpha: 0.85)),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Align(
                    alignment: Alignment.centerRight,
                    child: _CloseButton(
                      onTap: () => Navigator.of(context).maybePop(),
                    ),
                  ),
                  Container(
                    width: 65,
                    height: 65,
                    decoration: BoxDecoration(
                      color: AppColors.gold.withValues(alpha: 0.15),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.keyboard_alt_outlined,
                      color: AppColors.gold,
                      size: 30,
                    ),
                  ),
                  const SizedBox(height: 15),
                  Text(
                    'Enter Setup Code',
                    style: AppTypography.style(
                      color: Colors.white,
                      fontSize: 24,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 28),
                  _CodeField(
                    controller: _controller,
                    onSubmitted: (_) => _activate(),
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
                  const SizedBox(height: 20),
                  _ActivateButton(loading: _loading, onTap: _activate),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CodeField extends StatelessWidget {
  const _CodeField({required this.controller, required this.onSubmitted});

  final TextEditingController controller;
  final ValueChanged<String> onSubmitted;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 64,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.2),
          width: 0.8,
        ),
      ),
      child: TextField(
        controller: controller,
        autofocus: true,
        textAlign: TextAlign.center,
        textCapitalization: TextCapitalization.characters,
        onSubmitted: onSubmitted,
        cursorColor: AppColors.gold,
        style: AppTypography.style(
          color: Colors.white,
          fontSize: 20,
          fontWeight: FontWeight.w600,
          letterSpacing: 2,
        ),
        decoration: InputDecoration(
          border: InputBorder.none,
          isCollapsed: true,
          hintText: 'e.g. RCIO-2401',
          hintStyle: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.5),
            fontSize: 18,
            fontWeight: FontWeight.w600,
            letterSpacing: 2,
          ),
        ),
      ),
    );
  }
}

class _ActivateButton extends StatelessWidget {
  const _ActivateButton({required this.loading, required this.onTap});

  final bool loading;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: 56,
      child: Material(
        color: AppColors.gold,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          onTap: loading ? null : onTap,
          borderRadius: BorderRadius.circular(16),
          child: Center(
            child: loading
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.5,
                      color: AppColors.onGold,
                    ),
                  )
                : Text(
                    'Activate Tablet',
                    style: AppTypography.style(
                      color: AppColors.onGold,
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
          ),
        ),
      ),
    );
  }
}

class _CloseButton extends StatelessWidget {
  const _CloseButton({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white.withValues(alpha: 0.2),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: const SizedBox(
          width: 32,
          height: 32,
          child: Icon(Icons.close_rounded, color: Colors.white, size: 20),
        ),
      ),
    );
  }
}
