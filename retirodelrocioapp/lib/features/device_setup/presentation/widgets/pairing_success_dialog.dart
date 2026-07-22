import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/device_setup/presentation/widgets/allocation_chip.dart';

/// 0.8 — Tablet Paired Successfully pop-up (Figma node 72:2974).
///
/// Shows the assigned allocation, then auto-dismisses so the caller can move on
/// to the welcome screen.
Future<void> showPairingSuccessDialog(
  BuildContext context,
  ProvisionedDevice device,
) {
  return showDialog<void>(
    context: context,
    barrierDismissible: false,
    barrierColor: Colors.transparent,
    builder: (_) => _PairingSuccessDialog(device: device),
  );
}

class _PairingSuccessDialog extends StatefulWidget {
  const _PairingSuccessDialog({required this.device});

  final ProvisionedDevice device;

  @override
  State<_PairingSuccessDialog> createState() => _PairingSuccessDialogState();
}

class _PairingSuccessDialogState extends State<_PairingSuccessDialog> {
  @override
  void initState() {
    super.initState();
    Future.delayed(const Duration(milliseconds: 2600), () {
      if (mounted) Navigator.of(context).pop();
    });
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
              padding: const EdgeInsets.symmetric(horizontal: 60, vertical: 44),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(50),
                border: Border.all(color: Colors.white.withValues(alpha: 0.85)),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 95,
                    height: 90,
                    decoration: BoxDecoration(
                      color: AppColors.success.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: const Icon(Icons.check_circle_rounded,
                        color: AppColors.success, size: 55),
                  ),
                  const SizedBox(height: 13),
                  Text(
                    'Tablet Paired Successfully',
                    style: AppTypography.style(
                      color: Colors.white,
                      fontSize: 24,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'This device is now assigned to',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.7),
                      fontSize: 16,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 18),
                  AllocationChip(device: widget.device),
                  const SizedBox(height: 22),
                  Text(
                    'Preparing your experience....',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.5),
                      fontSize: 16,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
