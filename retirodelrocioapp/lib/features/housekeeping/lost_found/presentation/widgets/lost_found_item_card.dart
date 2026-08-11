import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/housekeeping/lost_found/domain/lost_found_item.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_widgets.dart';

/// One logged item — a full-width "Mark Returned" footer button while
/// unclaimed (an overflow menu reaches "Mark Disposed"), and a quiet terminal
/// pill with who it was handed back to once cleared. Mirrors
/// `HousekeepingRequestCard`'s state-driven footer pattern.
class LostFoundItemCard extends StatelessWidget {
  const LostFoundItemCard({
    super.key,
    required this.item,
    required this.onMarkReturned,
    required this.onMarkDisposed,
    this.busy = false,
  });

  final LostFoundItem item;
  final VoidCallback onMarkReturned;
  final VoidCallback onMarkDisposed;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: item.isUnclaimed ? AppColors.gold.withValues(alpha: 0.18) : Colors.white.withValues(alpha: 0.08),
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
                  item.itemDescription,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w700),
                ),
              ),
              if (item.isUnclaimed && !busy) _overflowMenu(),
            ],
          ),
          if ((item.roomNumber ?? '').isNotEmpty || (item.roomName ?? '').isNotEmpty) ...[
            const SizedBox(height: 3),
            Text(
              [
                if ((item.roomNumber ?? '').isNotEmpty) 'Room ${item.roomNumber}',
                if ((item.roomName ?? '').isNotEmpty) item.roomName!,
              ].join('  ·  '),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppTypography.style(
                color: AppColors.gold,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
          if ((item.notes ?? '').isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              item.notes!,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: AppTypography.style(color: Colors.white.withValues(alpha: 0.55), fontSize: 12),
            ),
          ],
          if ((item.foundByName ?? '').isNotEmpty || (item.foundLabel ?? '').isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              [
                if ((item.foundByName ?? '').isNotEmpty) 'Found by ${item.foundByName}',
                if ((item.foundLabel ?? '').isNotEmpty) item.foundLabel!,
              ].join('  ·  '),
              style: AppTypography.style(color: Colors.white.withValues(alpha: 0.35), fontSize: 12),
            ),
          ],
          const SizedBox(height: 14),
          _footer(),
        ],
      ),
    );
  }

  Widget _overflowMenu() {
    return PopupMenuButton<void>(
      color: const Color(0xFF141414),
      icon: Icon(Icons.more_vert_rounded, size: 16, color: Colors.white.withValues(alpha: 0.4)),
      padding: EdgeInsets.zero,
      itemBuilder: (context) => [
        PopupMenuItem<void>(
          onTap: onMarkDisposed,
          child: Text('Mark Disposed', style: AppTypography.style(color: Colors.white, fontSize: 13)),
        ),
      ],
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

    if (!item.isUnclaimed) {
      final label = item.status == 'disposed'
          ? 'Disposed'
          : (item.claimantName ?? '').isNotEmpty
          ? 'Returned to ${item.claimantName}'
          : 'Returned';
      final color = item.status == 'disposed' ? kHkSlate : kHkGreen;
      return Align(
        alignment: Alignment.centerLeft,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
          decoration: BoxDecoration(color: color.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(999)),
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.style(color: color, fontSize: 11, fontWeight: FontWeight.w600),
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
          onTap: onMarkReturned,
          borderRadius: BorderRadius.circular(10),
          child: Center(
            child: Text(
              'Mark Returned',
              style: AppTypography.style(color: Colors.black, fontSize: 13, fontWeight: FontWeight.w600),
            ),
          ),
        ),
      ),
    );
  }
}
