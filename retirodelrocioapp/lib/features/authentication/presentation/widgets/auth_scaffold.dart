import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';

/// Shared shell for the auth flow screens: room image + 85% overlay (same as
/// device setup), a centered glass card, and an optional back button.
class AuthScaffold extends StatelessWidget {
  const AuthScaffold({
    super.key,
    required this.child,
    this.showBack = true,
    this.width = 460,
  });

  final Widget child;
  final bool showBack;
  final double width;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset('assets/images/12375.jpg', fit: BoxFit.cover),
          const ColoredBox(color: AppColors.scrim),
          Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(vertical: 40),
              child: Container(
                width: width,
                padding: const EdgeInsets.symmetric(horizontal: 44, vertical: 40),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.07),
                  borderRadius: BorderRadius.circular(28),
                  border: Border.all(color: Colors.white.withValues(alpha: 0.15), width: 0.8),
                ),
                child: child,
              ),
            ),
          ),
          if (showBack)
            Positioned(
              top: 40,
              left: 24,
              child: Material(
                color: Colors.white.withValues(alpha: 0.12),
                shape: const CircleBorder(),
                child: InkWell(
                  onTap: () => Navigator.of(context).maybePop(),
                  customBorder: const CircleBorder(),
                  child: const SizedBox(
                    width: 44,
                    height: 44,
                    child: Icon(Icons.arrow_back_rounded, color: Colors.white),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
