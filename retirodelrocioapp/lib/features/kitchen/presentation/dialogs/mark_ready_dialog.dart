import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/features/kitchen/application/kitchen_providers.dart';
import 'package:retirodelrocioapp/features/kitchen/data/kitchen_repository.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_order.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/dialogs/kitchen_confirm_dialog.dart';

/// Marks a ticket ready/served — the Mark Ready confirm dialog. Shows a
/// failure snackbar on error; returns `true` on success.
Future<bool> handleMarkReady(
  BuildContext context,
  WidgetRef ref,
  String token,
  KitchenOrder order,
) async {
  final confirmed = await showKitchenConfirmDialog(
    context,
    icon: Icons.check_circle_rounded,
    accent: const Color(0xFF16A34A),
    title: 'Mark Ready?',
    message: '${order.itemsLabel} will be marked ready to serve.',
    confirmLabel: 'Mark Ready',
  );
  if (!confirmed || !context.mounted) return false;

  try {
    await ref.read(kitchenActionsProvider(token)).markServed(order.id);
    return true;
  } on KitchenException catch (e) {
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

/// A small reusable "Mark Ready" action button for the ticket board/detail.
class MarkReadyButton extends StatelessWidget {
  const MarkReadyButton({super.key, required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: const Padding(
          padding: EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.check_rounded, size: 16, color: Color(0xFF0A0F1E)),
              SizedBox(width: 6),
              Text(
                'Mark Ready',
                style: TextStyle(
                  color: Color(0xFF0A0F1E),
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
