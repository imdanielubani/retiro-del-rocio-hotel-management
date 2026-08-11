import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/spa/data/spa_repository.dart';
import 'package:retirodelrocioapp/features/guest/spa/domain/spa_service.dart';

/// The Payment Summary popup (Figma 332:2444 / 331:382) — priced with VAT
/// (7.5%). [onConfirm] runs the real booking (either charged straight to the
/// room, or a Paystack charge + server verify) and returns the confirmed
/// booking on success, or `null` if the guest backed out of a Paystack
/// checkout. The dialog owns the busy/error UI.
Future<SpaBookingConfirmation?> showSpaPaymentDialog(
  BuildContext context, {
  required String serviceLabel,
  required int guests,
  required String subtotalLabel,
  required String vatLabel,
  required String totalLabel,
  required String buttonLabel,
  required Future<SpaBookingConfirmation?> Function() onConfirm,
}) {
  return showDialog<SpaBookingConfirmation>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.9),
    builder: (_) => _SpaPaymentDialog(
      serviceLabel: serviceLabel,
      guests: guests,
      subtotalLabel: subtotalLabel,
      vatLabel: vatLabel,
      totalLabel: totalLabel,
      buttonLabel: buttonLabel,
      onConfirm: onConfirm,
    ),
  );
}

/// The "Spa Booking Confirmed" success popup (Figma 332:3082).
Future<void> showSpaBookingConfirmedDialog(
  BuildContext context, {
  required SpaBookingConfirmation confirmation,
}) {
  return showDialog<void>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.9),
    barrierDismissible: false,
    builder: (_) => _SpaConfirmedDialog(confirmation: confirmation),
  );
}

class _SpaPaymentDialog extends StatefulWidget {
  const _SpaPaymentDialog({
    required this.serviceLabel,
    required this.guests,
    required this.subtotalLabel,
    required this.vatLabel,
    required this.totalLabel,
    required this.buttonLabel,
    required this.onConfirm,
  });

  final String serviceLabel;
  final int guests;
  final String subtotalLabel;
  final String vatLabel;
  final String totalLabel;
  final String buttonLabel;
  final Future<SpaBookingConfirmation?> Function() onConfirm;

  @override
  State<_SpaPaymentDialog> createState() => _SpaPaymentDialogState();
}

class _SpaPaymentDialogState extends State<_SpaPaymentDialog> {
  bool _busy = false;
  String? _error;

  Future<void> _confirm() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final confirmation = await widget.onConfirm();
      if (!mounted) return;
      if (confirmation != null) {
        Navigator.of(context).pop(confirmation);
      } else {
        // The guest backed out of the Paystack checkout — leave the popup open.
        setState(() => _busy = false);
      }
    } on SpaException catch (e) {
      if (mounted) {
        setState(() {
          _error = e.message;
          _busy = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = 'Something went wrong. Please try again.';
          _busy = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 443),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(24),
          child: BackdropFilter(
            filter: ImageFilter.blur(sigmaX: 18, sigmaY: 18),
            child: Container(
              padding: const EdgeInsets.all(25),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(24),
                border: Border.all(
                  color: AppColors.gold.withValues(alpha: 0.2),
                  width: 1,
                ),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'Payment Summary',
                    style: AppTypography.style(
                      color: Colors.white,
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 16),
                  _row(widget.serviceLabel, widget.subtotalLabel),
                  const SizedBox(height: 12),
                  _row('Guest', '${widget.guests}'),
                  const SizedBox(height: 12),
                  _row('VAT (7.5%)', widget.vatLabel),
                  const SizedBox(height: 16),
                  Container(
                    height: 1,
                    color: Colors.white.withValues(alpha: 0.1),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Text(
                        'Total',
                        style: AppTypography.style(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const Spacer(),
                      Text(
                        widget.totalLabel,
                        style: AppTypography.style(
                          color: AppColors.gold,
                          fontSize: 22,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 14),
                    Text(
                      _error!,
                      style: AppTypography.style(
                        color: const Color(0xFFEF4444),
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                  const SizedBox(height: 24),
                  _confirmButton(),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _row(String label, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Text(
            label,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.5),
              fontSize: 13,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Text(
          value,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.8),
            fontSize: 13,
          ),
        ),
      ],
    );
  }

  Widget _confirmButton() {
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: AppColors.gold.withValues(alpha: 0.4),
            blurRadius: 12,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Material(
        color: AppColors.gold,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          onTap: _busy ? null : _confirm,
          borderRadius: BorderRadius.circular(16),
          child: Container(
            height: 56,
            alignment: Alignment.center,
            child: _busy
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.5,
                      color: Colors.black,
                    ),
                  )
                : Text(
                    widget.buttonLabel,
                    style: AppTypography.style(
                      color: Colors.black,
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
          ),
        ),
      ),
    );
  }
}

class _SpaConfirmedDialog extends StatelessWidget {
  const _SpaConfirmedDialog({required this.confirmation});

  final SpaBookingConfirmation confirmation;

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 480),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(24),
          child: BackdropFilter(
            filter: ImageFilter.blur(sigmaX: 18, sigmaY: 18),
            child: Container(
              padding: const EdgeInsets.all(38.2),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.10),
                borderRadius: BorderRadius.circular(24),
                border: Border.all(
                  color: Colors.white.withValues(alpha: 0.1),
                  width: 0.8,
                ),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 96,
                    height: 96,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: const Color(0xFF00FF00).withValues(alpha: 0.2),
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: const Color(0xFF00FF00).withValues(alpha: 0.5),
                        width: 1.6,
                      ),
                    ),
                    child: const Icon(
                      Icons.check_circle_rounded,
                      size: 48,
                      color: Color(0xFF00FF00),
                    ),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    'Spa Booking Confirmed',
                    textAlign: TextAlign.center,
                    style: AppTypography.style(
                      color: Colors.white,
                      fontSize: 28,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    confirmation.serviceName,
                    textAlign: TextAlign.center,
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.5),
                      fontSize: 14,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '${confirmation.time} • Today',
                    textAlign: TextAlign.center,
                    style: AppTypography.style(
                      color: AppColors.gold,
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 24),
                  Material(
                    color: AppColors.gold,
                    borderRadius: BorderRadius.circular(16),
                    child: InkWell(
                      onTap: () => Navigator.of(context).pop(),
                      borderRadius: BorderRadius.circular(16),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 24,
                          vertical: 12,
                        ),
                        child: Text(
                          'Done',
                          style: AppTypography.style(
                            color: Colors.black.withValues(alpha: 0.9),
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
