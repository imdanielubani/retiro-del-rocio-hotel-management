import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:retirodelrocioapp/app/app_router.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/core/widgets/blend_mask.dart';


class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _fade;
  late final Animation<double> _progress;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2600),
    );
    _fade = CurvedAnimation(
      parent: _controller,
      curve: const Interval(0.0, 0.32, curve: Curves.easeOut),
    );
    _progress = CurvedAnimation(
      parent: _controller,
      curve: const Interval(0.15, 1.0, curve: Curves.easeInOut),
    );
    _controller
      ..addStatusListener(_onLoadingComplete)
      ..forward();
  }

  void _onLoadingComplete(AnimationStatus status) {
    if (status != AnimationStatus.completed || !mounted) return;
    context.go(Routes.onboarding);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // Room photograph backdrop.
          Image.asset('assets/images/12375.jpg', fit: BoxFit.cover),
          // 85% black overlay over the imagery.
          const ColoredBox(color: AppColors.scrim),
          // Warm gold glow covering the frame; its black areas blend away.
          BlendMask(
            blendMode: BlendMode.screen,
            child: Image.asset('assets/images/bg-light.png', fit: BoxFit.cover),
          ),
          // Brand content.
          Center(
            child: FadeTransition(
              opacity: _fade,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  _logo(),
                  const SizedBox(height: 10),
                  Text(
                    'RETIRO DEL ROCIO',
                    textAlign: TextAlign.center,
                    style: AppTypography.style(
                      color: AppColors.gold,
                      fontSize: 52,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 6.24,
                      height: 1.5,
                    ),
                  ),
                  const SizedBox(height: 15),
                  Text(
                    'HOTEL & RESORT',
                    textAlign: TextAlign.center,
                    style: AppTypography.style(
                      color: AppColors.textMuted,
                      fontSize: 12,
                      letterSpacing: 3.6,
                      height: 1.5,
                    ),
                  ),
                  const SizedBox(height: 15),
                  Text(
                    'Where Luxury Meets Serenity',
                    textAlign: TextAlign.center,
                    style: AppTypography.style(
                      color: AppColors.textFaint,
                      fontSize: 16,
                      letterSpacing: 0.96,
                      height: 1.125,
                    ),
                  ),
                  const SizedBox(height: 79),
                  _loader(),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _logo() {
    return Container(
      width: 100,
      height: 100,
      decoration: const BoxDecoration(shape: BoxShape.circle),
      child: Image.asset(
        'assets/images/Rocio Logo Icon 1.png',
        fit: BoxFit.contain,
      ),
    );
  }

  Widget _loader() {
    return SizedBox(
      width: 256,
      child: Column(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: Container(
              height: 5,
              color: Colors.white.withValues(alpha: 0.1),
              alignment: Alignment.centerLeft,
              child: AnimatedBuilder(
                animation: _progress,
                builder: (context, _) => FractionallySizedBox(
                  widthFactor: 0.08 + (_progress.value * 0.92),
                  child: Container(
                    decoration: BoxDecoration(
                      color: AppColors.gold,
                      borderRadius: BorderRadius.circular(999),
                    ),
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 13),
          Text(
            'INITIALIZING EXPERIENCE',
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: AppColors.textFaint,
              fontSize: 11,
              letterSpacing: 1.65,
              height: 1.5,
            ),
          ),
        ],
      ),
    );
  }
}
