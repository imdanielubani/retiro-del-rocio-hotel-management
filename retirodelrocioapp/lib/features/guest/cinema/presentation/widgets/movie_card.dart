import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/cinema/domain/cinema_service.dart';

/// A bookable movie poster tile: artwork with a classification badge and a
/// selection circle, then the title, genre/duration and the private room's
/// price. The gold ring + filled circle read as "selected", matching the
/// guest tablet's radio-selection convention (see SpaServiceCard).
class MovieCard extends StatelessWidget {
  const MovieCard({
    super.key,
    required this.movie,
    required this.selected,
    required this.onTap,
  });

  final Movie movie;
  final bool selected;
  final VoidCallback onTap;

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
            children: [_poster(), _info()],
          ),
        ),
      ),
    );
  }

  Widget _poster() {
    return SizedBox(
      height: 200,
      child: Stack(
        fit: StackFit.expand,
        children: [
          Image.network(
            movie.posterUrl,
            fit: BoxFit.cover,
            errorBuilder: (_, _, _) => _posterFallback(),
            frameBuilder: (context, child, frame, wasSynchronouslyLoaded) =>
                frame == null && !wasSynchronouslyLoaded
                ? _posterFallback()
                : child,
          ),
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
                color: movie.isComingSoon
                    ? Colors.white.withValues(alpha: 0.85)
                    : AppColors.gold,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                movie.classificationLabel.toUpperCase(),
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
              movie.title,
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

  Widget _posterFallback() {
    return DecoratedBox(
      decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.06)),
      child: const Center(
        child: Icon(Icons.movie_rounded, size: 40, color: Colors.white24),
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
          Text(
            [
              if ((movie.genre ?? '').isNotEmpty) movie.genre,
              if ((movie.duration ?? '').isNotEmpty) movie.duration,
            ].whereType<String>().join(' • '),
            maxLines: 1,
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
              Expanded(
                child: Row(
                  children: [
                    Icon(
                      Icons.meeting_room_rounded,
                      size: 11,
                      color: Colors.white.withValues(alpha: 0.4),
                    ),
                    const SizedBox(width: 6),
                    Flexible(
                      child: Text(
                        'Private Room',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTypography.style(
                          color: Colors.white.withValues(alpha: 0.4),
                          fontSize: 11,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 6),
              Text(
                movie.roomPriceLabel,
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
