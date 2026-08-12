import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_order.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_widgets.dart';

/// A food ticket row on the Live Board / Queue / History list.
class KitchenOrderCard extends StatelessWidget {
  const KitchenOrderCard({super.key, required this.order, required this.onTap});

  final KitchenOrder order;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final accent = kitchenBoardColumnColor(order.boardColumn);

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
                      order.destinationLabel,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.style(
                        color: Colors.white,
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  if (order.hasDrinks)
                    Padding(
                      padding: const EdgeInsets.only(right: 6),
                      child: Icon(
                        Icons.local_bar_rounded,
                        size: 14,
                        color: Colors.white.withValues(alpha: 0.4),
                      ),
                    ),
                  KitchenStatusPill(
                    label: order.boardColumnLabel,
                    color: accent,
                  ),
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
              if (order.estimatedReadyLabel != null &&
                  order.boardColumn == 'preparing') ...[
                const SizedBox(height: 6),
                Row(
                  children: [
                    Icon(
                      Icons.schedule_rounded,
                      size: 13,
                      color: order.estimatedReadyOverdue
                          ? const Color(0xFFEF4444)
                          : AppColors.gold,
                    ),
                    const SizedBox(width: 4),
                    Text(
                      order.estimatedReadyOverdue
                          ? 'Running late · was due ${order.estimatedReadyLabel}'
                          : 'Ready by ${order.estimatedReadyLabel}',
                      style: AppTypography.style(
                        color: order.estimatedReadyOverdue
                            ? const Color(0xFFEF4444)
                            : AppColors.gold,
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ],
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
                    order.isPos ? 'Bar POS' : 'Room order',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.4),
                      fontSize: 11,
                    ),
                  ),
                  const Spacer(),
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
