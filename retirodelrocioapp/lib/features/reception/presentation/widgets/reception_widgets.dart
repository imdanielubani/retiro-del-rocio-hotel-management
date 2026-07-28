import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_booking_row.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_guest.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_overview.dart';

const Color kReceptionBlue = Color(0xFF3B82F6);
const Color kReceptionGreen = Color(0xFF22C55E);
const Color kReceptionRed = Color(0xFFEF4444);
const Color kReceptionSlate = Color(0xFF94A3B8);

/// A "Walk-in" / "Phone" chip flagging how a desk booking reached us. Shared by
/// the arrivals cards and the bookings list.
Widget receptionOriginBadge(String label) {
  return Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
    decoration: BoxDecoration(
      color: kReceptionBlue.withValues(alpha: 0.15),
      borderRadius: BorderRadius.circular(8),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(Icons.person_pin_circle_rounded, size: 11, color: kReceptionBlue),
        const SizedBox(width: 4),
        Text(
          label,
          style: AppTypography.style(color: kReceptionBlue, fontSize: 10, fontWeight: FontWeight.w700),
        ),
      ],
    ),
  );
}

/// The accent colour for a booking status, shared by pills across the module.
Color receptionStatusColor(String status) => switch (status) {
      'paid' => kReceptionGreen,
      'checked_in' => kReceptionBlue,
      'checked_out' => kReceptionSlate,
      'cancelled' => kReceptionRed,
      _ => AppColors.gold, // pending
    };

/// A rounded status pill (Confirmed / Checked In / …).
class ReceptionStatusPill extends StatelessWidget {
  const ReceptionStatusPill({super.key, required this.status, required this.label});

  final String status;
  final String label;

  @override
  Widget build(BuildContext context) {
    final color = receptionStatusColor(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: AppTypography.style(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

/// An avatar disc showing a guest's initials.
class ReceptionAvatar extends StatelessWidget {
  const ReceptionAvatar({super.key, required this.initials, this.size = 44});

  final String initials;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(size * 0.32),
      ),
      child: Text(
        initials,
        style: AppTypography.style(
          color: Colors.white,
          fontSize: size * 0.3,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

/// One guest in the Guests list — avatar, name, contact and a stays/in-house tag.
class ReceptionGuestCard extends StatelessWidget {
  const ReceptionGuestCard({
    super.key,
    required this.guest,
    required this.onTap,
    this.onCheckOut,
    this.checkingOut = false,
  });

  final ReceptionGuestSummary guest;
  final VoidCallback onTap;

  /// Checks this guest out in one tap, without opening their profile — only
  /// shown when [ReceptionGuestSummary.inHouse] is true.
  final VoidCallback? onCheckOut;

  /// True while this card's check-out request is in flight.
  final bool checkingOut;

  @override
  Widget build(BuildContext context) {
    final contact = [
      if ((guest.email ?? '').isNotEmpty) guest.email!,
      if ((guest.phone ?? '').isNotEmpty) guest.phone!,
    ].join('  ·  ');

    return Material(
      color: Colors.white.withValues(alpha: 0.04),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: Colors.white.withValues(alpha: 0.08), width: 0.8),
          ),
          child: Row(
            children: [
              ReceptionAvatar(initials: guest.initials),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            guest.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppTypography.style(
                              color: Colors.white,
                              fontSize: 15,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                        if (guest.inHouse) ...[
                          const SizedBox(width: 8),
                          const ReceptionStatusPill(status: 'checked_in', label: 'In-House'),
                        ],
                      ],
                    ),
                    if (contact.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        contact,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTypography.style(
                          color: Colors.white.withValues(alpha: 0.4),
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    '${guest.stays}',
                    style: AppTypography.style(
                      color: AppColors.gold,
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  Text(
                    guest.stays == 1 ? 'stay' : 'stays',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.35),
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
              if (guest.inHouse && onCheckOut != null) ...[
                const SizedBox(width: 10),
                _checkOutButton(),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _checkOutButton() {
    return Tooltip(
      message: 'Check Out',
      child: Material(
        color: kReceptionRed.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          onTap: checkingOut ? null : onCheckOut,
          borderRadius: BorderRadius.circular(10),
          child: SizedBox(
            width: 36,
            height: 36,
            child: Center(
              child: checkingOut
                  ? SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: kReceptionRed,
                      ),
                    )
                  : Icon(Icons.logout_rounded, size: 18, color: kReceptionRed),
            ),
          ),
        ),
      ),
    );
  }
}

/// One booking in the Bookings list and in a guest's stay history.
class ReceptionBookingRowCard extends StatelessWidget {
  const ReceptionBookingRowCard({super.key, required this.row});

  final ReceptionBookingRow row;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.white.withValues(alpha: 0.08), width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                row.reference,
                style: AppTypography.style(
                  color: AppColors.gold,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.5,
                ),
              ),
              if (row.originLabel != null) ...[
                const SizedBox(width: 8),
                receptionOriginBadge(row.originLabel!),
              ],
              const Spacer(),
              ReceptionStatusPill(status: row.status, label: row.statusLabel),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            row.guestName,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 15,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            row.roomLabel,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.45),
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Icon(Icons.calendar_today_rounded, size: 12, color: Colors.white.withValues(alpha: 0.35)),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  '${row.checkInLabel} → ${row.checkOutLabel}  ·  ${row.nights} ${row.nights == 1 ? 'night' : 'nights'}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.4),
                    fontSize: 12,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Text(
                row.amountLabel,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.85),
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// A headline counter (Figma 299:123): a small tracked label and a large accent
/// number, on a faint frosted card with a fading accent underline.
class ReceptionStatCard extends StatelessWidget {
  const ReceptionStatCard({
    super.key,
    required this.label,
    required this.value,
    required this.accent,
  });

  final String label;
  final int value;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 121,
      padding: const EdgeInsets.all(20.8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.white.withValues(alpha: 0.08), width: 0.8),
      ),
      child: Stack(
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.35),
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1.2,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                '$value',
                style: AppTypography.style(
                  color: accent,
                  fontSize: 36,
                  fontWeight: FontWeight.w800,
                  height: 1,
                ),
              ),
            ],
          ),
          Positioned(
            left: -15,
            right: 0,
            bottom: 0,
            child: Container(
              height: 2,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [accent.withValues(alpha: 0.25), Colors.transparent],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// A titled panel shell used by the arrivals, departures, alerts and room-status
/// sections — a gold uppercase heading over a faint frosted card.
class ReceptionSectionPanel extends StatelessWidget {
  const ReceptionSectionPanel({
    super.key,
    required this.title,
    required this.child,
    this.expand = true,
  });

  final String title;
  final Widget child;

  /// When false the panel hugs its content instead of filling the column.
  final bool expand;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(19),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.03),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.white.withValues(alpha: 0.06), width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: expand ? MainAxisSize.max : MainAxisSize.min,
        children: [
          Text(
            title,
            style: AppTypography.style(
              color: AppColors.gold,
              fontSize: 12,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.4,
            ),
          ),
          const SizedBox(height: 15),
          expand ? Expanded(child: child) : child,
        ],
      ),
    );
  }
}

/// One arrival or departure card (Figma 299:162 / 345:14853).
///
/// The action is state-driven, exactly like the admin booking view: a guest yet
/// to arrive shows **Check In**, a guest in-house shows **Check Out**, and a
/// guest who has left shows a terminal status. So reception can drive a
/// particular guest through both check-in and check-out from either list —
/// [onCheckIn] and [onCheckOut] are the two actions; the card picks the right one.
class ReceptionBookingCard extends StatelessWidget {
  const ReceptionBookingCard({
    super.key,
    required this.booking,
    required this.busy,
    this.onCheckIn,
    this.onCheckOut,
  });

  final ReceptionBooking booking;

  /// True while this card's action is mid-request, so it shows a spinner.
  final bool busy;

  final VoidCallback? onCheckIn;
  final VoidCallback? onCheckOut;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.white.withValues(alpha: 0.08), width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              if (booking.originLabel != null) ...[
                receptionOriginBadge(booking.originLabel!),
                const SizedBox(width: 6),
              ],
              if (booking.isOverdue) _overdueBadge(),
              const Spacer(),
              Text(
                booking.reference,
                style: AppTypography.style(
                  color: AppColors.gold,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.5,
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            booking.guestName,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 15,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            booking.roomLabel,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.5),
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            booking.isOverdue && booking.overdueLabel != null
                ? '${booking.dateLabel} · ${booking.overdueLabel}'
                : booking.dateLabel,
            style: AppTypography.style(
              color: booking.isOverdue
                  ? kReceptionRed.withValues(alpha: 0.85)
                  : Colors.white.withValues(alpha: 0.35),
              fontSize: 12,
              fontWeight: booking.isOverdue ? FontWeight.w600 : FontWeight.w400,
            ),
          ),
          const SizedBox(height: 14),
          _footer(),
        ],
      ),
    );
  }

  Widget _overdueBadge() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: kReceptionRed.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.warning_rounded, size: 11, color: kReceptionRed),
          const SizedBox(width: 4),
          Text(
            'Overdue',
            style: AppTypography.style(
              color: kReceptionRed,
              fontSize: 10,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  Widget _footer() {
    switch (booking.status) {
      case 'paid':
        return _button('Check In', filled: true, onTap: onCheckIn);
      case 'checked_in':
        // Overdue checkouts are the more urgent action — a filled red button
        // makes it visually stand out on the departures list.
        return _button(
          'Check Out',
          filled: booking.isOverdue,
          filledColor: kReceptionRed,
          onTap: onCheckOut,
        );
      default:
        // Checked out or otherwise closed — nothing left for the desk to do.
        return Align(
          alignment: Alignment.centerLeft,
          child: ReceptionStatusPill(
            status: booking.status,
            label: booking.statusLabel.isNotEmpty ? booking.statusLabel : booking.status,
          ),
        );
    }
  }

  Widget _button(
    String label, {
    required bool filled,
    required VoidCallback? onTap,
    Color? filledColor,
  }) {
    final bg = filled
        ? (filledColor ?? AppColors.gold)
        : Colors.white.withValues(alpha: 0.08);
    // Gold pairs with black text (matches every other gold button in the app);
    // any other filled colour (e.g. the overdue red) reads better with white.
    final fg = !filled
        ? Colors.white
        : (filledColor == null ? Colors.black : Colors.white);

    return SizedBox(
      width: double.infinity,
      height: 36,
      child: Material(
        color: bg,
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          onTap: busy ? null : onTap,
          borderRadius: BorderRadius.circular(10),
          child: Center(
            child: busy
                ? SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(strokeWidth: 2, color: fg),
                  )
                : Text(
                    label,
                    style: AppTypography.style(
                      color: fg,
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
          ),
        ),
      ),
    );
  }
}

/// The arrivals empty state — the same quiet icon-and-message treatment as the
/// departures and alerts sections, just with an arrivals-appropriate icon and
/// prompt, so all three empty states read as one family.
class ReceptionArrivalsEmpty extends StatelessWidget {
  const ReceptionArrivalsEmpty({super.key});

  @override
  Widget build(BuildContext context) {
    return const ReceptionSectionEmpty(
      icon: Icons.calendar_month_rounded,
      message: 'Type a room number to call directly',
    );
  }
}

/// One row in the Alerts panel (Figma 344:14606): a severity dot, a title and a
/// relative time.
class ReceptionAlertRow extends StatelessWidget {
  const ReceptionAlertRow({super.key, required this.alert});

  final ReceptionAlert alert;

  Color get _color => switch (alert.severity) {
        AlertSeverity.high => kReceptionRed,
        AlertSeverity.medium => AppColors.gold,
        AlertSeverity.low => kReceptionGreen,
      };

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Icon(Icons.warning_amber_rounded, size: 14, color: _color),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                alert.title,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: AppTypography.style(
                  color: _color,
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                alert.timeLabel,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.35),
                  fontSize: 12,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// The three room-status tiles (Figma 344:14665): Occupied / Dirty / Maint.
class ReceptionRoomStatusTiles extends StatelessWidget {
  const ReceptionRoomStatusTiles({super.key, required this.status});

  final ReceptionRoomStatus status;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(child: _tile(status.occupied, 'Occupied', kReceptionBlue)),
        const SizedBox(width: 8),
        Expanded(child: _tile(status.dirty, 'Dirty', AppColors.gold)),
        const SizedBox(width: 8),
        Expanded(child: _tile(status.maintenance, 'Maint.', kReceptionRed)),
      ],
    );
  }

  Widget _tile(int value, String label, Color accent) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 11),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: accent.withValues(alpha: 0.25), width: 0.8),
      ),
      child: Column(
        children: [
          Text(
            '$value',
            style: AppTypography.style(
              color: accent,
              fontSize: 26,
              fontWeight: FontWeight.w800,
              height: 1,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            label,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.5),
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}

/// A quiet placeholder for a section with nothing in it yet.
class ReceptionSectionEmpty extends StatelessWidget {
  const ReceptionSectionEmpty({super.key, required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 22, color: Colors.white.withValues(alpha: 0.25)),
          const SizedBox(height: 8),
          Text(
            message,
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.35),
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}
