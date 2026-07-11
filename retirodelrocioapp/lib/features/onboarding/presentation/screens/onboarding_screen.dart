import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:retirodelrocioapp/app/app_router.dart';
import 'package:retirodelrocioapp/core/media/ambient_video_background.dart';
import 'package:retirodelrocioapp/core/media/ambient_video_provider.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/onboarding/domain/onboarding_slide.dart';
import 'package:video_player/video_player.dart';

/// 0.2–0.4 — Onboarding (Figma nodes 62:1820 → 66:2629).
///
/// A swipeable four-slide carousel over the shared, always-playing ambient
/// hotel video. Falls back to a dark gradient when the video is unavailable.
class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  static const double _margin = 64;

  final PageController _pageController = PageController();
  int _index = 0;

  List<OnboardingSlide> get _slides => OnboardingSlide.all;
  bool get _isLast => _index == _slides.length - 1;

  @override
  void dispose() {
    _pageController.dispose();
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

  void _next() {
    if (_isLast) {
      _finish();
      return;
    }
    _pageController.nextPage(
      duration: const Duration(milliseconds: 450),
      curve: Curves.easeInOut,
    );
  }

  void _prev() {
    if (_index == 0) return;
    _pageController.previousPage(
      duration: const Duration(milliseconds: 450),
      curve: Curves.easeInOut,
    );
  }

  void _finish() {
    context.go(Routes.setup);
  }

  @override
  Widget build(BuildContext context) {
    final video = ref.watch(ambientVideoProvider).value;
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          AmbientVideoBackground(controller: video),
          _scrim(),
          PageView.builder(
            controller: _pageController,
            itemCount: _slides.length,
            onPageChanged: (i) => setState(() => _index = i),
            itemBuilder: (context, i) => _SlideContent(slide: _slides[i]),
          ),
          _header(),
          _topControls(video),
          _dots(),
          _nav(),
        ],
      ),
    );
  }

  Widget _scrim() => const DecoratedBox(
    decoration: BoxDecoration(
      gradient: LinearGradient(
        begin: Alignment.centerLeft,
        end: Alignment.centerRight,
        colors: [Color(0xE6000000), Color(0x66000000)],
        stops: [0.0, 0.9],
      ),
    ),
  );

  // --- Fixed chrome ------------------------------------------------------

  Widget _header() {
    return Positioned(
      left: _margin,
      top: 64,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Image.asset(
            'assets/images/Rocio Logo Icon 1.png',
            width: 74,
            height: 38,
            fit: BoxFit.contain,
          ),
          const SizedBox(width: 10),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
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
              const SizedBox(height: 5),
              Text(
                'WHERE STILLNESS FINDS YOU',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 10,
                  letterSpacing: 1.8,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _topControls(VideoPlayerController? video) {
    Widget controls(bool muted, bool playing) => Row(
      children: [
        _CircleButton(
          icon: muted ? Icons.volume_off_rounded : Icons.volume_up_rounded,
          onTap: video == null ? () {} : () => _toggleMute(video),
        ),
        const SizedBox(width: 12),
        _PillButton(
          icon: playing ? Icons.pause_rounded : Icons.play_arrow_rounded,
          label: playing ? 'Pause' : 'Play',
          onTap: video == null ? () {} : () => _togglePlay(video),
        ),
        const SizedBox(width: 12),
        _SkipButton(onTap: _finish),
      ],
    );

    return Positioned(
      right: _margin,
      top: 64,
      child: video == null
          ? controls(true, true)
          : AnimatedBuilder(
              animation: video,
              builder: (context, _) =>
                  controls(video.value.volume == 0, video.value.isPlaying),
            ),
    );
  }

  Widget _dots() {
    return Positioned(
      left: _margin,
      bottom: 64,
      child: Row(
        children: List.generate(_slides.length, (i) {
          final active = i == _index;
          return AnimatedContainer(
            duration: const Duration(milliseconds: 250),
            margin: const EdgeInsets.only(right: 8),
            width: active ? 28 : 8,
            height: 8,
            decoration: BoxDecoration(
              color: active
                  ? AppColors.gold
                  : Colors.white.withValues(alpha: 0.25),
              borderRadius: BorderRadius.circular(4),
            ),
          );
        }),
      ),
    );
  }

  Widget _nav() {
    return Positioned(
      right: _margin,
      bottom: 56,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (_index > 0) ...[
            _CircleButton(
              icon: Icons.chevron_left_rounded,
              size: 56,
              onTap: _prev,
            ),
            const SizedBox(width: 9),
          ],
          if (_index == 0)
            _GoldCircleButton(icon: Icons.chevron_right_rounded, onTap: _next)
          else
            _GoldPillButton(
              label: _isLast ? 'Get Started' : 'Next',
              onTap: _next,
            ),
        ],
      ),
    );
  }
}

// --- Slide content -------------------------------------------------------

class _SlideContent extends StatelessWidget {
  const _SlideContent({required this.slide});

  final OnboardingSlide slide;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerLeft,
      child: Padding(
        padding: const EdgeInsets.only(left: 64, right: 40),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(width: 32, height: 1, color: AppColors.gold),
                const SizedBox(width: 12),
                Text(
                  slide.eyebrow,
                  style: AppTypography.style(
                    color: AppColors.gold,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 2.75,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 28),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(slide.titleTop, style: _titleStyle(Colors.white)),
                  const SizedBox(height: 18),
                  Text(slide.titleBottom, style: _titleStyle(AppColors.gold)),
                ],
              ),
            ),
            const SizedBox(height: 30),
            SizedBox(
              width: 520,
              child: Text(
                slide.description,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.6),
                  fontSize: 14,
                  height: 1.51,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  TextStyle _titleStyle(Color color) => AppTypography.style(
    color: color,
    fontSize: 88,
    fontWeight: FontWeight.w700,
    height: 0.9,
  );
}

// --- Reusable buttons ----------------------------------------------------

class _CircleButton extends StatelessWidget {
  const _CircleButton({
    required this.icon,
    required this.onTap,
    this.size = 40,
  });

  final IconData icon;
  final VoidCallback onTap;
  final double size;

  @override
  Widget build(BuildContext context) {
    return _Tappable(
      onTap: onTap,
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.12),
          shape: BoxShape.circle,
          border: Border.all(
            color: Colors.white.withValues(alpha: 0.2),
            width: 0.8,
          ),
        ),
        child: Icon(icon, size: size * 0.42, color: Colors.white),
      ),
    );
  }
}

class _PillButton extends StatelessWidget {
  const _PillButton({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return _Tappable(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 17, vertical: 9),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(
            color: Colors.white.withValues(alpha: 0.2),
            width: 0.8,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 16, color: Colors.white),
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
    );
  }
}

class _SkipButton extends StatelessWidget {
  const _SkipButton({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return _Tappable(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 21, vertical: 9),
        decoration: BoxDecoration(
          color: AppColors.goldAccent.withValues(alpha: 0.15),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(
            color: AppColors.goldAccent.withValues(alpha: 0.35),
            width: 0.8,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Skip',
              style: AppTypography.style(
                color: AppColors.goldAccent,
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(width: 8),
            const Icon(
              Icons.chevron_right_rounded,
              size: 16,
              color: AppColors.goldAccent,
            ),
          ],
        ),
      ),
    );
  }
}

class _GoldCircleButton extends StatelessWidget {
  const _GoldCircleButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return _Tappable(
      onTap: onTap,
      child: Container(
        width: 56,
        height: 56,
        decoration: const BoxDecoration(
          color: AppColors.gold,
          shape: BoxShape.circle,
          boxShadow: [
            BoxShadow(
              color: Color(0x26000000),
              blurRadius: 5,
              offset: Offset(0, 8),
            ),
          ],
        ),
        child: Icon(icon, size: 26, color: AppColors.onGold),
      ),
    );
  }
}

class _GoldPillButton extends StatelessWidget {
  const _GoldPillButton({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return _Tappable(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 18),
        decoration: BoxDecoration(
          color: AppColors.gold,
          borderRadius: BorderRadius.circular(50),
          boxShadow: const [
            BoxShadow(
              color: Color(0x26000000),
              blurRadius: 5,
              offset: Offset(0, 8),
            ),
          ],
        ),
        child: Text(
          label,
          style: AppTypography.style(
            color: AppColors.onGold,
            fontSize: 16,
            fontWeight: FontWeight.w500,
            letterSpacing: -0.2,
          ),
        ),
      ),
    );
  }
}

/// A minimal tap wrapper with a rounded ripple that fits the pill/circle shapes.
class _Tappable extends StatelessWidget {
  const _Tappable({required this.child, required this.onTap});

  final Widget child;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: child,
      ),
    );
  }
}
