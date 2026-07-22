import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/sos/presentation/widgets/sos_button.dart';

/// "Confirm Emergency?" (Figma 113:1837).
///
/// Deliberately a two-step action: a single accidental brush against a red
/// button in a hotel room must not scramble security. Returns `true` on SEND SOS.
Future<bool> showConfirmEmergencyDialog(
  BuildContext context, {
  required String? roomNumber,
}) async {
  final confirmed = await showDialog<bool>(
    context: context,
    barrierDismissible: true,
    barrierColor: Colors.black.withValues(alpha: 0.5),
    builder: (dialogContext) => BackdropFilter(
      filter: ImageFilter.blur(sigmaX: 16, sigmaY: 16),
      child: _ConfirmEmergencyDialog(roomNumber: roomNumber),
    ),
  );

  return confirmed ?? false;
}

class _ConfirmEmergencyDialog extends StatelessWidget {
  const _ConfirmEmergencyDialog({required this.roomNumber});

  final String? roomNumber;

  @override
  Widget build(BuildContext context) {
    final room = roomNumber != null ? 'Room $roomNumber' : 'your room';

    return Dialog(
      backgroundColor: Colors.transparent,
      elevation: 0,
      child: Container(
        width: 384,
        padding: const EdgeInsets.all(33.8),
        decoration: BoxDecoration(
          color: const Color(0xE6140505),
          borderRadius: BorderRadius.circular(24),
          border: Border.all(
            color: kSosGlow.withValues(alpha: 0.4),
            width: 0.8,
          ),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 80,
              height: 80,
              decoration: BoxDecoration(
                color: kSosRed.withValues(alpha: 0.2),
                shape: BoxShape.circle,
                border: Border.all(
                  color: kSosRed.withValues(alpha: 0.5),
                  width: 1.6,
                ),
              ),
              child: const Icon(Icons.warning_amber_rounded,
                  size: 40, color: Colors.white),
            ),
            const SizedBox(height: 24),
            Text(
              'Confirm Emergency?',
              textAlign: TextAlign.center,
              style: AppTypography.style(
                color: Colors.white,
                fontSize: 22,
                fontWeight: FontWeight.w700,
                height: 33 / 22,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'This will immediately alert hotel security and emergency services '
              'to $room. Please confirm this is a genuine emergency.',
              textAlign: TextAlign.center,
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.5),
                fontSize: 14,
                height: 22.4 / 14,
              ),
            ),
            const SizedBox(height: 24),
            Row(
              children: [
                Expanded(
                  child: _action(
                    label: 'Cancel',
                    onTap: () => Navigator.of(context).pop(false),
                    background: Colors.white.withValues(alpha: 0.1),
                    border: Colors.white.withValues(alpha: 0.2),
                    textColor: Colors.white.withValues(alpha: 0.7),
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: _action(
                    label: 'SEND SOS',
                    onTap: () => Navigator.of(context).pop(true),
                    background: kSosRed,
                    textColor: Colors.white,
                    fontWeight: FontWeight.w700,
                    glow: true,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _action({
    required String label,
    required VoidCallback onTap,
    required Color background,
    required Color textColor,
    required FontWeight fontWeight,
    Color? border,
    bool glow = false,
  }) {
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        boxShadow: glow
            ? [
                BoxShadow(
                  color: kSosGlow.withValues(alpha: 0.5),
                  blurRadius: 12,
                  offset: const Offset(0, 8),
                ),
              ]
            : null,
      ),
      child: Material(
        color: background,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(16),
          child: Container(
            height: 49.6,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: border != null
                  ? Border.all(color: border, width: 0.8)
                  : null,
            ),
            child: Text(
              label,
              style: AppTypography.style(
                color: textColor,
                fontSize: 16,
                fontWeight: fontWeight,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
