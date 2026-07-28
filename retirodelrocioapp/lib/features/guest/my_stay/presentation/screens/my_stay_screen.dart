import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/application/my_stay_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/domain/guest_stay.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/presentation/screens/extend_stay_screen.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// 2.6 — My Stay (Figma 132:776). The checked-in guest's reservation at a
/// glance: the room and stay window, who is on the booking, a bill summary, the
/// room's inclusions and the running balance — with Extend Stay leading into the
/// self-service extension flow.
class MyStayScreen extends ConsumerWidget {
  const MyStayScreen({super.key, required this.device, required this.status});

  final ProvisionedDevice device;
  final RoomStatus status;

  String get _token => device.token;

  void _extend(BuildContext context, GuestStay stay) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ExtendStayScreen(device: device, stay: stay),
      ),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final stayAsync = ref.watch(myStayProvider(_token));
    final stay = stayAsync.value;

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
                    suiteName: status.suiteName ?? 'Suite',
                    roomNumber: status.roomNumber ?? device.roomNumber ?? '—',
                    guestName:
                        status.guest?.name ??
                        stay?.guests.primaryName ??
                        'Guest',
                    weather: ref.watch(weatherProvider).value,
                    onNotifications: () {},
                    onProfile: () {},
                    hasUnreadNotifications:
                        ref.watch(guestUnreadNotificationsProvider(_token)) >
                        0,
                  ),
                  const SizedBox(height: 20),
                  _header(context, stay),
                  const SizedBox(height: 20),
                  Expanded(
                    child: stayAsync.when(
                      data: (data) => _content(context, data),
                      loading: () => stay != null
                          ? _content(context, stay)
                          : const Center(
                              child: CircularProgressIndicator(
                                color: AppColors.gold,
                              ),
                            ),
                      error: (_, _) => stay != null
                          ? _content(context, stay)
                          : _errorState(ref),
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

  Widget _header(BuildContext context, GuestStay? stay) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        _backButton(context),
        const SizedBox(width: 15),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'My Stay',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 36,
                  fontWeight: FontWeight.w700,
                  height: 1.15,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Current Reservation Details',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 15,
                ),
              ),
            ],
          ),
        ),
        if (stay != null) ...[
          const SizedBox(width: 16),
          _extendButton(context, stay),
        ],
      ],
    );
  }

  Widget _backButton(BuildContext context) {
    return Material(
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
    );
  }

  Widget _extendButton(BuildContext context, GuestStay stay) {
    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: () => _extend(context, stay),
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.calendar_month_rounded,
                size: 16,
                color: Color(0xFF0A0F1E),
              ),
              const SizedBox(width: 8),
              Text(
                'Extend Stay',
                style: AppTypography.style(
                  color: const Color(0xFF0A0F1E),
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _content(BuildContext context, GuestStay stay) {
    return SingleChildScrollView(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _reservationCard(stay.reservation),
                const SizedBox(height: 24),
                IntrinsicHeight(
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Expanded(child: _guestsCard(stay.guests, stay.visitors)),
                      const SizedBox(width: 20),
                      Expanded(child: _summaryCard(stay)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 24),
          SizedBox(
            width: 336,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _inclusionsCard(stay.inclusions),
                const SizedBox(height: 24),
                _currentBillCard(stay.currentBillLabel),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // --- Reservation ----------------------------------------------------------

  Widget _reservationCard(StayReservation r) {
    return Container(
      padding: const EdgeInsets.all(26),
      decoration: BoxDecoration(
        color: const Color(0xFF1B1D1A),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: AppColors.gold.withValues(alpha: 0.25),
          width: 0.8,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _eyebrow('RESERVATION'),
          const SizedBox(height: 16),
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              _roomPhoto(r.imageUrl),
              const SizedBox(width: 40),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      r.roomName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.style(
                        color: Colors.white,
                        fontSize: 32,
                        fontWeight: FontWeight.w700,
                        height: 33 / 32,
                      ),
                    ),
                    if ((r.unitLabel ?? '').isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text(
                        r.unitLabel!,
                        style: AppTypography.style(
                          color: AppColors.gold,
                          fontSize: 20,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                    const SizedBox(height: 20),
                    _factsRow(r),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _factsRow(StayReservation r) {
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _stayFact(
            'CHECK-IN',
            r.checkIn != null
                ? DateFormat('MMMM d, y').format(r.checkIn!)
                : '—',
            r.checkIn != null ? DateFormat('h:mm a').format(r.checkIn!) : null,
            Colors.white,
          ),
          _factDivider(),
          _stayFact(
            'CHECK-OUT',
            r.checkOut != null
                ? DateFormat('MMMM d, y').format(r.checkOut!)
                : '—',
            r.checkOut != null
                ? DateFormat('h:mm a').format(r.checkOut!)
                : null,
            Colors.white,
          ),
          _factDivider(),
          _stayFact(
            'DURATION',
            '${r.nights} ${r.nights == 1 ? 'Night' : 'Nights'}',
            r.rateLabel,
            AppColors.gold,
          ),
        ],
      ),
    );
  }

  Widget _factDivider() => Container(
    width: 1,
    margin: const EdgeInsets.symmetric(horizontal: 24),
    color: Colors.white.withValues(alpha: 0.1),
  );

  Widget _roomPhoto(String? url) {
    const fallback = Image(
      image: AssetImage('assets/images/2724.jpg'),
      fit: BoxFit.cover,
      width: 158,
      height: 156,
    );
    return Container(
      width: 158,
      height: 156,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: AppColors.gold.withValues(alpha: 0.3),
          width: 0.8,
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: url == null
          ? fallback
          : Image.network(
              url,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => fallback,
              frameBuilder: (_, child, frame, wasSync) =>
                  frame == null && !wasSync ? fallback : child,
            ),
    );
  }

  Widget _stayFact(String label, String value, String? sub, Color valueColor) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          label,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.35),
            fontSize: 10,
            letterSpacing: 0.8,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          value,
          style: AppTypography.style(
            color: valueColor,
            fontSize: 16,
            fontWeight: FontWeight.w700,
          ),
        ),
        if ((sub ?? '').isNotEmpty) ...[
          const SizedBox(height: 3),
          Text(
            sub!,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 12,
            ),
          ),
        ],
      ],
    );
  }

  // --- Guests ---------------------------------------------------------------

  Widget _guestsCard(StayGuests guests, List<StayVisitor> visitors) {
    final accompanying = guests.partySize - 1;
    final initials = guests.primaryName.trim().isEmpty
        ? 'G'
        : guests.primaryName
              .trim()
              .split(RegExp(r'\s+'))
              .take(2)
              .map((p) => p[0])
              .join()
              .toUpperCase();

    return _panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _eyebrow('GUESTS'),
          const SizedBox(height: 16),
          _guestRow(
            initials: initials,
            name: '${guests.primaryName} (Primary)',
            role: 'Room Owner',
            primary: true,
          ),
          for (var i = 0; i < accompanying; i++) ...[
            const SizedBox(height: 12),
            _guestRow(initials: 'G', name: 'Accompanying Guest'),
          ],
          if (visitors.isNotEmpty) ...[
            const SizedBox(height: 16),
            Divider(
              color: Colors.white.withValues(alpha: 0.1),
              height: 1,
              thickness: 0.8,
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                _eyebrow('VISITORS'),
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 2,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.gold.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    '${visitors.length}',
                    style: AppTypography.style(
                      color: AppColors.gold,
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
            for (final visitor in visitors) ...[
              const SizedBox(height: 12),
              _visitorRow(visitor),
            ],
          ],
        ],
      ),
    );
  }

  Widget _visitorRow(StayVisitor visitor) {
    final color = _visitorStatusColor(visitor.status);
    return Row(
      children: [
        Container(
          width: 32,
          height: 32,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: Icon(
            Icons.person_outline_rounded,
            size: 16,
            color: Colors.white.withValues(alpha: 0.6),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            visitor.name,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.style(color: Colors.white, fontSize: 13),
          ),
        ),
        const SizedBox(width: 8),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.15),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Text(
            visitor.statusLabel,
            style: AppTypography.style(
              color: color,
              fontSize: 10,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }

  Color _visitorStatusColor(String status) {
    switch (status) {
      case 'inside':
        return const Color(0xFF22C55E);
      case 'pending':
        return AppColors.gold;
      case 'denied':
        return const Color(0xFFEF4444);
      default:
        return Colors.white.withValues(alpha: 0.5);
    }
  }

  Widget _guestRow({
    required String initials,
    required String name,
    String? role,
    bool primary = false,
  }) {
    return Row(
      children: [
        Container(
          width: 32,
          height: 32,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: primary
                ? AppColors.gold
                : Colors.white.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: Text(
            initials,
            style: AppTypography.style(
              color: primary
                  ? const Color(0xFF0A0F1E)
                  : Colors.white.withValues(alpha: 0.6),
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppTypography.style(color: Colors.white, fontSize: 13),
              ),
              if (role != null) ...[
                const SizedBox(height: 1),
                Text(
                  role,
                  style: AppTypography.style(
                    color: AppColors.gold,
                    fontSize: 10,
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  // --- Stay summary ---------------------------------------------------------

  Widget _summaryCard(GuestStay stay) {
    return _panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _eyebrow('STAY SUMMARY'),
          const SizedBox(height: 16),
          for (final line in stay.summaryLines) ...[
            _summaryLine(line),
            const SizedBox(height: 12),
          ],
          Divider(
            color: Colors.white.withValues(alpha: 0.1),
            height: 1,
            thickness: 0.8,
          ),
          const SizedBox(height: 9),
          Row(
            children: [
              Text(
                'Total Bills',
                style: AppTypography.style(color: Colors.white, fontSize: 13),
              ),
              const Spacer(),
              Text(
                stay.summaryTotalLabel,
                style: AppTypography.style(
                  color: AppColors.gold,
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _summaryLine(StaySummaryLine line) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                line.label,
                style: AppTypography.style(color: Colors.white, fontSize: 13),
              ),
              if ((line.sub ?? '').isNotEmpty) ...[
                const SizedBox(height: 1),
                Text(
                  line.sub!,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.5),
                    fontSize: 11,
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(width: 12),
        Text(
          line.amountLabel,
          style: AppTypography.style(color: Colors.white, fontSize: 13),
        ),
      ],
    );
  }

  // --- Inclusions -----------------------------------------------------------

  Widget _inclusionsCard(List<StayInclusion> inclusions) {
    return _panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _eyebrow('ROOM INCLUSIONS'),
          const SizedBox(height: 16),
          if (inclusions.isEmpty)
            Text(
              'No inclusions listed for this room.',
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.4),
                fontSize: 13,
              ),
            )
          else
            for (var i = 0; i < inclusions.length; i++) ...[
              if (i > 0) const SizedBox(height: 20),
              _inclusionRow(inclusions[i]),
            ],
        ],
      ),
    );
  }

  Widget _inclusionRow(StayInclusion inc) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.gold.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(
              _inclusionIcon(inc.title),
              size: 16,
              color: AppColors.gold,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  inc.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.6),
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  inc.value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  IconData _inclusionIcon(String title) {
    final t = title.toLowerCase();
    if (t.contains('wifi') || t.contains('internet')) return Icons.wifi_rounded;
    if (t.contains('breakfast') || t.contains('dining') || t.contains('meal')) {
      return Icons.free_breakfast_rounded;
    }
    if (t.contains('transfer') ||
        t.contains('airport') ||
        t.contains('pickup') ||
        t.contains('car')) {
      return Icons.directions_car_rounded;
    }
    if (t.contains('member')) return Icons.star_rounded;
    if (t.contains('spa') || t.contains('pool')) return Icons.spa_rounded;
    if (t.contains('gym') || t.contains('fitness')) {
      return Icons.fitness_center_rounded;
    }
    if (t.contains('park')) return Icons.local_parking_rounded;
    return Icons.check_circle_outline_rounded;
  }

  // --- Current bill ---------------------------------------------------------

  Widget _currentBillCard(String amountLabel) {
    return Container(
      padding: const EdgeInsets.all(20.8),
      decoration: BoxDecoration(
        color: AppColors.gold.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: AppColors.gold.withValues(alpha: 0.2),
          width: 0.8,
        ),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'CURRENT BILL',
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.5),
                    fontSize: 11,
                    fontWeight: FontWeight.w500,
                    letterSpacing: 0.88,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  amountLabel,
                  style: AppTypography.style(
                    color: AppColors.gold,
                    fontSize: 24,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          Icon(
            Icons.chevron_right_rounded,
            size: 20,
            color: Colors.white.withValues(alpha: 0.5),
          ),
        ],
      ),
    );
  }

  // --- Shared ---------------------------------------------------------------

  Widget _panel({required Widget child, EdgeInsets? padding}) {
    return Container(
      padding: padding ?? const EdgeInsets.all(20.8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.1),
          width: 0.8,
        ),
      ),
      child: child,
    );
  }

  Widget _eyebrow(String text) => Text(
    text,
    style: AppTypography.style(
      color: Colors.white,
      fontSize: 11,
      fontWeight: FontWeight.w500,
      letterSpacing: 1.1,
    ),
  );

  Widget _errorState(WidgetRef ref) {
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
            'Could not load your stay.',
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
              onTap: () => ref.invalidate(myStayProvider(_token)),
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
