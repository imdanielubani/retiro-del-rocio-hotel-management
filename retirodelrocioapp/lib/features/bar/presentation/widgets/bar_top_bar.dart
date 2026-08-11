import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/glass_panel.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';

/// The Bar Tablet's header — a structural match of [ReceptionTopBar]: the
/// same frosted [GlassPanel], logo + brand lockup, signed-in staffer
/// identity, live weather/clock pill, notification bell and avatar.
class BarTopBar extends StatelessWidget {
  const BarTopBar({
    super.key,
    required this.name,
    required this.role,
    required this.weather,
    this.hasAlert = false,
    this.hasUnreadNotifications = false,
    this.onNotifications,
    this.onChat,
    this.onIntercom,
    this.hasUnreadChat = false,
  });

  final String name;
  final String role;
  final Weather? weather;

  /// A live new-order alert lights the bell's dot red — outranks a plain
  /// unread notification.
  final bool hasAlert;
  final bool hasUnreadNotifications;
  final VoidCallback? onNotifications;

  /// Opens Staff Chat / Intercom. Left null where a screen hasn't wired
  /// them up, in which case the icon is inert.
  final VoidCallback? onChat;
  final VoidCallback? onIntercom;

  /// Lights the Chat icon's dot gold, the same way an unread notification
  /// lights the bell's.
  final bool hasUnreadChat;

  String get _initials {
    final parts = name.trim().split(RegExp(r'\s+'));
    final letters = parts.where((p) => p.isNotEmpty).take(2).map((p) => p[0]);
    final joined = letters.join().toUpperCase();
    return joined.isEmpty ? 'BR' : joined;
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
          _iconButton(
            Icons.chat_bubble_outline_rounded,
            onChat,
            showDot: hasUnreadChat,
          ),
          const SizedBox(width: 10),
          _iconButton(Icons.call_outlined, onIntercom),
          const SizedBox(width: 10),
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
          'BAR',
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

  Widget _iconButton(
    IconData icon,
    VoidCallback? onTap, {
    bool showDot = false,
  }) {
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
              onTap: onTap,
              customBorder: const CircleBorder(),
              child: Center(child: Icon(icon, size: 16, color: Colors.white)),
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
