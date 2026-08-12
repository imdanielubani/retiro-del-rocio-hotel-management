import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_guest_request.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_room.dart';

const Color kHkGreen = Color(0xFF22C55E);
const Color kHkBlue = Color(0xFF3B82F6);
const Color kHkRed = Color(0xFFEF4444);
const Color kHkSlate = Color(0xFF94A3B8);

Color housekeepingStatusColor(String status) => switch (status) {
  'dirty' => AppColors.gold,
  'preparing' => kHkBlue,
  'inspected' => kHkBlue,
  'out_of_order' => kHkRed,
  _ => kHkGreen, // clean
};

/// A headline counter, matching the reception/security dashboards' stat card
/// language — a small tracked label and a large accent number.
class HousekeepingStatCard extends StatelessWidget {
  const HousekeepingStatCard({
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
      padding: const EdgeInsets.all(20.8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.08),
          width: 0.8,
        ),
      ),
      child: Column(
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
          const SizedBox(height: 12),
          Text(
            '$value',
            style: AppTypography.style(
              color: accent,
              fontSize: 28,
              fontWeight: FontWeight.w800,
              height: 1,
            ),
          ),
        ],
      ),
    );
  }
}

/// A rounded status pill for a room's cleanliness.
class HousekeepingStatusPill extends StatelessWidget {
  const HousekeepingStatusPill({
    super.key,
    required this.status,
    required this.label,
  });

  final String status;
  final String label;

  @override
  Widget build(BuildContext context) {
    final color = housekeepingStatusColor(status);
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

/// A titled panel shell used by the dashboard's Rooms Needing Attention and
/// Guest Requests sections — a gold uppercase heading over a faint frosted
/// card, matching the reception dashboard's Today's Arrivals / Today's
/// Departures panels (`ReceptionSectionPanel`).
class HousekeepingSectionPanel extends StatelessWidget {
  const HousekeepingSectionPanel({
    super.key,
    required this.title,
    required this.child,
    this.trailing,
    this.expand = true,
  });

  final String title;
  final Widget child;

  /// e.g. a count badge, shown at the right of the title row.
  final Widget? trailing;

  /// When false the panel hugs its content instead of filling the column.
  final bool expand;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(19),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.03),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.06),
          width: 0.8,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: expand ? MainAxisSize.max : MainAxisSize.min,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: AppTypography.style(
                    color: AppColors.gold,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1.4,
                  ),
                ),
              ),
              if (trailing != null) trailing!,
            ],
          ),
          const SizedBox(height: 15),
          expand ? Expanded(child: child) : child,
        ],
      ),
    );
  }
}

/// One room in the Rooms grid / dashboard's "needs attention" list — its
/// occupancy, cleanliness, and the single most relevant next action as a
/// full-width footer button, the same state-driven pattern the reception
/// dashboard's arrival/departure cards use. The overflow menu beside the
/// room number still reaches all four statuses directly, for the rare case
/// the obvious next step isn't the one wanted.
class HousekeepingRoomCard extends StatelessWidget {
  const HousekeepingRoomCard({
    super.key,
    required this.room,
    required this.onMarkStatus,
    this.onReportFault,
    this.busy = false,
  });

  final HousekeepingRoom room;

  /// Called with the chosen status when the housekeeper taps an action.
  final ValueChanged<String> onMarkStatus;

  /// Opens the Report Fault dialog for this room, reached from the overflow
  /// menu. Left null on a screen that doesn't wire it up (e.g. a future
  /// read-only room list), in which case the menu simply omits the option.
  final VoidCallback? onReportFault;

  /// True while this room's status update is in flight.
  final bool busy;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: room.needsAttention
              ? housekeepingStatusColor(
                  room.housekeepingStatus,
                ).withValues(alpha: 0.35)
              : Colors.white.withValues(alpha: 0.08),
          width: 0.8,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              if (room.needsInspection) ...[
                _tag('Inspection needed', kHkBlue),
                const SizedBox(width: 8),
              ] else if (room.checkoutToday) ...[
                _tag('Checkout today', AppColors.gold),
                const SizedBox(width: 8),
              ],
              const Spacer(),
              Text(
                'Room ${room.number}',
                style: AppTypography.style(
                  color: AppColors.gold,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.5,
                ),
              ),
              const SizedBox(width: 4),
              _overflowMenu(context),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            (room.guestName ?? '').isNotEmpty
                ? room.guestName!
                : (room.roomName ?? 'Room ${room.number}'),
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
            [
              if ((room.roomName ?? '').isNotEmpty &&
                  (room.guestName ?? '').isNotEmpty)
                room.roomName!,
              room.occupancyLabel,
            ].join('  ·  '),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.45),
              fontSize: 12,
            ),
          ),
          if ((room.updatedLabel ?? '').isNotEmpty) ...[
            const SizedBox(height: 2),
            Text(
              'Updated ${room.updatedLabel}',
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.35),
                fontSize: 12,
              ),
            ),
          ],
          const SizedBox(height: 10),
          HousekeepingStatusPill(
            status: room.housekeepingStatus,
            label: room.housekeepingStatusLabel,
          ),
          const SizedBox(height: 14),
          _footer(),
        ],
      ),
    );
  }

  Widget _tag(String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: AppTypography.style(
          color: color,
          fontSize: 10,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  /// The single most relevant next action, full width — dirty/out-of-order
  /// rooms get a filled call to action; a room being actively prepared gets
  /// the "Mark Clean" hand-off once the turnover clean is done; a clean room
  /// gets a quieter "Mark Inspected" nudge; an already-inspected room just
  /// shows its pill, nothing left for this card to ask for.
  Widget _footer() {
    if (busy) {
      return const SizedBox(
        width: double.infinity,
        height: 36,
        child: Center(
          child: SizedBox(
            width: 16,
            height: 16,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: AppColors.gold,
            ),
          ),
        ),
      );
    }

    return switch (room.housekeepingStatus) {
      'dirty' => _button(
        'Mark Preparing',
        filled: true,
        color: AppColors.gold,
        onTap: () => onMarkStatus('preparing'),
      ),
      'preparing' => _button(
        'Mark Clean',
        filled: true,
        color: kHkBlue,
        onTap: () => onMarkStatus('clean'),
      ),
      'out_of_order' => _button(
        'Mark Fixed',
        filled: true,
        color: kHkRed,
        onTap: () => onMarkStatus('clean'),
      ),
      'clean' => _button(
        'Mark Inspected',
        filled: false,
        color: kHkBlue,
        onTap: () => onMarkStatus('inspected'),
      ),
      _ => const SizedBox.shrink(), // inspected — nothing further to do here
    };
  }

  Widget _button(
    String label, {
    required bool filled,
    required Color color,
    required VoidCallback onTap,
  }) {
    final fg = filled
        ? (color == AppColors.gold ? Colors.black : Colors.white)
        : color;
    return SizedBox(
      width: double.infinity,
      height: 36,
      child: Material(
        color: filled ? color : Colors.white.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(10),
          child: Center(
            child: Text(
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

  Widget _overflowMenu(BuildContext context) {
    return PopupMenuButton<void>(
      color: const Color(0xFF141414),
      icon: Icon(
        Icons.more_vert_rounded,
        size: 16,
        color: Colors.white.withValues(alpha: 0.4),
      ),
      padding: EdgeInsets.zero,
      itemBuilder: (context) => [
        for (final status in const [
          'clean',
          'dirty',
          'preparing',
          'inspected',
          'out_of_order',
        ])
          if (status != room.housekeepingStatus)
            PopupMenuItem<void>(
              onTap: () => onMarkStatus(status),
              child: Text(
                _statusMenuLabel(status),
                style: AppTypography.style(color: Colors.white, fontSize: 13),
              ),
            ),
        if (onReportFault != null) ...[
          const PopupMenuDivider(height: 1),
          PopupMenuItem<void>(
            onTap: onReportFault,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  Icons.build_outlined,
                  size: 15,
                  color: Colors.white.withValues(alpha: 0.6),
                ),
                const SizedBox(width: 8),
                Text(
                  'Report Fault',
                  style: AppTypography.style(color: Colors.white, fontSize: 13),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  String _statusMenuLabel(String status) => switch (status) {
    'dirty' => 'Mark Dirty',
    'preparing' => 'Mark Preparing',
    'inspected' => 'Mark Inspected',
    'out_of_order' => 'Mark Out of Order',
    _ => 'Mark Clean',
  };
}

/// One guest ask in the Requests list — a full-width "Mark Complete" footer
/// button while pending, matching `HousekeepingRoomCard`'s state-driven
/// footer, and a quiet terminal pill once cleared.
class HousekeepingRequestCard extends StatelessWidget {
  const HousekeepingRequestCard({
    super.key,
    required this.request,
    required this.onComplete,
    this.busy = false,
  });

  final HousekeepingGuestRequest request;
  final VoidCallback onComplete;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: request.isPending
              ? AppColors.gold.withValues(alpha: 0.18)
              : Colors.white.withValues(alpha: 0.08),
          width: 0.8,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  request.typeLabel,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              if ((request.roomNumber ?? '').isNotEmpty) ...[
                const SizedBox(width: 8),
                Text(
                  'Room ${request.roomNumber}',
                  style: AppTypography.style(
                    color: AppColors.gold,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.5,
                  ),
                ),
              ],
            ],
          ),
          if ((request.roomName ?? '').isNotEmpty ||
              (request.guestName ?? '').isNotEmpty) ...[
            const SizedBox(height: 3),
            Text(
              [
                if ((request.guestName ?? '').isNotEmpty) request.guestName!,
                if ((request.roomName ?? '').isNotEmpty) request.roomName!,
              ].join('  ·  '),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.45),
                fontSize: 12,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
          if ((request.notes ?? '').isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              request.notes!,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.55),
                fontSize: 12,
              ),
            ),
          ],
          if ((request.createdLabel ?? '').isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              request.createdLabel!,
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.35),
                fontSize: 12,
              ),
            ),
          ],
          const SizedBox(height: 14),
          _footer(),
        ],
      ),
    );
  }

  Widget _footer() {
    if (busy) {
      return const SizedBox(
        width: double.infinity,
        height: 36,
        child: Center(
          child: SizedBox(
            width: 16,
            height: 16,
            child: CircularProgressIndicator(strokeWidth: 2, color: kHkGreen),
          ),
        ),
      );
    }

    if (!request.isPending) {
      return Align(
        alignment: Alignment.centerLeft,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
          decoration: BoxDecoration(
            color: kHkSlate.withValues(alpha: 0.14),
            borderRadius: BorderRadius.circular(999),
          ),
          child: Text(
            'Completed',
            style: AppTypography.style(
              color: kHkSlate,
              fontSize: 11,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      );
    }

    return SizedBox(
      width: double.infinity,
      height: 36,
      child: Material(
        color: kHkGreen,
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          onTap: onComplete,
          borderRadius: BorderRadius.circular(10),
          child: Center(
            child: Text(
              'Mark Complete',
              style: AppTypography.style(
                color: Colors.black,
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

/// A quiet placeholder for a section with nothing in it yet.
class HousekeepingSectionEmpty extends StatelessWidget {
  const HousekeepingSectionEmpty({
    super.key,
    required this.icon,
    required this.message,
  });

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
