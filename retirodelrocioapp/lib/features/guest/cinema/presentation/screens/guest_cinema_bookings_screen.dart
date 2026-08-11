import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/cinema/application/cinema_providers.dart';
import 'package:retirodelrocioapp/features/guest/cinema/domain/cinema_service.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/presentation/screens/guest_notification_screen.dart';
import 'package:retirodelrocioapp/features/guest/sos/presentation/screens/sos_screen.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// My Bookings — every private-room cinema booking the guest has confirmed,
/// past or upcoming, however it was paid. A back button reaches this screen
/// (no left sidebar), same as every other guest sub-screen.
class GuestCinemaBookingsScreen extends ConsumerWidget {
  const GuestCinemaBookingsScreen({
    super.key,
    required this.device,
    required this.status,
  });

  final ProvisionedDevice device;
  final RoomStatus status;

  String get _token => device.token;

  void _openNotifications(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => GuestNotificationScreen(device: device, status: status),
      ),
    );
  }

  void _openEmergency(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SosScreen(device: device, status: status),
      ),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bookingsAsync = ref.watch(cinemaBookingsProvider(_token));
    final bookings = bookingsAsync.value ?? const <CinemaBookingAppointment>[];
    final weather = ref.watch(weatherProvider).value;
    final guest = status.guest;

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
                    guestName: guest?.name ?? 'Guest',
                    weather: weather,
                    onNotifications: () => _openNotifications(context),
                    onProfile: () {},
                    onEmergency: () => _openEmergency(context),
                    hasUnreadNotifications:
                        ref.watch(guestUnreadNotificationsProvider(_token)) > 0,
                  ),
                  const SizedBox(height: 20),
                  _header(context),
                  const SizedBox(height: 19),
                  Expanded(
                    child: bookingsAsync.when(
                      data: (data) => _content(ref, data),
                      loading: () => bookings.isNotEmpty
                          ? _content(ref, bookings)
                          : const Center(
                              child: CircularProgressIndicator(
                                color: AppColors.gold,
                              ),
                            ),
                      error: (_, _) => bookings.isNotEmpty
                          ? _content(ref, bookings)
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

  Widget _header(BuildContext context) {
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
                'My Bookings',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 36,
                  fontWeight: FontWeight.w700,
                  height: 1.15,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                'Every private room you\'ve booked, past and upcoming',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 15,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _content(WidgetRef ref, List<CinemaBookingAppointment> bookings) {
    if (bookings.isEmpty) return _emptyState();

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(cinemaBookingsProvider(_token)),
      child: ListView.separated(
        padding: const EdgeInsets.only(bottom: 24),
        itemCount: bookings.length,
        separatorBuilder: (_, _) => const SizedBox(height: 12),
        itemBuilder: (_, i) => _BookingCard(booking: bookings[i]),
      ),
    );
  }

  Widget _emptyState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.movie_outlined,
            size: 32,
            color: Colors.white.withValues(alpha: 0.3),
          ),
          const SizedBox(height: 12),
          Text(
            'No cinema bookings yet',
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 15,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'Book a private room from Cinema and it\'ll show up here.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }

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
            'Could not load your bookings.',
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
              onTap: () => ref.invalidate(cinemaBookingsProvider(_token)),
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

class _BookingCard extends StatelessWidget {
  const _BookingCard({required this.booking});

  final CinemaBookingAppointment booking;

  Color get _statusColor => switch (booking.status) {
    'confirmed' => const Color(0xFF00FF00),
    'used' => Colors.white.withValues(alpha: 0.5),
    'cancelled' => const Color(0xFFEF4444),
    _ => AppColors.gold,
  };

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.1),
          width: 0.8,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.gold.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Icon(
              Icons.movie_rounded,
              size: 18,
              color: AppColors.gold,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        booking.movieTitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTypography.style(
                          color: Colors.white,
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Text(
                      booking.totalLabel,
                      style: AppTypography.style(
                        color: AppColors.gold,
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Row(
                  children: [
                    Icon(
                      Icons.calendar_today_rounded,
                      size: 11,
                      color: Colors.white.withValues(alpha: 0.35),
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        [
                          if (booking.showDateLabel != null)
                            booking.showDateLabel,
                          booking.showTime,
                          if (booking.room != null) booking.room,
                        ].whereType<String>().join(' • '),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTypography.style(
                          color: Colors.white.withValues(alpha: 0.5),
                          fontSize: 12,
                        ),
                      ),
                    ),
                  ],
                ),
                if (booking.snacksLabel != '—') ...[
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Icon(
                        Icons.local_cafe_rounded,
                        size: 11,
                        color: Colors.white.withValues(alpha: 0.35),
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          booking.snacksLabel,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: AppTypography.style(
                            color: Colors.white.withValues(alpha: 0.5),
                            fontSize: 12,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
                const SizedBox(height: 10),
                Row(
                  children: [
                    _pill(booking.statusLabel, color: _statusColor),
                    const SizedBox(width: 8),
                    _pill(
                      booking.paymentMethodLabel,
                      color: booking.chargedToRoom
                          ? AppColors.gold
                          : Colors.white,
                      muted: true,
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _pill(String label, {required Color color, bool muted = false}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: muted ? 0.08 : 0.14),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: AppTypography.style(
          color: muted ? color.withValues(alpha: 0.7) : color,
          fontSize: 11,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
