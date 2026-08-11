import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_tab.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_widgets.dart';

/// An open/settled tab row on the Tabs / History screens.
class BarTabCard extends StatelessWidget {
  const BarTabCard({super.key, required this.tab, required this.onTap});

  final BarTab tab;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final accent = switch (tab.status) {
      'settled' => const Color(0xFF16A34A),
      'void' => const Color(0xFFDC2626),
      _ => const Color(0xFF2563EB),
    };

    return Material(
      color: Colors.white.withValues(alpha: 0.05),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Container(
                width: 42,
                height: 42,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.gold.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.table_bar_rounded,
                  size: 20,
                  color: AppColors.gold,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            tab.tableLabel ?? tab.guestName ?? tab.code,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppTypography.style(
                              color: Colors.white,
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                        if (tab.isVip)
                          const Padding(
                            padding: EdgeInsets.only(left: 6),
                            child: VipBadge(),
                          ),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${tab.orderCount} ${tab.orderCount == 1 ? 'order' : 'orders'} · ${tab.openedAtLabel ?? ''}',
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.5),
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    tab.totalLabel,
                    style: AppTypography.style(
                      color: Colors.white,
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  BarStatusPill(label: tab.statusLabel, color: accent),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
