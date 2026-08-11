import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/bills/application/bills_providers.dart';
import 'package:retirodelrocioapp/features/guest/bills/data/bills_repository.dart';
import 'package:retirodelrocioapp/features/guest/bills/domain/bill.dart';
import 'package:retirodelrocioapp/features/guest/bills/presentation/widgets/bill_category_card.dart';
import 'package:retirodelrocioapp/features/guest/bills/presentation/widgets/bill_dialogs.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/presentation/screens/paystack_checkout_screen.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/presentation/screens/guest_notification_screen.dart';
import 'package:retirodelrocioapp/features/guest/sos/presentation/screens/sos_screen.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// 2.5/2.6 — My Bill (Figma 140:2522 → 140:2877). The checked-in guest's real
/// itemised folio — room charges, spa sessions charged to the room, and the
/// categories with nothing booked yet — plus an optional Paystack
/// pre-settlement of the outstanding balance ahead of checkout.
class GuestBillsScreen extends ConsumerStatefulWidget {
  const GuestBillsScreen({
    super.key,
    required this.device,
    required this.status,
  });

  final ProvisionedDevice device;
  final RoomStatus status;

  @override
  ConsumerState<GuestBillsScreen> createState() => _GuestBillsScreenState();
}

class _GuestBillsScreenState extends ConsumerState<GuestBillsScreen> {
  bool _paying = false;

  String get _token => widget.device.token;

  Future<void> _payNow() async {
    if (_paying) return;
    setState(() => _paying = true);
    try {
      final quote = await ref
          .read(billActionsProvider(_token))
          .initializePaystack();
      if (!mounted) return;

      final paid = await showPaystackCheckout(
        context,
        authorizationUrl: quote.authorizationUrl,
        callbackUrl: quote.callbackUrl,
      );
      if (!paid || !mounted) return;

      await ref
          .read(billActionsProvider(_token))
          .confirmPaystack(quote.reference);
      if (!mounted) return;

      ref.invalidate(billProvider(_token));
      ref.invalidate(guestNotificationsProvider(_token));
      await showBillConfirmedDialog(context);
    } on BillException catch (e) {
      if (mounted) _showError(e.message);
    } catch (_) {
      if (mounted) _showError('Something went wrong. Please try again.');
    } finally {
      if (mounted) setState(() => _paying = false);
    }
  }

  void _showError(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  void _openNotifications() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => GuestNotificationScreen(
          device: widget.device,
          status: widget.status,
        ),
      ),
    );
  }

  void _openEmergency() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SosScreen(device: widget.device, status: widget.status),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final billAsync = ref.watch(billProvider(_token));
    final bill = billAsync.value ?? Bill.empty;
    final weather = ref.watch(weatherProvider).value;
    final guest = widget.status.guest;

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
                    suiteName: widget.status.suiteName ?? 'Suite',
                    roomNumber:
                        widget.status.roomNumber ??
                        widget.device.roomNumber ??
                        '—',
                    guestName: guest?.name ?? 'Guest',
                    weather: weather,
                    onNotifications: _openNotifications,
                    onProfile: () {},
                    onEmergency: _openEmergency,
                    hasUnreadNotifications:
                        ref.watch(guestUnreadNotificationsProvider(_token)) > 0,
                  ),
                  const SizedBox(height: 20),
                  _header(bill),
                  const SizedBox(height: 19),
                  Expanded(
                    child: billAsync.when(
                      data: (data) => _content(data),
                      loading: () => bill.categories.isNotEmpty
                          ? _content(bill)
                          : const Center(
                              child: CircularProgressIndicator(
                                color: AppColors.gold,
                              ),
                            ),
                      error: (_, _) => bill.categories.isNotEmpty
                          ? _content(bill)
                          : _errorState(),
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

  Widget _header(Bill bill) {
    final subtitle = [
      if (bill.roomName != null) bill.roomName,
      if (bill.unitLabel != null) bill.unitLabel,
    ].whereType<String>().join(' - ');

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
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'My Bill',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 36,
                  fontWeight: FontWeight.w700,
                  height: 1.15,
                ),
              ),
              if (subtitle.isNotEmpty) ...[
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.4),
                    fontSize: 15,
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _content(Bill bill) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: SingleChildScrollView(
            child: Column(
              children: [
                for (var i = 0; i < bill.categories.length; i++) ...[
                  if (i > 0) const SizedBox(height: 16),
                  BillCategoryCard(
                    category: bill.categories[i],
                    initiallyExpanded: i == 0,
                  ),
                ],
              ],
            ),
          ),
        ),
        const SizedBox(width: 24),
        SizedBox(width: 320, child: _sidebar(bill)),
      ],
    );
  }

  Widget _sidebar(Bill bill) {
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _billSummaryCard(bill),
          const SizedBox(height: 15),
          _checkoutReminderCard(bill),
        ],
      ),
    );
  }

  Widget _billSummaryCard(Bill bill) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
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
            'Bill Summary',
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          for (final line in bill.summaryLines) ...[
            _summaryRow(line.label, line.amountLabel),
            const SizedBox(height: 10),
          ],
          const SizedBox(height: 4),
          Container(height: 1, color: Colors.white.withValues(alpha: 0.1)),
          const SizedBox(height: 16),
          Row(
            children: [
              Text(
                'Total Due',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerRight,
                  child: Text(
                    bill.totalDueLabel,
                    style: AppTypography.style(
                      color: AppColors.gold,
                      fontSize: 22,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          _payNowButton(bill),
        ],
      ),
    );
  }

  Widget _summaryRow(String label, String value) {
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
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }

  Widget _payNowButton(Bill bill) {
    if (!bill.canPay) {
      return Container(
        height: 56,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.06),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: Colors.white.withValues(alpha: 0.08),
            width: 0.8,
          ),
        ),
        child: Text(
          'All Settled',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 14,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }

    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: _paying ? null : _payNow,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          height: 56,
          alignment: Alignment.center,
          child: _paying
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
    );
  }

  Widget _checkoutReminderCard(Bill bill) {
    final reminder = bill.checkoutReminder;
    if (reminder.checkOutLabel == null) return const SizedBox.shrink();

    return Container(
      padding: const EdgeInsets.all(17),
      decoration: BoxDecoration(
        color: AppColors.gold.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: AppColors.gold.withValues(alpha: 0.15),
          width: 0.8,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.info_outline_rounded, size: 14, color: AppColors.gold),
              const SizedBox(width: 8),
              Text(
                'CHECKOUT REMINDER',
                style: AppTypography.style(
                  color: AppColors.gold,
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1.2,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            reminder.checkOutLabel!,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.7),
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            reminder.daysRemainingLabel,
            style: AppTypography.style(
              color: AppColors.gold,
              fontSize: 13,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  Widget _errorState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.cloud_off_rounded,
            size: 30,
            color: Colors.white.withValues(alpha: 0.4),
          ),
          const SizedBox(height: 12),
          Text(
            'Could not load your bill.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.6),
              fontSize: 15,
            ),
          ),
          const SizedBox(height: 16),
          Material(
            color: AppColors.gold,
            borderRadius: BorderRadius.circular(12),
            child: InkWell(
              onTap: () => ref.invalidate(billProvider(_token)),
              borderRadius: BorderRadius.circular(12),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 24,
                  vertical: 12,
                ),
                child: Text(
                  'Retry',
                  style: AppTypography.style(
                    color: AppColors.onGold,
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
