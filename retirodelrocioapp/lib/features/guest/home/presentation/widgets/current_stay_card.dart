import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// "CURRENT STAY" — suite photo, room, dates, a nights progress strip and the
/// Extend Stay action (Figma 84:3429 / node 85:3730).
class CurrentStayCard extends StatelessWidget {
  const CurrentStayCard({
    super.key,
    required this.status,
    required this.roomImageUrl,
    required this.onExtendStay,
  });

  final RoomStatus status;

  /// The room's featured photo, carried on the paired device (fetched once at
  /// launch) rather than re-sent on every room-status poll.
  final String? roomImageUrl;

  final VoidCallback onExtendStay;

  GuestInfo get _guest => status.guest!;

  int get _nights {
    final booked = _guest.nights;
    if (booked != null && booked > 0) return booked;

    final from = _guest.checkIn;
    final to = _guest.checkOut;
    if (from == null || to == null) return 0;
    final nights = DateTime(
      to.year,
      to.month,
      to.day,
    ).difference(DateTime(from.year, from.month, from.day)).inDays;
    return nights > 0 ? nights : 0;
  }

  /// Nights already spent, used to fill the progress dots.
  int get _nightsElapsed {
    final from = _guest.checkIn;
    if (from == null) return 0;
    final now = DateTime.now();
    final elapsed = DateTime(
      now.year,
      now.month,
      now.day,
    ).difference(DateTime(from.year, from.month, from.day)).inDays;
    return elapsed.clamp(0, _nights);
  }

  String get _stayRange {
    final from = _guest.checkIn;
    final to = _guest.checkOut;
    if (from == null || to == null) return 'Dates pending';
    final nights = _nights;
    return '${DateFormat('MMM d').format(from)} – '
        '${DateFormat('MMM d, y').format(to)} • '
        '$nights ${nights == 1 ? 'Night' : 'Nights'}';
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 129,
      padding: const EdgeInsets.symmetric(horizontal: 24),
      decoration: BoxDecoration(
        color: const Color(0xFF1B1D1A),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppColors.gold.withValues(alpha: 0.25)),
      ),
      child: Row(
        children: [
          Container(
            width: 97,
            height: 97,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: AppColors.gold.withValues(alpha: 0.3),
                width: 0.8,
              ),
            ),
            clipBehavior: Clip.antiAlias,
            child: _roomPhoto(),
          ),
          const SizedBox(width: 21),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'CURRENT STAY',
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.4),
                    fontSize: 11,
                    letterSpacing: 1.1,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${status.suiteName ?? 'Suite'} • Room ${status.roomNumber ?? '—'}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _stayRange,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.5),
                    fontSize: 13,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 24),
          _dateColumn('CHECK-IN', _guest.checkIn, AppColors.gold),
          const SizedBox(width: 32),
          _nightsStrip(),
          const SizedBox(width: 32),
          _dateColumn('CHECK-OUT', _guest.checkOut, Colors.white),
          const SizedBox(width: 32),
          _extendButton(),
        ],
      ),
    );
  }

  /// The room's own photo, as set in the admin dashboard. Falls back to the
  /// bundled suite image while it loads, or if the tablet is offline.
  Widget _roomPhoto() {
    const fallback = Image(
      image: AssetImage('assets/images/2724.jpg'),
      fit: BoxFit.cover,
      width: 97,
      height: 97,
    );

    final url = roomImageUrl;
    if (url == null) return fallback;

    return Image.network(
      url,
      fit: BoxFit.cover,
      width: 97,
      height: 97,
      errorBuilder: (context, error, stackTrace) => fallback,
      frameBuilder: (context, child, frame, wasSynchronouslyLoaded) =>
          frame == null && !wasSynchronouslyLoaded ? fallback : child,
    );
  }

  Widget _dateColumn(String label, DateTime? date, Color accent) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          label,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 10,
            letterSpacing: 0.8,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          date != null ? DateFormat('MMM d').format(date) : '—',
          style: AppTypography.style(
            color: accent,
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          date != null ? DateFormat('h:mm a').format(date) : '--:--',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 11,
          ),
        ),
      ],
    );
  }

  Widget _nightsStrip() {
    final nights = _nights;
    // A long stay would otherwise push the row off the card — cap the dots and
    // scale the progress into them.
    final dots = nights > 7 ? 7 : nights;
    final elapsed = nights == 0
        ? 0
        : (_nightsElapsed * dots / nights).round().clamp(0, dots);

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (var i = 0; i < dots; i++) ...[
              if (i > 0) const SizedBox(width: 8),
              Container(
                width: i == elapsed ? 8 : 5,
                height: i == elapsed ? 8 : 5,
                decoration: BoxDecoration(
                  color: i < elapsed
                      ? AppColors.gold
                      : Colors.white.withValues(alpha: 0.2),
                  shape: BoxShape.circle,
                ),
              ),
            ],
          ],
        ),
        const SizedBox(height: 4),
        Text(
          '$nights ${nights == 1 ? 'night' : 'nights'}',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.3),
            fontSize: 10,
          ),
        ),
      ],
    );
  }

  Widget _extendButton() {
    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(100),
      child: InkWell(
        onTap: onExtendStay,
        borderRadius: BorderRadius.circular(100),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.calendar_month_rounded,
                size: 16,
                color: Color(0xFF0A0F1E),
              ),
              const SizedBox(width: 8),
              Text(
                'Extend Stay',
                style: AppTypography.style(
                  color: const Color(0xFF0A0F1E),
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
