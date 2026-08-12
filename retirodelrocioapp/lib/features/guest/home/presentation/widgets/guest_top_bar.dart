import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/glass_panel.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';

/// The frosted header bar: brand + suite, the guest identity chip, the live
/// weather/clock pill, notifications and the guest avatar (Figma 84:3429).
class GuestTopBar extends StatelessWidget {
  const GuestTopBar({
    super.key,
    required this.suiteName,
    required this.roomNumber,
    required this.guestName,
    required this.weather,
    required this.onNotifications,
    required this.onProfile,
    required this.onEmergency,
    this.hasUnreadNotifications = false,
  });

  final String suiteName;
  final String roomNumber;
  final String guestName;
  final Weather? weather;
  final VoidCallback onNotifications;
  final VoidCallback onProfile;

  /// Opens Emergency SOS — present on every guest screen via this shared
  /// top bar, not just the home dashboard, so help is always one tap away.
  final VoidCallback onEmergency;

  /// Lights up the gold dot on the bell — real unread state, not decoration.
  final bool hasUnreadNotifications;

  String get _initials {
    final parts = guestName.trim().split(RegExp(r'\s+'));
    final letters = parts.where((p) => p.isNotEmpty).take(2).map((p) => p[0]);
    return letters.join().toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    return GlassPanel(
      height: 63,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Row(
        children: [
          _logo(),
          const SizedBox(width: 7),
          _brand(),
          _divider(),
          Expanded(child: _identity()),
          const SizedBox(width: 16),
          _WeatherClockPill(weather: weather),
          const SizedBox(width: 15),
          _emergencyButton(),
          const SizedBox(width: 15),
          _notificationsButton(),
          const SizedBox(width: 15),
          _avatar(),
        ],
      ),
    );
  }

  /// The brand mark, which the design moved into the header (42px gold disc).
  Widget _logo() {
    return Container(
      width: 42,
      height: 42,
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: AppColors.gold.withValues(alpha: 0.12),
        shape: BoxShape.circle,
        border: Border.all(
          color: AppColors.gold.withValues(alpha: 0.28),
          width: 0.8,
        ),
      ),
      child: Image.asset(
        'assets/icons/Rociologosetup.png',
        fit: BoxFit.contain,
      ),
    );
  }

  Widget _brand() {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'RETIRO DEL ROCIO',
          style: AppTypography.style(
            color: AppColors.gold,
            fontSize: 12,
            fontWeight: FontWeight.w700,
            letterSpacing: 1.68,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          suiteName.toUpperCase(),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 10,
          ),
        ),
      ],
    );
  }

  Widget _divider() => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 16),
    child: Container(
      width: 1,
      height: 22,
      color: Colors.white.withValues(alpha: 0.1),
    ),
  );

  Widget _identity() {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Guest',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.45),
            fontSize: 11,
          ),
        ),
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Flexible(
              child: Text(
                guestName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppTypography.style(color: Colors.white, fontSize: 15),
              ),
            ),
            const SizedBox(width: 8),
            Text(
              '·',
              style: AppTypography.style(color: Colors.white, fontSize: 16),
            ),
            const SizedBox(width: 8),
            Container(
              padding: const EdgeInsets.symmetric(
                horizontal: 10.8,
                vertical: 2.8,
              ),
              decoration: BoxDecoration(
                color: AppColors.gold.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(999),
                border: Border.all(
                  color: AppColors.gold.withValues(alpha: 0.28),
                  width: 0.8,
                ),
              ),
              child: Text(
                'Room $roomNumber',
                style: AppTypography.style(
                  color: AppColors.gold,
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  letterSpacing: 0.33,
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  /// A compact red twin of the notification bell — present on every screen
  /// via this shared top bar, so Emergency SOS is always reachable.
  Widget _emergencyButton() {
    const red = Color(0xFFFF0000);
    return SizedBox(
      width: 35,
      height: 35,
      child: Material(
        color: red,
        shape: CircleBorder(side: BorderSide(color: red, width: 0.8)),
        child: InkWell(
          onTap: onEmergency,
          customBorder: const CircleBorder(),
          child: const Center(
            child: Icon(
              Icons.warning_amber_rounded,
              size: 16,
              color: Colors.white,
            ),
          ),
        ),
      ),
    );
  }

  Widget _notificationsButton() {
    return SizedBox(
      width: 35,
      height: 35,
      child: Stack(
        children: [
          Material(
            color: Colors.white.withValues(alpha: 0.12),
            shape: CircleBorder(
              side: BorderSide(
                color: Colors.white.withValues(alpha: 0.2),
                width: 0.8,
              ),
            ),
            child: InkWell(
              onTap: onNotifications,
              customBorder: const CircleBorder(),
              child: const Center(
                child: Icon(
                  Icons.notifications_none_rounded,
                  size: 16,
                  color: Colors.white,
                ),
              ),
            ),
          ),
          if (hasUnreadNotifications)
            const Positioned(
              right: 6,
              top: 5,
              child: SizedBox(
                width: 6,
                height: 6,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    color: AppColors.gold,
                    shape: BoxShape.circle,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _avatar() {
    return Material(
      color: AppColors.gold,
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onProfile,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 35,
          height: 35,
          child: Center(
            child: Text(
              _initials,
              style: AppTypography.style(
                color: Colors.black,
                fontSize: 15,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Weather + clock. Owns its own 1-second ticker so only this pill rebuilds
/// each second, and uses tabular figures so the digits never shift the layout.
class _WeatherClockPill extends StatefulWidget {
  const _WeatherClockPill({required this.weather});

  final Weather? weather;

  @override
  State<_WeatherClockPill> createState() => _WeatherClockPillState();
}

class _WeatherClockPillState extends State<_WeatherClockPill> {
  static const List<FontFeature> _tabular = [FontFeature.tabularFigures()];

  Timer? _timer;
  DateTime _now = DateTime.now();

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() => _now = DateTime.now());
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final weather = widget.weather;

    return Container(
      height: 33,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(100),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.2),
          width: 0.8,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(weather?.emoji ?? '🌡️', style: const TextStyle(fontSize: 13)),
          const SizedBox(width: 6),
          Text(
            weather != null ? '${weather.temperatureC}°' : '--°',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.88),
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(width: 6),
          Text(
            weather?.condition ?? 'Loading…',
            style: AppTypography.style(color: Colors.white, fontSize: 11),
          ),
          const SizedBox(width: 12),
          Container(width: 1, height: 18, color: Colors.white),
          const SizedBox(width: 12),
          Text(
            DateFormat('h:mm a').format(_now),
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 14,
              fontWeight: FontWeight.w600,
              letterSpacing: 0.42,
            ).copyWith(fontFeatures: _tabular),
          ),
          const SizedBox(width: 10),
          Text(
            DateFormat('EEEE, MMM d').format(_now),
            style: AppTypography.style(color: Colors.white, fontSize: 11),
          ),
        ],
      ),
    );
  }
}
