import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/media/ambient_video_background.dart';
import 'package:retirodelrocioapp/core/media/ambient_video_provider.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/core/widgets/coming_soon_screen.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/screens/guest_home_screen.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// 1.0 — Guest Welcome (checked-in state, Figma node 77:3152).
///
/// Personalized greeting + guest name, room card (suite / number / stay dates),
/// and a Quick Actions grid. Shown when a guest is checked in to the room.
class GuestWelcomeView extends ConsumerWidget {
  const GuestWelcomeView({
    super.key,
    required this.device,
    required this.status,
  });

  final ProvisionedDevice device;
  final RoomStatus status;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final video = ref.watch(ambientVideoProvider).value;
    final weather = ref.watch(weatherProvider).value;
    final now = DateTime.now();
    final guest = status.guest!;

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          AmbientVideoBackground(
            controller: video,
            fallback: Image.asset('assets/images/12375.jpg', fit: BoxFit.cover),
          ),
          // Same background overlay as the device setup screen.
          const ColoredBox(color: AppColors.scrim),
          Positioned(
            left: 24,
            top: 40,
            child: Image.asset(
              'assets/images/Rocio Logo Icon 1.png',
              width: 74,
              height: 38,
              fit: BoxFit.contain,
            ),
          ),
          Positioned(
            left: 24,
            top: 200,
            width: 520,
            child: _greetingColumn(context, guest, now, weather),
          ),
          Positioned(
            right: 24,
            top: 170,
            width: 368,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _roomCard(guest),
                const SizedBox(height: 22),
                _quickActionsCard(context),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _greeting(DateTime t) {
    if (t.hour < 12) return 'Good Morning,';
    if (t.hour < 17) return 'Good Afternoon,';
    return 'Good Evening,';
  }

  Widget _greetingColumn(
    BuildContext context,
    GuestInfo guest,
    DateTime now,
    weather,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          _greeting(now),
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 20,
          ),
        ),
        const SizedBox(height: 24),
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('Welcome Back,', style: _title(Colors.white)),
              const SizedBox(height: 12),
              Text('${guest.name}.', style: _title(AppColors.gold)),
            ],
          ),
        ),
        const SizedBox(height: 21),
        Text(
          DateFormat('EEEE, MMMM d, y').format(now),
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.5),
            fontSize: 20,
          ),
        ),
        const SizedBox(height: 15),
        Text(
          '${weather?.emoji ?? '🌡️'} ${weather?.temperatureLabel ?? '--°C'}'
          '${weather?.city != null ? ' • ${weather.city}' : ''}',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.35),
            fontSize: 15,
          ),
        ),
        const SizedBox(height: 37),
        _enterSuiteButton(context),
      ],
    );
  }

  TextStyle _title(Color color) => AppTypography.style(
    color: color,
    fontSize: 60,
    fontWeight: FontWeight.w700,
    height: 0.95,
  );

  Widget _enterSuiteButton(BuildContext context) {
    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => _enterSuite(context),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 40, vertical: 16),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Enter Your Suite',
                style: AppTypography.style(
                  color: AppColors.onGold,
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.48,
                ),
              ),
              const SizedBox(width: 12),
              const Icon(
                Icons.arrow_forward_rounded,
                color: AppColors.onGold,
                size: 20,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _roomCard(GuestInfo guest) {
    return _card(
      padding: const EdgeInsets.symmetric(horizontal: 34, vertical: 28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 57,
                height: 57,
                decoration: BoxDecoration(
                  color: AppColors.gold.withValues(alpha: 0.2),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.king_bed_outlined,
                  color: AppColors.gold,
                  size: 29,
                ),
              ),
              const SizedBox(width: 20),
              Expanded(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            (status.suiteName ?? 'Suite').toUpperCase(),
                            style: AppTypography.style(
                              color: Colors.white.withValues(alpha: 0.5),
                              fontSize: 12,
                              letterSpacing: 1.1,
                            ),
                          ),
                          const SizedBox(height: 9),
                          Text(
                            'YOUR ROOM',
                            style: AppTypography.style(
                              color: Colors.white,
                              fontSize: 15,
                              letterSpacing: 1.1,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Text(
                      status.roomNumber ?? '—',
                      style: AppTypography.style(
                        color: AppColors.gold,
                        fontSize: 44,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 23),
          Row(
            children: [
              Expanded(child: _dateChunk('CHECK-IN', guest.checkIn)),
              const SizedBox(width: 16),
              Container(
                width: 1,
                height: 34,
                color: Colors.white.withValues(alpha: 0.1),
              ),
              const SizedBox(width: 16),
              Expanded(child: _dateChunk('CHECK-OUT', guest.checkOut)),
            ],
          ),
        ],
      ),
    );
  }

  Widget _dateChunk(String label, DateTime? date) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 10,
            letterSpacing: 0.8,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          date != null ? DateFormat('MMM d, y').format(date) : '—',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.85),
            fontSize: 13,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }

  Widget _quickActionsCard(BuildContext context) {
    return _card(
      padding: const EdgeInsets.symmetric(horizontal: 36, vertical: 21),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                Icons.bolt_rounded,
                size: 14,
                color: Colors.white.withValues(alpha: 0.5),
              ),
              const SizedBox(width: 8),
              Text(
                'QUICK ACTIONS',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.5),
                  fontSize: 11,
                  letterSpacing: 1.1,
                ),
              ),
            ],
          ),
          const SizedBox(height: 17),
          Row(
            children: [
              Expanded(
                child: _quickAction(
                  context,
                  Icons.restaurant_rounded,
                  'Order Food',
                ),
              ),
              const SizedBox(width: 13),
              Expanded(
                child: _quickAction(
                  context,
                  Icons.settings_remote_rounded,
                  'Smart Room',
                ),
              ),
            ],
          ),
          const SizedBox(height: 22),
          Row(
            children: [
              Expanded(
                child: _quickAction(context, Icons.spa_rounded, 'Book Spa'),
              ),
              const SizedBox(width: 13),
              Expanded(
                child: _quickAction(context, Icons.call_rounded, 'Reception'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _quickAction(BuildContext context, IconData icon, String label) {
    return Material(
      color: Colors.white.withValues(alpha: 0.05),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => _open(context, label),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.08),
              width: 0.8,
            ),
          ),
          child: Column(
            children: [
              Icon(icon, color: AppColors.gold, size: 30),
              const SizedBox(height: 8),
              Text(
                label,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.7),
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _card({required EdgeInsets padding, required Widget child}) {
    return Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.12),
          width: 0.8,
        ),
      ),
      child: child,
    );
  }

  /// Opens the guest home dashboard (Figma 84:3429).
  void _enterSuite(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => GuestHomeScreen(device: device, status: status),
      ),
    );
  }

  void _open(BuildContext context, String title) {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => ComingSoonScreen(title: title)));
  }
}
