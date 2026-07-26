import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/data/my_stay_repository.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/domain/guest_stay.dart';

const Color _successGreen = Color(0xFF00FF00);

/// The Payment Summary popup (Figma 333:4655) for a stay extension — priced with
/// VAT (7.5%). [onPay] runs the real charge (Paystack) and the server-side
/// verify, returning the applied [StayExtension] on success or `null` if the
/// guest backed out. The dialog owns the busy/error UI.
Future<StayExtension?> showExtendPaymentDialog(
  BuildContext context, {
  required ExtensionQuote quote,
  required int partySize,
  required Future<StayExtension?> Function() onPay,
}) {
  return showDialog<StayExtension>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.9),
    builder: (_) =>
        _PaymentDialog(quote: quote, partySize: partySize, onPay: onPay),
  );
}

/// The "Stay Extended!" confirmation (Figma 137:1437).
Future<void> showStayExtendedDialog(
  BuildContext context, {
  required StayExtension extension,
}) {
  return showDialog<void>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.9),
    barrierDismissible: false,
    builder: (_) => _SuccessDialog(extension: extension),
  );
}

class _PaymentDialog extends StatefulWidget {
  const _PaymentDialog({
    required this.quote,
    required this.partySize,
    required this.onPay,
  });

  final ExtensionQuote quote;
  final int partySize;
  final Future<StayExtension?> Function() onPay;

  @override
  State<_PaymentDialog> createState() => _PaymentDialogState();
}

class _PaymentDialogState extends State<_PaymentDialog> {
  bool _busy = false;
  String? _error;

  Future<void> _pay() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final extension = await widget.onPay();
      if (!mounted) return;
      if (extension != null) {
        Navigator.of(context).pop(extension);
      } else {
        // The guest backed out of the Paystack checkout — leave the popup open.
        setState(() => _busy = false);
      }
    } on MyStayException catch (e) {
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
    final n = widget.quote.additionalNights;
    final itemLabel = 'Stay Extension ($n ${n == 1 ? 'night' : 'nights'})';

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 443),
        // Frosted glass: blur what's behind so the card reads as a light panel
        // (Figma 333:4655), the same effect the design gets from its blurred scrim.
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
                  _row(itemLabel, widget.quote.subtotalLabel),
                  const SizedBox(height: 12),
                  _row('Guest', '${widget.partySize}'),
                  const SizedBox(height: 12),
                  _row('VAT (7.5%)', widget.quote.vatLabel),
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
                        widget.quote.totalLabel,
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
                  _payButton(),
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

  Widget _payButton() {
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
          onTap: _busy ? null : _pay,
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
                    'PAY NOW',
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

class _SuccessDialog extends StatelessWidget {
  const _SuccessDialog({required this.extension});

  final StayExtension extension;

  @override
  Widget build(BuildContext context) {
    // Same frosted-glass format as the Payment Summary popup (Figma 137:1784).
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
              padding: const EdgeInsets.all(48.8),
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
                      color: _successGreen.withValues(alpha: 0.2),
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: _successGreen.withValues(alpha: 0.5),
                        width: 1.6,
                      ),
                    ),
                    child: const Icon(
                      Icons.check_circle_rounded,
                      size: 48,
                      color: _successGreen,
                    ),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    'Stay Extended!',
                    textAlign: TextAlign.center,
                    style: AppTypography.style(
                      color: Colors.white,
                      fontSize: 28,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Your checkout has been extended to ${extension.newCheckOutLabel}',
                    textAlign: TextAlign.center,
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.5),
                      fontSize: 14,
                      height: 1.5,
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
                          'Back to My Stay',
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
