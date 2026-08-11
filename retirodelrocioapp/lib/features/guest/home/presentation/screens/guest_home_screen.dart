import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/session/device_revocation.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/core/widgets/coming_soon_screen.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/bills/presentation/screens/guest_bills_screen.dart';
import 'package:retirodelrocioapp/features/guest/chat/presentation/screens/guest_chat_screen.dart';
import 'package:retirodelrocioapp/features/guest/cinema/presentation/screens/guest_cinema_screen.dart';
import 'package:retirodelrocioapp/features/guest/dining/presentation/screens/guest_dining_screen.dart';
import 'package:retirodelrocioapp/features/guest/home/domain/guest_service.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/current_stay_card.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/quick_service_card.dart';
import 'package:retirodelrocioapp/features/guest/intercom/presentation/screens/guest_intercom_screen.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/presentation/screens/my_stay_screen.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/presentation/screens/guest_notification_screen.dart';
import 'package:retirodelrocioapp/features/guest/service_requests/presentation/screens/guest_service_request_screen.dart';
import 'package:retirodelrocioapp/features/guest/sos/presentation/screens/sos_screen.dart';
import 'package:retirodelrocioapp/features/guest/spa/presentation/screens/guest_spa_screen.dart';
import 'package:retirodelrocioapp/features/guest/visitor_pass/presentation/screens/visitor_pass_screen.dart';
import 'package:retirodelrocioapp/features/welcome/application/room_status_providers.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// 1.1 — Guest Home Screen (Figma node 84:3429).
///
/// The checked-in guest's dashboard: identity/weather header, the welcome line
/// with Emergency, the current-stay summary and the Quick Services grid. It
/// keeps polling the room status, so when reception checks the guest out the
/// tablet returns to the idle welcome screen on its own.
class GuestHomeScreen extends ConsumerStatefulWidget {
  const GuestHomeScreen({
    super.key,
    required this.device,
    required this.status,
  });

  final ProvisionedDevice device;

  /// Status at entry; kept live by [roomStatusProvider] while the screen is up.
  final RoomStatus status;

  @override
  ConsumerState<GuestHomeScreen> createState() => _GuestHomeScreenState();
}

class _GuestHomeScreenState extends ConsumerState<GuestHomeScreen> {
  /// The in-room tablet is a shared kiosk: when a guest walks away and leaves it
  /// on the home screen, it returns to the idle welcome screen on its own after
  /// this long with no interaction — so the next person (or a passer-by) never
  /// finds it sitting on someone else's checked-in dashboard.
  static const Duration _idleTimeout = Duration(minutes: 5);
  Timer? _idleTimer;

  @override
  void initState() {
    super.initState();
    _resetIdleTimer();
  }

  @override
  void dispose() {
    _idleTimer?.cancel();
    super.dispose();
  }

  /// Restart the idle countdown from now — called on every touch, and again each
  /// time the guest returns to the home screen from a service.
  void _resetIdleTimer() {
    _idleTimer?.cancel();
    _idleTimer = Timer(_idleTimeout, _onIdle);
  }

  /// The idle timeout fired. Drop back to the welcome screen the same way a
  /// check-out does — but only while the home screen itself is on top. If the
  /// guest is deep in a service they may simply be reading, so we wait and
  /// re-check rather than yanking them out mid-task.
  void _onIdle() {
    if (!mounted) return;

    final route = ModalRoute.of(context);
    if (route != null && !route.isCurrent) {
      _idleTimer = Timer(_idleTimeout, _onIdle);
      return;
    }

    Navigator.of(context).popUntil((route) => route.isFirst);
  }

  Future<void> _open(GuestService service) async {
    // Notifications is built — take the guest there rather than "coming soon".
    if (service.id == _alerts.id) {
      final live = ref.read(roomStatusProvider(widget.device.token)).value;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => GuestNotificationScreen(
            device: widget.device,
            status: live ?? widget.status,
          ),
        ),
      );
      if (mounted) _resetIdleTimer();
      return;
    }

    // Visitor Pass is built — take the guest there rather than "coming soon".
    if (service.id == GuestServices.visitorPass.id) {
      final live = ref.read(roomStatusProvider(widget.device.token)).value;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => VisitorPassScreen(
            device: widget.device,
            status: live ?? widget.status,
          ),
        ),
      );
      if (mounted) _resetIdleTimer();
      return;
    }

    // My Stay is built — the reservation details and the extend-stay flow.
    if (service.id == GuestServices.myStay.id) {
      final live = ref.read(roomStatusProvider(widget.device.token)).value;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => MyStayScreen(
            device: widget.device,
            status: live ?? widget.status,
          ),
        ),
      );
      if (mounted) _resetIdleTimer();
      return;
    }

    // Place Order (Dining) is built — take the guest there rather than "coming soon".
    if (service.id == GuestServices.dining.id) {
      final live = ref.read(roomStatusProvider(widget.device.token)).value;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => GuestDiningScreen(
            device: widget.device,
            status: live ?? widget.status,
          ),
        ),
      );
      if (mounted) _resetIdleTimer();
      return;
    }

    // Spa & Wellness is built — take the guest there rather than "coming soon".
    if (service.id == GuestServices.spa.id) {
      final live = ref.read(roomStatusProvider(widget.device.token)).value;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => GuestSpaScreen(
            device: widget.device,
            status: live ?? widget.status,
          ),
        ),
      );
      if (mounted) _resetIdleTimer();
      return;
    }

    // Cinema is built — take the guest there rather than "coming soon".
    if (service.id == GuestServices.cinema.id) {
      final live = ref.read(roomStatusProvider(widget.device.token)).value;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => GuestCinemaScreen(
            device: widget.device,
            status: live ?? widget.status,
          ),
        ),
      );
      if (mounted) _resetIdleTimer();
      return;
    }

    // My Bills is built — take the guest there rather than "coming soon".
    if (service.id == GuestServices.bills.id) {
      final live = ref.read(roomStatusProvider(widget.device.token)).value;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => GuestBillsScreen(
            device: widget.device,
            status: live ?? widget.status,
          ),
        ),
      );
      if (mounted) _resetIdleTimer();
      return;
    }

    // Service Request is built — take the guest there rather than "coming soon".
    if (service.id == GuestServices.serviceRequest.id) {
      final live = ref.read(roomStatusProvider(widget.device.token)).value;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => GuestServiceRequestScreen(
            device: widget.device,
            status: live ?? widget.status,
          ),
        ),
      );
      if (mounted) _resetIdleTimer();
      return;
    }

    // Concierge Chat is built — take the guest there rather than "coming soon".
    if (service.id == GuestServices.chat.id) {
      final live = ref.read(roomStatusProvider(widget.device.token)).value;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => GuestChatScreen(
            device: widget.device,
            status: live ?? widget.status,
          ),
        ),
      );
      if (mounted) _resetIdleTimer();
      return;
    }

    // Intercom is built — take the guest there rather than "coming soon".
    if (service.id == GuestServices.intercom.id) {
      final live = ref.read(roomStatusProvider(widget.device.token)).value;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => GuestIntercomScreen(
            device: widget.device,
            status: live ?? widget.status,
          ),
        ),
      );
      if (mounted) _resetIdleTimer();
      return;
    }

    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ComingSoonScreen(title: service.title)),
    );
    if (mounted) _resetIdleTimer();
  }

  /// Opens the Emergency SOS screen (Figma 113:725). The confirm step and the
  /// alert itself live there, so the guest reaches the same place whether they
  /// come from here or from an alert already in progress.
  Future<void> _emergency() async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SosScreen(device: widget.device, status: widget.status),
      ),
    );
    if (mounted) _resetIdleTimer();
  }

  @override
  Widget build(BuildContext context) {
    // Keep following the room for live stay-card data, and to drop a tablet
    // deleted from the dashboard back to device setup. The pop-back-to-welcome
    // reaction to a check-out is deliberately NOT duplicated here — WelcomeScreen
    // (mounted underneath this and every other pushed guest screen) is the sole
    // authority on that, watching the same provider itself. Two independent
    // `popUntil` calls racing in the same frame — one from here, one from
    // WelcomeScreen — could interleave with the Navigator's pop-animation state
    // machine and leave the stack not fully unwound, so only one caller may ever
    // own this.
    final statusAsync = ref.watch(roomStatusProvider(widget.device.token));
    unpairIfRevoked(context, statusAsync);

    final live = statusAsync.value;
    final status = (live != null && live.isOccupied && live.hasGuest)
        ? live
        : widget.status;

    final weather = ref.watch(weatherProvider).value;
    final guest = status.guest!;

    return Listener(
      // Any touch counts as "in use" and restarts the idle countdown. Translucent
      // so it observes every pointer without stealing taps from the UI beneath.
      behavior: HitTestBehavior.translucent,
      onPointerDown: (_) => _resetIdleTimer(),
      child: Scaffold(
        backgroundColor: AppColors.background,
        body: Stack(
          fit: StackFit.expand,
          children: [
            Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
            const ColoredBox(color: Color.fromARGB(243, 0, 0, 0)),
            SafeArea(
              // Header, stay card and section heading stay put; the design frame
              // is taller than the tablet, so only the service grid scrolls.
              child: Padding(
                padding: const EdgeInsets.fromLTRB(25, 24, 25, 0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    GuestTopBar(
                      suiteName: status.suiteName ?? 'Suite',
                      roomNumber: status.roomNumber ?? '—',
                      guestName: guest.name,
                      weather: weather,
                      onNotifications: () => _open(_alerts),
                      onProfile: () => _open(GuestServices.myStay),
                      onEmergency: _emergency,
                      hasUnreadNotifications:
                          ref.watch(
                            guestUnreadNotificationsProvider(
                              widget.device.token,
                            ),
                          ) >
                          0,
                    ),
                    const SizedBox(height: 17),
                    _header(guest.name),
                    const SizedBox(height: 17),
                    CurrentStayCard(
                      status: status,
                      roomImageUrl: widget.device.roomImageUrl,
                      onExtendStay: () => _open(GuestServices.myStay),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Quick Services',
                      style: AppTypography.style(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w600,
                        height: 27 / 18,
                      ),
                    ),
                    const SizedBox(height: 15),
                    Expanded(
                      child: SingleChildScrollView(
                        padding: const EdgeInsets.only(bottom: 28),
                        child: _quickServicesGrid(),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  static const _alerts = GuestService(
    id: 'alerts',
    title: 'Notifications',
    tagline: 'Hotel updates',
    icon: Icons.notifications_none_rounded,
  );

  Widget _header(String guestName) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'WELCOME BACK,',
          style: AppTypography.style(
            color: AppColors.gold,
            fontSize: 12,
            letterSpacing: 0.6,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          guestName,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 36,
            fontWeight: FontWeight.w700,
            height: 1.1,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          'Your suite is set. Explore in-suite dining, smart room controls, '
          'spa and more.',
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.7),
            fontSize: 15,
          ),
        ),
      ],
    );
  }

  /// Three rows of 280px cards — 36px apart across, 34px down (Figma 111:261).
  /// The last row holds only three, and stays left-aligned on the same columns.
  Widget _quickServicesGrid() {
    const rows = GuestServices.grid;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (var r = 0; r < rows.length; r++) ...[
          if (r > 0) const SizedBox(height: 34),
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              for (var c = 0; c < rows[r].length; c++) ...[
                if (c > 0) const SizedBox(width: 36),
                SizedBox(
                  width: 280,
                  child: QuickServiceCard(
                    service: rows[r][c],
                    onTap: () => _open(rows[r][c]),
                  ),
                ),
              ],
            ],
          ),
        ],
      ],
    );
  }
}
