import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/features/bar/presentation/dialogs/bar_confirm_dialog.dart';

/// The Age Verification prompt — staff confirm they've checked ID before an
/// alcoholic order can be marked served. Resolves `true` once confirmed.
Future<bool> showAgeVerificationDialog(
  BuildContext context, {
  required String itemsLabel,
}) {
  return showBarConfirmDialog(
    context,
    icon: Icons.badge_rounded,
    accent: AppColors.gold,
    title: 'Verify Age',
    message:
        'This order contains alcohol ($itemsLabel). Confirm you\'ve checked the guest\'s ID and they\'re of legal drinking age.',
    confirmLabel: 'Verified',
    cancelLabel: 'Cancel',
  );
}
