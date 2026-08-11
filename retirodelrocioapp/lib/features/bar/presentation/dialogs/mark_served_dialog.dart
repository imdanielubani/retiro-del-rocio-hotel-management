import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/features/bar/application/bar_providers.dart';
import 'package:retirodelrocioapp/features/bar/data/bar_repository.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_order.dart';
import 'package:retirodelrocioapp/features/bar/presentation/dialogs/age_verification_dialog.dart';
import 'package:retirodelrocioapp/features/bar/presentation/dialogs/bar_confirm_dialog.dart';

/// Marks an order served — chains through the Age Verification dialog first
/// if it contains an alcoholic item that hasn't been verified yet. Shows a
/// failure snackbar on error; returns `true` on success.
Future<bool> handleMarkServed(
  BuildContext context,
  WidgetRef ref,
  String token,
  BarOrder order,
) async {
  if (order.needsAgeCheck) {
    final verified = await showAgeVerificationDialog(
      context,
      itemsLabel: order.itemsLabel,
    );
    if (!verified) return false;
    if (!context.mounted) return false;

    try {
      await ref.read(barActionsProvider(token)).verifyAge(order.id);
    } on BarException catch (e) {
      if (context.mounted) _showFailure(context, e.message);
      return false;
    }
  }

  if (!context.mounted) return false;

  final confirmed = await showBarConfirmDialog(
    context,
    icon: Icons.check_circle_rounded,
    accent: const Color(0xFF16A34A),
    title: 'Mark Served?',
    message: '${order.itemsLabel} will be marked as served.',
    confirmLabel: 'Mark Served',
  );
  if (!confirmed || !context.mounted) return false;

  try {
    await ref.read(barActionsProvider(token)).markServed(order.id);
    return true;
  } on BarException catch (e) {
    if (context.mounted) _showFailure(context, e.message);
    return false;
  }
}

void _showFailure(BuildContext context, String message) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      backgroundColor: const Color(0xFF7F1D1D),
      behavior: SnackBarBehavior.floating,
      content: Text(message, style: const TextStyle(color: Colors.white)),
    ),
  );
}

/// A small reusable "Serve" action button carrying the badge accent when the
/// order still needs age verification.
class ServeButton extends StatelessWidget {
  const ServeButton({
    super.key,
    required this.needsAgeCheck,
    required this.onTap,
  });

  final bool needsAgeCheck;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: needsAgeCheck
          ? Colors.amber.withValues(alpha: 0.16)
          : AppColors.gold,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                needsAgeCheck ? Icons.badge_rounded : Icons.check_rounded,
                size: 16,
                color: needsAgeCheck ? Colors.amber : const Color(0xFF0A0F1E),
              ),
              const SizedBox(width: 6),
              Text(
                needsAgeCheck ? 'Verify & Serve' : 'Mark Served',
                style: TextStyle(
                  color: needsAgeCheck ? Colors.amber : const Color(0xFF0A0F1E),
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
