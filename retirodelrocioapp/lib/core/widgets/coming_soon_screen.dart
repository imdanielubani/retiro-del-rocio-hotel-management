import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';

/// Simple placeholder for screens that aren't built yet (guest home, etc.).
class ComingSoonScreen extends StatelessWidget {
  const ComingSoonScreen({super.key, required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        foregroundColor: Colors.white,
        title: Text(title, style: AppTypography.style(color: Colors.white, fontSize: 18)),
      ),
      body: Center(
        child: Text(
          '$title — coming soon',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.6),
            fontSize: 18,
          ),
        ),
      ),
    );
  }
}
