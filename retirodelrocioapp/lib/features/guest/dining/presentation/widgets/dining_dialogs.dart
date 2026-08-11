import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/dining/data/dining_repository.dart';
import 'package:retirodelrocioapp/features/guest/dining/domain/menu_item.dart';

/// The Payment Summary popup — unlike Cinema/Spa (which pick "Charge to
/// Room" vs "Paystack" on the booking screen itself, before opening this as
/// a pure confirmation step), Dining's cart has no natural place for that
/// choice, so tapping "Place Order" opens straight into this popup and the
/// guest picks Charge to Room / Paystack right here. Whichever is picked
/// runs the matching callback and returns the confirmed order, or `null` if
/// the guest backed out of a Paystack checkout (the popup then just resets,
/// still open, payment method still selected).
Future<DiningOrderConfirmation?> showDiningPaymentDialog(
  BuildContext context, {
  required String itemsLabel,
  required String subtotalLabel,
  required String vatLabel,
  required String serviceFeeLabel,
  required String totalLabel,
  required Future<DiningOrderConfirmation?> Function() onConfirmRoom,
  required Future<DiningOrderConfirmation?> Function() onConfirmPaystack,
}) {
  return showDialog<DiningOrderConfirmation>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.9),
    builder: (_) => _DiningPaymentDialog(
      itemsLabel: itemsLabel,
      subtotalLabel: subtotalLabel,
      vatLabel: vatLabel,
      serviceFeeLabel: serviceFeeLabel,
      totalLabel: totalLabel,
      onConfirmRoom: onConfirmRoom,
      onConfirmPaystack: onConfirmPaystack,
    ),
  );
}

/// The "Order Confirmed" success popup.
Future<void> showDiningOrderConfirmedDialog(
  BuildContext context, {
  required DiningOrderConfirmation confirmation,
}) {
  return showDialog<void>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.9),
    barrierDismissible: false,
    builder: (_) => _DiningConfirmedDialog(confirmation: confirmation),
  );
}

class _DiningPaymentDialog extends StatefulWidget {
  const _DiningPaymentDialog({
    required this.itemsLabel,
    required this.subtotalLabel,
    required this.vatLabel,
    required this.serviceFeeLabel,
    required this.totalLabel,
    required this.onConfirmRoom,
    required this.onConfirmPaystack,
  });

  final String itemsLabel;
  final String subtotalLabel;
  final String vatLabel;
  final String serviceFeeLabel;
  final String totalLabel;
  final Future<DiningOrderConfirmation?> Function() onConfirmRoom;
  final Future<DiningOrderConfirmation?> Function() onConfirmPaystack;

  @override
  State<_DiningPaymentDialog> createState() => _DiningPaymentDialogState();
}

class _DiningPaymentDialogState extends State<_DiningPaymentDialog> {
  DiningPaymentMethod? _method;
  bool _busy = false;
  String? _error;

  void _select(DiningPaymentMethod method) {
    if (_busy) return;
    setState(() => _method = method);
  }

  Future<void> _confirm() async {
    final method = _method;
    if (method == null) return;

    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final confirmation = method == DiningPaymentMethod.room
          ? await widget.onConfirmRoom()
          : await widget.onConfirmPaystack();
      if (!mounted) return;
      if (confirmation != null) {
        Navigator.of(context).pop(confirmation);
      } else {
        // The guest backed out of the Paystack checkout — leave the popup open.
        setState(() => _busy = false);
      }
    } on DiningException catch (e) {
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
                  _row(widget.itemsLabel, widget.subtotalLabel),
                  const SizedBox(height: 12),
                  _row('VAT (7.5%)', widget.vatLabel),
                  const SizedBox(height: 12),
                  _row('Room Service Fee', widget.serviceFeeLabel),
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
                  const SizedBox(height: 20),
                  Text(
                    'HOW WOULD YOU LIKE TO PAY?',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.4),
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1.2,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: _paymentOption(
                          method: DiningPaymentMethod.room,
                          icon: Icons.hotel_rounded,
                          title: 'Charge to Room',
                          subtitle: 'Add to your room bill',
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _paymentOption(
                          method: DiningPaymentMethod.paystack,
                          icon: Icons.credit_card_rounded,
                          title: 'Paystack',
                          subtitle: 'Pay online now',
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

  Widget _paymentOption({
    required DiningPaymentMethod method,
    required IconData icon,
    required String title,
    required String subtitle,
  }) {
    final selected = method == _method;
    final tint = selected ? AppColors.gold : Colors.white;

    return Material(
      color: selected
          ? AppColors.gold.withValues(alpha: 0.12)
          : Colors.white.withValues(alpha: 0.05),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: () => _select(method),
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.4)
                  : Colors.white.withValues(alpha: 0.08),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 16, color: tint),
              const SizedBox(height: 6),
              Text(
                title,
                style: AppTypography.style(
                  color: tint,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
              Text(
                subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 10,
                ),
              ),
            ],
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
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
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
    final enabled = _method != null && !_busy;
    final label = _method == DiningPaymentMethod.paystack
        ? 'PAY NOW'
        : 'PLACE ORDER';

    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        boxShadow: enabled
            ? [
                BoxShadow(
                  color: AppColors.gold.withValues(alpha: 0.4),
                  blurRadius: 12,
                  offset: const Offset(0, 8),
                ),
              ]
            : null,
      ),
      child: Material(
        color: enabled ? AppColors.gold : Colors.white.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          onTap: enabled ? _confirm : null,
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
                    _method == null ? 'SELECT A PAYMENT METHOD' : label,
                    style: AppTypography.style(
                      color: enabled
                          ? Colors.black
                          : Colors.white.withValues(alpha: 0.3),
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

class _DiningConfirmedDialog extends StatelessWidget {
  const _DiningConfirmedDialog({required this.confirmation});

  final DiningOrderConfirmation confirmation;

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
                    'Order Confirmed',
                    textAlign: TextAlign.center,
                    style: AppTypography.style(
                      color: Colors.white,
                      fontSize: 28,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    confirmation.itemsLabel,
                    textAlign: TextAlign.center,
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.5),
                      fontSize: 14,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '${confirmation.itemCount} item${confirmation.itemCount == 1 ? '' : 's'} • ${confirmation.totalLabel}',
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
