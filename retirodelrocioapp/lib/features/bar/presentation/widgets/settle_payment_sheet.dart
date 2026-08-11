import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_tab.dart';

/// The "Settle Payment" bottom sheet — picks how a closed tab is paid.
/// Returns `cash` | `card` | `room_charge`, or null if dismissed.
Future<String?> showSettlePaymentSheet(
  BuildContext context, {
  required BarTab tab,
}) {
  return showModalBottomSheet<String>(
    context: context,
    backgroundColor: const Color(0xFF1E1E1E),
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (_) => _SettlePaymentSheet(tab: tab),
  );
}

class _SettlePaymentSheet extends StatelessWidget {
  const _SettlePaymentSheet({required this.tab});

  final BarTab tab;

  static const _methods = [
    ('cash', Icons.payments_rounded, 'Cash'),
    ('card', Icons.credit_card_rounded, 'Card'),
    ('room_charge', Icons.hotel_rounded, 'Charge to Room'),
  ];

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 4),
            child: Text(
              'Settle Payment',
              style: AppTypography.style(
                color: Colors.white,
                fontSize: 16,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
            child: Text(
              '${tab.tableLabel ?? tab.code} · ${tab.totalLabel}',
              style: AppTypography.style(
                color: AppColors.gold,
                fontSize: 14,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          for (final (value, icon, label) in _methods)
            ListTile(
              leading: Icon(icon, color: AppColors.gold),
              title: Text(
                label,
                style: AppTypography.style(color: Colors.white, fontSize: 15),
              ),
              onTap: () => Navigator.of(context).pop(value),
            ),
          const SizedBox(height: 8),
        ],
      ),
    );
  }
}
