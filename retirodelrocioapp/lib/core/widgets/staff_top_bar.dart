import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/glass_panel.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';

/// The shared frosted header every staff tablet opens on: brand + station
/// label, the signed-in staffer, the live weather/clock pill, a
/// notifications bell and their avatar.
///
/// Reception and security each grew their own copy of this bar before it was
/// worth sharing; housekeeping and maintenance are built directly on this
/// one, parameterised by [brandLabel] and [initialsFallback], so all four
/// tablets stay visually identical without a third and fourth near-duplicate
/// file.
class StaffTopBar extends StatelessWidget {
  const StaffTopBar({
    super.key,
    required this.name,
    required this.role,
    required this.brandLabel,
    required this.weather,
    this.hasAlert = false,
    this.hasUnreadNotifications = false,
    this.onNotifications,
    this.initialsFallback = 'ST',
  });

  final String name;
  final String role;

  /// e.g. "HOUSEKEEPING", "MAINTENANCE" — printed under the hotel wordmark.
  final String brandLabel;
  final Weather? weather;

  /// A live incident/urgent item lights the bell's dot red — outranks a
  /// plain unread notification, which is never an emergency.
  final bool hasAlert;

  /// An unread notification lights the bell's dot gold when there's no
  /// higher-priority alert to outrank it.
  final bool hasUnreadNotifications;
  final VoidCallback? onNotifications;

  /// Shown on the avatar when [name] doesn't yield any initials.
  final String initialsFallback;

  String get _initials {
    final parts = name.trim().split(RegExp(r'\s+'));
    final letters = parts.where((p) => p.isNotEmpty).take(2).map((p) => p[0]);
    final joined = letters.join().toUpperCase();
    return joined.isEmpty ? initialsFallback : joined;
  }

  @override
  Widget build(BuildContext context) {
    return GlassPanel(
      height: 63,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Row(
        children: [
          _logo(),
          const SizedBox(width: 10),
          _brand(),
          _divider(),
          Expanded(child: _identity()),
          const SizedBox(width: 16),
          _WeatherClockPill(weather: weather),
          const SizedBox(width: 15),
          _bell(),
          const SizedBox(width: 15),
          _avatar(),
        ],
      ),
    );
  }

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
          brandLabel,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 10,
            letterSpacing: 0.6,
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
          role,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.45),
            fontSize: 11,
          ),
        ),
        Text(
          name,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: AppTypography.style(color: Colors.white, fontSize: 15),
        ),
      ],
    );
  }

  Widget _bell() {
    final showDot = hasAlert || hasUnreadNotifications;
    final dot = hasAlert ? const Color(0xFFFF0000) : AppColors.gold;
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
          if (showDot)
            Positioned(
              right: 6,
              top: 5,
              child: SizedBox(
                width: 6,
                height: 6,
                child: DecoratedBox(
                  decoration: BoxDecoration(color: dot, shape: BoxShape.circle),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _avatar() {
    return Container(
      width: 35,
      height: 35,
      decoration: const BoxDecoration(
        color: AppColors.gold,
        shape: BoxShape.circle,
      ),
      alignment: Alignment.center,
      child: Text(
        _initials,
        style: AppTypography.style(
          color: Colors.black,
          fontSize: 13,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

/// Weather + clock. Owns its own 1-second ticker so only this pill rebuilds
/// each second; tabular figures keep the digits from shifting the layout.
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
