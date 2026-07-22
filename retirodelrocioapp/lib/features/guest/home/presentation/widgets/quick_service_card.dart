import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/home/domain/guest_service.dart';

/// One "Quick Services" tile: gold icon badge, title and tagline on a frosted
/// card — 280 × 137, radius 24 (Figma 84:3429).
class QuickServiceCard extends StatelessWidget {
  const QuickServiceCard({
    super.key,
    required this.service,
    required this.onTap,
  });

  final GuestService service;
  final VoidCallback onTap;

  static const double height = 137;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(24);
    final asset = service.iconAsset;

    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      borderRadius: radius,
      child: InkWell(
        onTap: onTap,
        borderRadius: radius,
        child: Container(
          height: height,
          padding: const EdgeInsets.symmetric(horizontal: 21.8, vertical: 12),
          decoration: BoxDecoration(
            borderRadius: radius,
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          // The card's height is fixed by the design, so the labels are Flexible:
          // if a device's font metrics run tall they shrink rather than overflow.
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(
                  color: AppColors.gold.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Center(
                  child: asset != null
                      ? Image.asset(
                          asset,
                          width: 22,
                          height: 22,
                          fit: BoxFit.contain,
                        )
                      : Icon(service.icon, size: 22, color: AppColors.gold),
                ),
              ),
              const SizedBox(height: 10),
              Flexible(
                child: Text(
                  service.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    height: 21 / 20,
                  ),
                ),
              ),
              const SizedBox(height: 7),
              Flexible(
                child: Text(
                  service.tagline,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.4),
                    fontSize: 15,
                    fontWeight: FontWeight.w500,
                    height: 16.5 / 16,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
