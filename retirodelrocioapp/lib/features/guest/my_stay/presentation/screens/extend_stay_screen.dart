import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/application/my_stay_providers.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/data/my_stay_repository.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/domain/guest_stay.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/presentation/screens/paystack_checkout_screen.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/presentation/widgets/extend_calendar.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/presentation/widgets/extend_stay_dialogs.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';

/// 2.6a — Extend Stay (Figma 137:1157 → 137:1437). The guest picks a new
/// checkout date on the calendar; the extension summary prices the extra nights
/// live; Confirm opens the payment summary, and a successful charge moves the
/// checkout and shows the confirmation.
class ExtendStayScreen extends ConsumerStatefulWidget {
  const ExtendStayScreen({super.key, required this.device, required this.stay});

  final ProvisionedDevice device;
  final GuestStay stay;

  @override
  ConsumerState<ExtendStayScreen> createState() => _ExtendStayScreenState();
}

class _ExtendStayScreenState extends ConsumerState<ExtendStayScreen> {
  late DateTime _month;
  DateTime? _selected;

  /// True while the Paystack charge is being opened (the Confirm button spins).
  bool _busy = false;

  String get _token => widget.device.token;
  StayReservation get _r => widget.stay.reservation;

  /// The current checkout as a date only, the floor for any new checkout.
  DateTime get _currentOut {
    final c = _r.checkOut ?? DateTime.now();
    return DateTime(c.year, c.month, c.day);
  }

  @override
  void initState() {
    super.initState();
    _month = DateTime(_currentOut.year, _currentOut.month);
  }

  int get _additionalNights =>
      _selected == null ? 0 : _selected!.difference(_currentOut).inDays;

  int get _additionalCost => _additionalNights * _r.ratePerNight;

  String get _money => 'NGN ${NumberFormat('#,###').format(_additionalCost)}';

  void _stepMonth(int delta) =>
      setState(() => _month = DateTime(_month.year, _month.month + delta));

  Future<void> _confirm() async {
    final selected = _selected;
    if (selected == null || _busy) return;

    // Price the extension and open the Paystack charge.
    setState(() => _busy = true);
    final ExtensionQuote quote;
    try {
      quote = await ref
          .read(myStayActionsProvider(_token))
          .initializeExtension(selected);
    } on MyStayException catch (e) {
      if (mounted) {
        setState(() => _busy = false);
        _toast(e.message);
      }
      return;
    } catch (_) {
      if (mounted) {
        setState(() => _busy = false);
        _toast('Could not start the payment. Please try again.');
      }
      return;
    }
    if (!mounted) return;
    setState(() => _busy = false);

    // Payment Summary (VAT 7.5%) → Pay Now → Paystack → verify → apply.
    final extension = await showExtendPaymentDialog(
      context,
      quote: quote,
      partySize: widget.stay.guests.partySize,
      onPay: () async {
        final paid = await showPaystackCheckout(
          context,
          authorizationUrl: quote.authorizationUrl,
          callbackUrl: quote.callbackUrl,
        );
        if (!paid) return null;

        final stay = await ref
            .read(myStayActionsProvider(_token))
            .extend(selected, quote.reference);
        // The server is the source of truth for the extension's figures.
        return stay.extension ??
            StayExtension(
              additionalNights: quote.additionalNights,
              additionalCost: 0,
              additionalCostLabel: quote.totalLabel,
              newCheckOut: selected,
              newCheckOutLabel: DateFormat('MMMM d, y').format(selected),
            );
      },
    );

    if (extension == null || !mounted) return;

    await showStayExtendedDialog(context, extension: extension);
    // Back to My Stay, which reloads the (now longer) reservation from the server.
    if (mounted) Navigator.of(context).pop();
  }

  void _toast(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: const Color(0xFF7F1D1D),
        content: Text(
          message,
          style: AppTypography.style(color: Colors.white, fontSize: 14),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
          const ColoredBox(color: Color.fromARGB(243, 0, 0, 0)),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(25, 24, 25, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  GuestTopBar(
                    suiteName: _r.roomName,
                    roomNumber: _roomNumber,
                    guestName: widget.stay.guests.primaryName,
                    weather: ref.watch(weatherProvider).value,
                    onNotifications: () {},
                    onProfile: () {},
                  ),
                  const SizedBox(height: 20),
                  _header(),
                  const SizedBox(height: 24),
                  Expanded(
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: SingleChildScrollView(
                            child: ExtendCalendar(
                              month: _month,
                              checkIn: _r.checkIn,
                              currentCheckOut: _currentOut,
                              selected: _selected,
                              onSelect: (d) => setState(() => _selected = d),
                              onMonth: _stepMonth,
                            ),
                          ),
                        ),
                        const SizedBox(width: 24),
                        SizedBox(width: 300, child: _summaryColumn()),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _header() {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Material(
          color: Colors.white.withValues(alpha: 0.06),
          shape: CircleBorder(
            side: BorderSide(
              color: Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          child: InkWell(
            onTap: () => Navigator.of(context).maybePop(),
            customBorder: const CircleBorder(),
            child: SizedBox(
              width: 40,
              height: 40,
              child: Icon(
                Icons.arrow_back_rounded,
                size: 18,
                color: Colors.white.withValues(alpha: 0.8),
              ),
            ),
          ),
        ),
        const SizedBox(width: 15),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Extend Stay',
              style: AppTypography.style(
                color: Colors.white,
                fontSize: 36,
                fontWeight: FontWeight.w700,
                height: 1.15,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              'Select your new checkout date',
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.4),
                fontSize: 15,
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _summaryColumn() {
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            padding: const EdgeInsets.all(24.8),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(24),
              border: Border.all(
                color: AppColors.gold.withValues(alpha: 0.2),
                width: 0.8,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'EXTENSION SUMMARY',
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 11,
                    fontWeight: FontWeight.w500,
                    letterSpacing: 1.1,
                  ),
                ),
                const SizedBox(height: 16),
                _summaryField(
                  'Current Checkout',
                  _r.checkOut != null
                      ? DateFormat('MMMM d, y').format(_r.checkOut!)
                      : '—',
                  Colors.white,
                  fontSize: 15,
                  weight: FontWeight.w600,
                ),
                const SizedBox(height: 16),
                _summaryField(
                  'New Checkout',
                  _selected != null
                      ? DateFormat('MMMM d, y').format(_selected!)
                      : 'Not selected',
                  _selected != null
                      ? AppColors.gold
                      : Colors.white.withValues(alpha: 0.3),
                  fontSize: 15,
                  weight: FontWeight.w600,
                ),
                const SizedBox(height: 16),
                Container(
                  height: 1,
                  color: Colors.white.withValues(alpha: 0.1),
                ),
                const SizedBox(height: 16),
                _summaryField(
                  'Additional Nights',
                  _selected == null ? '—' : '$_additionalNights',
                  Colors.white,
                  fontSize: 22,
                  weight: FontWeight.w700,
                ),
                const SizedBox(height: 16),
                _summaryField(
                  'Rate per Night',
                  _r.rateLabel.replaceAll('/night', '').trim(),
                  Colors.white,
                  fontSize: 15,
                  weight: FontWeight.w400,
                ),
                const SizedBox(height: 16),
                _costBox(),
              ],
            ),
          ),
          const SizedBox(height: 16),
          _confirmButton(),
        ],
      ),
    );
  }

  Widget _summaryField(
    String label,
    String value,
    Color valueColor, {
    required double fontSize,
    required FontWeight weight,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 11,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          value,
          style: AppTypography.style(
            color: valueColor,
            fontSize: fontSize,
            fontWeight: weight,
          ),
        ),
      ],
    );
  }

  Widget _costBox() {
    return Container(
      padding: const EdgeInsets.all(16.8),
      decoration: BoxDecoration(
        color: AppColors.gold.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: AppColors.gold.withValues(alpha: 0.2),
          width: 0.8,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Additional Cost',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.5),
              fontSize: 11,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            _selected == null ? '—' : _money,
            style: AppTypography.style(
              color: AppColors.gold,
              fontSize: 26,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  Widget _confirmButton() {
    final enabled = _selected != null && _additionalNights > 0 && !_busy;
    final button = Material(
      color: AppColors.gold,
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
                    color: Color(0xFF0A0F1E),
                  ),
                )
              : Text(
                  'Confirm Extension',
                  style: AppTypography.style(
                    color: const Color(0xFF0A0F1E),
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                  ),
                ),
        ),
      ),
    );

    // Disabled reads as the same gold button dimmed, per the design's 40% state.
    // While busy it stays solid (it's working), just not tappable.
    return (enabled || _busy)
        ? DecoratedBox(
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
            child: button,
          )
        : Opacity(opacity: 0.4, child: button);
  }

  /// The room number for the top bar, from the reservation's "Room 101" label.
  String get _roomNumber {
    final label = _r.unitLabel ?? '';
    final digits = RegExp(r'\d+').firstMatch(label)?.group(0);
    return digits ?? (label.isNotEmpty ? label : '—');
  }
}
