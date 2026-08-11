import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/spa/domain/spa_service.dart';

/// A bookable spa service tile (Figma 162:4569): a photo with the service's
/// real admin-managed category badge and a selection circle, then the name,
/// description, duration and price. The gold ring + filled circle read as
/// "selected", matching the guest tablet's radio-selection convention.
class SpaServiceCard extends StatelessWidget {
  const SpaServiceCard({
    super.key,
    required this.service,
    required this.selected,
    required this.onTap,
  });

  final SpaService service;
  final bool selected;
  final VoidCallback onTap;

  /// Parses the admin's `#rrggbb` category colour; falls back to the design's
  /// gold if the value is missing or malformed.
  Color get _categoryColor {
    final hex = service.categoryColor.replaceFirst('#', '');
    if (hex.length != 6) return const Color(0xFFC9A227);
    final value = int.tryParse(hex, radix: 16);
    return value == null ? const Color(0xFFC9A227) : Color(0xFF000000 | value);
  }

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(24),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(24),
        child: Container(
          clipBehavior: Clip.antiAlias,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(24),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.6)
                  : Colors.white.withValues(alpha: 0.07),
              width: selected ? 1.6 : 1.2,
            ),
            boxShadow: const [
              BoxShadow(
                color: Color(0x4D000000),
                blurRadius: 16,
                offset: Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [_image(), _info()],
          ),
        ),
      ),
    );
  }

  Widget _image() {
    final url = service.imageUrl;
    return SizedBox(
      height: 148,
      child: Stack(
        fit: StackFit.expand,
        children: [
          url != null
              ? Image.network(
                  url,
                  fit: BoxFit.cover,
                  errorBuilder: (_, _, _) => _imageFallback(),
                  frameBuilder:
                      (context, child, frame, wasSynchronouslyLoaded) =>
                          frame == null && !wasSynchronouslyLoaded
                          ? _imageFallback()
                          : child,
                )
              : _imageFallback(),
          DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.bottomCenter,
                end: Alignment.topCenter,
                colors: [
                  Colors.black.withValues(alpha: 0.9),
                  Colors.black.withValues(alpha: 0.05),
                ],
                stops: const [0.0, 0.65],
              ),
            ),
          ),
          Positioned(
            left: 12,
            top: 12,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(
                color: _categoryColor,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                (service.category ?? 'Spa').toUpperCase(),
                style: AppTypography.style(
                  color: Colors.black,
                  fontSize: 9,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.9,
                ),
              ),
            ),
          ),
          Positioned(right: 10, top: 10, child: _selectionCircle()),
          Positioned(
            left: 14,
            bottom: 12,
            right: 14,
            child: Text(
              service.name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppTypography.style(
                color: Colors.white,
                fontSize: 15,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _imageFallback() {
    return DecoratedBox(
      decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.06)),
      child: const Center(
        child: Icon(Icons.spa_rounded, size: 40, color: Colors.white24),
      ),
    );
  }

  Widget _selectionCircle() {
    return Container(
      width: 24,
      height: 24,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: selected ? AppColors.gold : Colors.white.withValues(alpha: 0.15),
        shape: BoxShape.circle,
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.8),
          width: 1.2,
        ),
      ),
      child: selected
          ? const Icon(Icons.check_rounded, size: 15, color: Colors.black)
          : null,
    );
  }

  Widget _info() {
    return Container(
      color: const Color(0xFF1A211A),
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if ((service.description ?? '').isNotEmpty)
            Text(
              service.description!,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.45),
                fontSize: 12,
                height: 1.4,
              ),
            ),
          const SizedBox(height: 8),
          Row(
            children: [
              if (service.durationLabel != null) ...[
                Icon(
                  Icons.schedule_rounded,
                  size: 11,
                  color: Colors.white.withValues(alpha: 0.4),
                ),
                const SizedBox(width: 6),
                Text(
                  service.durationLabel!,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.4),
                    fontSize: 11,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
              const Spacer(),
              Text(
                service.priceLabel,
                style: AppTypography.style(
                  color: AppColors.gold,
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
