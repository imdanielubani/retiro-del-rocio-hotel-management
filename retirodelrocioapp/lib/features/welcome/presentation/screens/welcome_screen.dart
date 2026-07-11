import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/media/ambient_video_background.dart';
import 'package:retirodelrocioapp/core/media/ambient_video_provider.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/device_setup/presentation/widgets/allocation_chip.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';
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
  Timer? _clock;
  DateTime _now = DateTime.now();

  @override
  void initState() {
    super.initState();
    _clock = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() => _now = DateTime.now());
    });
  }

  @override
  void dispose() {
    _clock?.cancel();
    super.dispose();
  }

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
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => _ComingSoonScreen(device: widget.device)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final device = widget.device;
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
          const ColoredBox(color: Color(0x99000000)),
          Positioned(left: 64, right: 64, top: 64, child: _mediaBar(video)),
          Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(vertical: 150),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Image.asset('assets/images/Rocio Logo Icon 1.png',
                      width: 74, height: 38, fit: BoxFit.contain),
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
                  if (device.isGuest) _guestAvailability(),
                  const SizedBox(height: 30),
                  _infoRow(ref.watch(weatherProvider).value),
                  const SizedBox(height: 34),
                  _exploreButton(),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _mediaBar(VideoPlayerController? video) {
    Widget bar(bool muted, bool playing) => Container(
          height: 63,
          padding: const EdgeInsets.symmetric(horizontal: 12),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(100),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              _circleControl(
                muted ? Icons.volume_off_rounded : Icons.volume_up_rounded,
                video == null ? () {} : () => _toggleMute(video),
              ),
              _pillControl(
                playing ? Icons.pause_rounded : Icons.play_arrow_rounded,
                playing ? 'Pause' : 'Play',
                video == null ? () {} : () => _togglePlay(video),
              ),
            ],
          ),
        );

    if (video == null) return bar(true, true);
    return AnimatedBuilder(
      animation: video,
      builder: (context, _) => bar(video.value.volume == 0, video.value.isPlaying),
    );
  }

  Widget _circleControl(IconData icon, VoidCallback onTap) {
    return Material(
      color: Colors.white.withValues(alpha: 0.12),
      shape: const CircleBorder(),
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
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 17, vertical: 9),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 14, color: Colors.white),
              const SizedBox(width: 8),
              Text(label,
                  style: AppTypography.style(
                      color: Colors.white, fontSize: 13, fontWeight: FontWeight.w500)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _guestAvailability() {
    return Column(
      children: [
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 10,
              height: 10,
              decoration: const BoxDecoration(
                  color: AppColors.success, shape: BoxShape.circle),
            ),
            const SizedBox(width: 8),
            Text(
              'This room is currently available.',
              style: AppTypography.style(
                color: AppColors.success,
                fontSize: 14,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
        const SizedBox(height: 5),
        Text.rich(
          TextSpan(
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.42),
              fontSize: 14,
              height: 1.8,
            ),
            children: [
              const TextSpan(
                  text: 'If you have a reservation, please\ncomplete your check-in at '),
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

  Widget _infoRow(Weather? weather) {
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
        _weatherCard(weather),
      ],
    );
  }

  Widget _dot() => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12),
        child: Text('·',
            style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.2), fontSize: 20)),
      );

  Widget _clockCard() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 21, vertical: 13),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.white.withValues(alpha: 0.1), width: 0.8),
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
            ),
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
              ),
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
        border: Border.all(color: Colors.white.withValues(alpha: 0.1), width: 0.8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(weather?.emoji ?? '🌡️', style: const TextStyle(fontSize: 26)),
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(weather?.temperatureLabel ?? '--°C',
                  style: AppTypography.style(
                      color: Colors.white, fontSize: 20, fontWeight: FontWeight.w700)),
              Text(weather?.subtitle ?? 'Loading…',
                  style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.4), fontSize: 11)),
            ],
          ),
        ],
      ),
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
              const Icon(Icons.arrow_forward_rounded, color: AppColors.onGold, size: 20),
            ],
          ),
        ),
      ),
    );
  }
}

/// Temporary landing for Explore / Sign In — the guest home & staff login
/// screens replace this next.
class _ComingSoonScreen extends StatelessWidget {
  const _ComingSoonScreen({required this.device});

  final ProvisionedDevice device;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        foregroundColor: Colors.white,
      ),
      body: Center(
        child: Text(
          device.isStaff ? 'Staff Sign In — coming next' : 'Guest Home — coming next',
          style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.6), fontSize: 18),
        ),
      ),
    );
  }
}
