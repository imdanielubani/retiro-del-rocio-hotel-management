import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_tab.dart';
import 'package:retirodelrocioapp/features/bar/presentation/dialogs/bar_confirm_dialog.dart';

/// Confirm before closing a tab — shows the running total. Resolves `true`
/// to proceed into the Settle Payment bottom sheet.
Future<bool> showCloseTabConfirmDialog(BuildContext context, BarTab tab) {
  return showBarConfirmDialog(
    context,
    icon: Icons.receipt_long_rounded,
    accent: AppColors.gold,
    title: 'Close ${tab.tableLabel ?? tab.code}?',
    message:
        'Total: ${tab.totalLabel} across ${tab.orderCount} ${tab.orderCount == 1 ? 'order' : 'orders'}. You\'ll choose how the guest is paying next.',
    confirmLabel: 'Continue to Payment',
  );
}
