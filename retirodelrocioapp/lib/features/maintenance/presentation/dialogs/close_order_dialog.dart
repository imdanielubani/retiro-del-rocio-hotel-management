import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_widgets.dart';

/// "Close Order" — confirms marking a work order complete before it happens,
/// since completion is a one-way step (there's no re-open action). Returns
/// true if the technician confirmed.
Future<bool> showCloseOrderDialog(BuildContext context, {required String orderTitle}) async {
  final confirmed = await showDialog<bool>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.6),
    builder: (_) => Dialog(
      backgroundColor: const Color(0xFF141414),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 380),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  const Icon(Icons.check_circle_outline_rounded, color: kMtGreen, size: 22),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Close this order?',
                      style: AppTypography.style(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                '"$orderTitle" will be marked complete. This can\'t be undone from here.',
                style: AppTypography.style(color: Colors.white.withValues(alpha: 0.6), fontSize: 14, height: 1.4),
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: TextButton(
                      onPressed: () => Navigator.of(context).pop(false),
                      child: Text('Cancel', style: AppTypography.style(color: Colors.white70, fontSize: 14)),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Material(
                      color: kMtGreen,
                      borderRadius: BorderRadius.circular(12),
                      child: InkWell(
                        onTap: () => Navigator.of(context).pop(true),
                        borderRadius: BorderRadius.circular(12),
                        child: Container(
                          height: 44,
                          alignment: Alignment.center,
                          child: Text(
                            'Close Order',
                            style: AppTypography.style(color: Colors.black, fontSize: 14, fontWeight: FontWeight.w700),
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
  return confirmed ?? false;
}
