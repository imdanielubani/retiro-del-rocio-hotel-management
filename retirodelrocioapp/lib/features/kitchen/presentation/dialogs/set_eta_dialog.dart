import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_order.dart';

/// "Add Time" / "Increase Time" — the Kitchen sets or raises how long a
/// ticket still needs. Returns the chosen number of minutes, or null if
/// dismissed.
Future<int?> showSetEtaDialog(BuildContext context, KitchenOrder order) {
  return showDialog<int>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.4),
    builder: (_) => _SetEtaDialog(order: order),
  );
}

class _SetEtaDialog extends StatefulWidget {
  const _SetEtaDialog({required this.order});

  final KitchenOrder order;

  @override
  State<_SetEtaDialog> createState() => _SetEtaDialogState();
}

class _SetEtaDialogState extends State<_SetEtaDialog> {
  static const _presets = [5, 10, 15, 20, 30, 45];
  int _minutes = 10;

  @override
  Widget build(BuildContext context) {
    final hasEta = widget.order.estimatedReadyLabel != null;

    return BackdropFilter(
      filter: ImageFilter.blur(sigmaX: 8, sigmaY: 8),
      child: Center(
        child: Material(
          color: Colors.transparent,
          child: Container(
            width: 420,
            padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 28),
            decoration: BoxDecoration(
              color: const Color(0xFF1A1712),
              borderRadius: BorderRadius.circular(24),
              border: Border.all(
                color: Colors.white.withValues(alpha: 0.12),
                width: 0.8,
              ),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  hasEta ? 'Increase Time' : 'Add Time',
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  hasEta
                      ? 'Currently due ${widget.order.estimatedReadyLabel}. How much longer does it need, from now?'
                      : 'How long until ${widget.order.itemsLabel} is ready?',
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.55),
                    fontSize: 13,
                    height: 1.4,
                  ),
                ),
                const SizedBox(height: 20),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final minutes in _presets)
                      _chip('$minutes min', minutes == _minutes, () {
                        setState(() => _minutes = minutes);
                      }),
                  ],
                ),
                const SizedBox(height: 24),
                Row(
                  children: [
                    Expanded(
                      child: SizedBox(
                        height: 48,
                        child: Material(
                          color: Colors.white.withValues(alpha: 0.08),
                          borderRadius: BorderRadius.circular(14),
                          child: InkWell(
                            onTap: () => Navigator.of(context).pop(),
                            borderRadius: BorderRadius.circular(14),
                            child: Center(
                              child: Text(
                                'Cancel',
                                style: AppTypography.style(
                                  color: Colors.white.withValues(alpha: 0.8),
                                  fontSize: 15,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: SizedBox(
                        height: 48,
                        child: Material(
                          color: AppColors.gold,
                          borderRadius: BorderRadius.circular(14),
                          child: InkWell(
                            onTap: () => Navigator.of(context).pop(_minutes),
                            borderRadius: BorderRadius.circular(14),
                            child: Center(
                              child: Text(
                                hasEta ? 'Increase Time' : 'Set Time',
                                style: AppTypography.style(
                                  color: const Color(0xFF0A0F1E),
                                  fontSize: 15,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _chip(String label, bool selected, VoidCallback onTap) {
    return Material(
      color: selected ? AppColors.gold : Colors.white.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          child: Text(
            label,
            style: AppTypography.style(
              color: selected ? const Color(0xFF0A0F1E) : Colors.white70,
              fontSize: 13,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }
}
