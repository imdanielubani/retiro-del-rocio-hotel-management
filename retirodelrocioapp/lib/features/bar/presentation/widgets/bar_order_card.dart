import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_order.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_widgets.dart';

/// A drink order row on the Live Orders board / History list.
class BarOrderCard extends StatelessWidget {
  const BarOrderCard({super.key, required this.order, required this.onTap});

  final BarOrder order;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final accent = barBoardColumnColor(order.boardColumn);

    return Material(
      color: Colors.white.withValues(alpha: 0.05),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      order.tableLabel ?? order.guestName ?? order.code,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.style(
                        color: Colors.white,
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  if (order.isVip)
                    const Padding(
                      padding: EdgeInsets.only(right: 6),
                      child: VipBadge(),
                    ),
                  BarStatusPill(label: order.boardColumnLabel, color: accent),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                order.itemsLabel,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.6),
                  fontSize: 12,
                ),
              ),
              const SizedBox(height: 15),
              Row(
                children: [
                  Icon(
                    order.isPos
                        ? Icons.point_of_sale_rounded
                        : Icons.room_service_rounded,
                    size: 13,
                    color: Colors.white.withValues(alpha: 0.4),
                  ),
                  const SizedBox(width: 4),
                  Text(
                    order.isPos ? 'POS' : 'Room order',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.4),
                      fontSize: 11,
                    ),
                  ),
                  const Spacer(),
                  if (order.needsAgeCheck)
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: Icon(
                        Icons.badge_rounded,
                        size: 14,
                        color: Colors.amber.withValues(alpha: 0.8),
                      ),
                    ),
                  Text(
                    order.totalLabel,
                    style: AppTypography.style(
                      color: AppColors.gold,
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
