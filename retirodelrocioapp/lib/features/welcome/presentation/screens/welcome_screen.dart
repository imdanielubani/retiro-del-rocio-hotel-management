import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/media/ambient_video_background.dart';
import 'package:retirodelrocioapp/core/media/ambient_video_provider.dart';
import 'package:retirodelrocioapp/core/session/device_revocation.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/screens/staff_dashboard_screen.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/screens/staff_login_screen.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/device_setup/presentation/widgets/allocation_chip.dart';
import 'package:retirodelrocioapp/features/welcome/application/room_realtime_provider.dart';
import 'package:retirodelrocioapp/features/welcome/application/room_status_providers.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';
import 'package:retirodelrocioapp/features/welcome/presentation/widgets/guest_welcome_view.dart';
import 'package:video_player/video_player.dart';

/// 0.9 — Role / Welcome display (Figma node 75:3012).
///
/// The paired tablet's idle home: brand, allocation (suite+room or role),
/// live clock/date, and an Explore entry point. Uses the shared, always-playing
/// ambient video in the background with mute / play controls.
class WelcomeScreen extends ConsumerStatefulWidget {
  const WelcomeScreen({super.key, required this.device});

  final ProvisionedDevice device;

  @override
  ConsumerState<WelcomeScreen> createState() => _WelcomeScreenState();
}

class _WelcomeScreenState extends ConsumerState<WelcomeScreen> {
  /// True once the checked-in guest has tapped Explore. Reset automatically the
  /// moment the room stops being occupied (i.e. reception checks them out).
  bool _explored = false;

  /// Occupancy at the previous poll, so a check-out can be detected as an edge.
  bool _wasCheckedIn = false;

  void _toggleMute(VideoPlayerController video) {
    video.setVolume(video.value.volume == 0 ? 1 : 0);
  }

  void _togglePlay(VideoPlayerController video) {
    if (video.value.isPlaying) {
      video.pause();
    } else {
      video.play();
    }
  }

  void _explore() {
    final device = widget.device;
    if (device.isStaff) {
      // Already signed in → straight to the role dashboard; otherwise sign in.
      final session = ref.read(authControllerProvider).value;
      Navigator.of(context).push(
        MaterialPageRoute(
          settings: const RouteSettings(name: StaffLoginScreen.routeName),
          builder: (_) => session != null
              ? StaffDashboardScreen(session: session)
              : StaffLoginScreen(device: device),
        ),
      );
      return;
    }
    // Guest tablet: reveal the checked-in guest's welcome. Kept in place rather
    // than pushed, so a check-out simply drops back to this screen.
    setState(() => _explored = true);
  }

  @override
  Widget build(BuildContext context) {
    final device = widget.device;

    // Listen for check-in / check-out on this room, so the tablet reacts the
    // moment reception clicks. Held here — this screen stays mounted beneath the
    // guest's own screens — and it merely accelerates the poll below.
    ref.watch(roomRealtimeProvider(device));

    // A tablet deleted from the admin dashboard loses its token: forget the
    // pairing and go back to device setup rather than sit on a dead session.
    final statusAsync = ref.watch(roomStatusProvider(device.token));
    unpairIfRevoked(context, statusAsync);

    final status = statusAsync.value;
    final checkedIn =
        device.isGuest &&
        status != null &&
        status.isOccupied &&
        status.hasGuest;

    // Reception checking the guest out drops the tablet straight back to this
    // welcome screen — no one has to touch it. The guest may have pushed their
    // home dashboard (and screens above it) on top of us, so tear those down:
    // this screen is the authority on whether anyone is still checked in.
    if (!checkedIn) _explored = false;
    if (device.isGuest && _wasCheckedIn && !checkedIn) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) Navigator.of(context).popUntil((route) => route.isFirst);
      });
    }
    _wasCheckedIn = checkedIn;

    // The guest's own screens are behind Explore: on check-in this screen just
    // reports the room as occupied and offers the way in.
    if (checkedIn && _explored) {
      return GuestWelcomeView(device: device, status: status);
    }

    final video = ref.watch(ambientVideoProvider).value;
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
          // Figma 75:3012 — mute + play sit together in the top-right corner.
          Positioned(right: 90, top: 49, child: _mediaControls(video)),
          Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(vertical: 150),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Image.asset(
                    'assets/images/Rocio Logo Icon 1.png',
                    width: 74,
                    height: 38,
                    fit: BoxFit.contain,
                  ),
                  const SizedBox(height: 25),
                  Text(
                    'WELCOME TO',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.45),
                      fontSize: 13,
                      letterSpacing: 2.6,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    'RETIRO DEL ROCIO',
                    style: AppTypography.style(
                      color: AppColors.gold,
                      fontSize: 44,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 6.24,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    'Where Luxury Meets Serenity',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.3),
                      fontSize: 16,
                      letterSpacing: 0.96,
                    ),
                  ),
                  const SizedBox(height: 22),
                  AllocationChip(device: device),
                  const SizedBox(height: 18),
                  if (device.isGuest) _guestAvailability(status, checkedIn),
                  const SizedBox(height: 30),
                  _LiveInfoRow(weather: ref.watch(weatherProvider).value),
                  // A guest tablet only offers a way in once reception has
                  // checked someone in; an empty room shows no button at all.
                  if (device.isStaff || checkedIn) ...[
                    const SizedBox(height: 34),
                    _exploreButton(),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// The mute + play controls, sitting side by side (Figma 75:3012 / 194:28).
  Widget _mediaControls(VideoPlayerController? video) {
    Widget controls(bool muted, bool playing) => Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        _circleControl(
          muted ? Icons.volume_off_rounded : Icons.volume_up_rounded,
          video == null ? () {} : () => _toggleMute(video),
        ),
        const SizedBox(width: 12),
        _pillControl(
          playing ? Icons.pause_rounded : Icons.play_arrow_rounded,
          playing ? 'Pause' : 'Play',
          video == null ? () {} : () => _togglePlay(video),
        ),
      ],
    );

    if (video == null) return controls(true, true);
    return AnimatedBuilder(
      animation: video,
      builder: (context, _) =>
          controls(video.value.volume == 0, video.value.isPlaying),
    );
  }

  Widget _circleControl(IconData icon, VoidCallback onTap) {
    return Material(
      color: Colors.white.withValues(alpha: 0.12),
      shape: CircleBorder(
        side: BorderSide(color: Colors.white.withValues(alpha: 0.2), width: 0.8),
      ),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 40,
          height: 40,
          child: Icon(icon, size: 16, color: Colors.white),
        ),
      ),
    );
  }

  Widget _pillControl(IconData icon, String label, VoidCallback onTap) {
    return Material(
      color: Colors.white.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 17, vertical: 9),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.2),
              width: 0.8,
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 14, color: Colors.white),
              const SizedBox(width: 8),
              Text(
                label,
                style: AppTypography.style(
                  color: Colors.white,
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

  Widget _guestAvailability(RoomStatus? status, bool checkedIn) {
    final occupied = status?.isOccupied ?? false;
    final color = occupied ? const Color(0xFFF87171) : AppColors.success;

    return Column(
      children: [
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 10,
              height: 10,
              decoration: BoxDecoration(color: color, shape: BoxShape.circle),
            ),
            const SizedBox(width: 8),
            Text(
              occupied
                  ? 'This room is currently occupied.'
                  : 'This room is currently available.',
              style: AppTypography.style(
                color: color,
                fontSize: 14,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
        const SizedBox(height: 5),

        // The guest is checked in — point them at Explore instead of Reception.
        if (checkedIn)
          Text(
            'Welcome, ${status!.guest!.name.split(' ').first}.\n'
            'Tap Explore to open your suite.',
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.42),
              fontSize: 14,
              height: 1.8,
            ),
          )
        else
          Text.rich(
            TextSpan(
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.42),
                fontSize: 14,
                height: 1.8,
              ),
              children: [
                const TextSpan(
                  text:
                      'If you have a reservation, please\ncomplete your check-in at ',
                ),
                TextSpan(
                  text: 'Reception.',
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.7),
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
            textAlign: TextAlign.center,
          ),
      ],
    );
  }

  Widget _exploreButton() {
    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: _explore,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 40, vertical: 16),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                widget.device.isStaff ? 'Sign In' : 'Explore',
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
}

/// The clock + date + weather row. Owns its own 1-second ticker so **only this
/// small widget** rebuilds each second — the rest of the welcome screen stays
/// put (no layout jitter). Tabular figures keep the digits a fixed width.
class _LiveInfoRow extends StatefulWidget {
  const _LiveInfoRow({required this.weather});

  final Weather? weather;

  @override
  State<_LiveInfoRow> createState() => _LiveInfoRowState();
}

class _LiveInfoRowState extends State<_LiveInfoRow> {
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
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        _clockCard(),
        _dot(),
        Text(
          DateFormat('EEEE, MMMM d, y').format(_now),
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.5),
            fontSize: 13,
          ),
        ),
        _dot(),
        _weatherCard(widget.weather),
      ],
    );
  }

  Widget _dot() => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 12),
    child: Text(
      '·',
      style: AppTypography.style(
        color: Colors.white.withValues(alpha: 0.2),
        fontSize: 20,
      ),
    ),
  );

  Widget _clockCard() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 21, vertical: 13),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.1),
          width: 0.8,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
            DateFormat('HH:mm').format(_now),
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 32,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.28,
            ).copyWith(fontFeatures: _tabular),
          ),
          const SizedBox(width: 6),
          Padding(
            padding: const EdgeInsets.only(bottom: 3),
            child: Text(
              DateFormat('ss').format(_now),
              style: AppTypography.style(
                color: AppColors.goldAccent,
                fontSize: 16,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.64,
              ).copyWith(fontFeatures: _tabular),
            ),
          ),
          const SizedBox(width: 4),
          Padding(
            padding: const EdgeInsets.only(bottom: 4),
            child: Text(
              DateFormat('a').format(_now),
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.35),
                fontSize: 13,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _weatherCard(Weather? weather) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 21, vertical: 13),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.1),
          width: 0.8,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(weather?.emoji ?? '🌡️', style: const TextStyle(fontSize: 26)),
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                weather?.temperatureLabel ?? '--°C',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                ),
              ),
              Text(
                weather?.subtitle ?? 'Loading…',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 11,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
