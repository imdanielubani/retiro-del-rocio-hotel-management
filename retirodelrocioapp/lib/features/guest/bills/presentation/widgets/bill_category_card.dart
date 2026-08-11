import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/bills/domain/bill.dart';

/// A charge category card on the My Bills screen (Figma 140:2522): a header
/// row with the item count and amount that expands to the itemised lines when
/// it has charges, or a dimmed "No charges" card when it doesn't.
class BillCategoryCard extends StatefulWidget {
  const BillCategoryCard({
    super.key,
    required this.category,
    this.initiallyExpanded = false,
  });

  final BillCategory category;
  final bool initiallyExpanded;

  @override
  State<BillCategoryCard> createState() => _BillCategoryCardState();
}

class _BillCategoryCardState extends State<BillCategoryCard> {
  late bool _expanded = widget.initiallyExpanded;

  @override
  Widget build(BuildContext context) {
    final category = widget.category;
    final expandable = category.hasCharges && category.items.isNotEmpty;

    return Container(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: category.hasCharges
              ? AppColors.gold.withValues(alpha: 0.13)
              : Colors.white.withValues(alpha: 0.06),
          width: 0.8,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Material(
            color: Colors.transparent,
            borderRadius: BorderRadius.circular(24),
            child: InkWell(
              onTap: expandable
                  ? () => setState(() => _expanded = !_expanded)
                  : null,
              borderRadius: BorderRadius.circular(24),
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            category.label,
                            style: AppTypography.style(
                              color: Colors.white,
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            category.itemCount == 1
                                ? '1 item'
                                : '${category.itemCount} items',
                            style: AppTypography.style(
                              color: Colors.white.withValues(alpha: 0.4),
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    if (category.hasCharges)
                      Text(
                        category.amountLabel ?? 'NGN 0',
                        style: AppTypography.style(
                          color: AppColors.gold,
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                      )
                    else
                      Text(
                        'No charges',
                        style: AppTypography.style(
                          color: Colors.white.withValues(alpha: 0.25),
                          fontSize: 13,
                        ),
                      ),
                    if (expandable) ...[
                      const SizedBox(width: 10),
                      AnimatedRotation(
                        turns: _expanded ? 0.5 : 0,
                        duration: const Duration(milliseconds: 180),
                        child: Icon(
                          Icons.keyboard_arrow_down_rounded,
                          size: 20,
                          color: Colors.white.withValues(alpha: 0.4),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
          if (_expanded)
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 18),
              child: Column(
                children: [
                  Container(
                    height: 1,
                    color: Colors.white.withValues(alpha: 0.08),
                  ),
                  const SizedBox(height: 14),
                  for (var i = 0; i < category.items.length; i++) ...[
                    if (i > 0) const SizedBox(height: 12),
                    _itemRow(category.items[i]),
                  ],
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _itemRow(BillLineItem item) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                item.label,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.85),
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                ),
              ),
              if ((item.sub ?? '').isNotEmpty) ...[
                const SizedBox(height: 2),
                Text(
                  item.sub!,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.35),
                    fontSize: 11,
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(width: 12),
        Text(
          item.amountLabel,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.7),
            fontSize: 13,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}
