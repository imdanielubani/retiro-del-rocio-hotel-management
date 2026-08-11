import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/glass_panel.dart';

/// One tappable module in a [StaffNavRail] — icon, label, and whether it's
/// the screen currently on top.
@immutable
class StaffNavRailEntry {
  const StaffNavRailEntry({
    required this.icon,
    required this.label,
    required this.active,
    required this.onTap,
  });

  final IconData icon;

  /// May contain `\n` to wrap onto two lines, same as reception/security's
  /// rails (e.g. "Work\nOrders").
  final String label;
  final bool active;
  final VoidCallback onTap;
}

/// The shared shell behind every staff tablet's left navigation rail — a
/// 74px frosted column with the brand mark, the module icons and Logout
/// pinned to the bottom. Reception and security each grew their own copy of
/// this shell before it was worth sharing; housekeeping and maintenance are
/// built directly on this one so the four tablets stay visually identical
/// without hand-copying the same file a third and fourth time.
class StaffNavRail extends StatelessWidget {
  const StaffNavRail({
    super.key,
    required this.items,
    required this.onLogout,
  });

  final List<StaffNavRailEntry> items;
  final VoidCallback onLogout;

  @override
  Widget build(BuildContext context) {
    return GlassPanel(
      width: 74,
      padding: const EdgeInsets.fromLTRB(11, 8, 11, 9),
      // When the keyboard is up the rail is shorter than its items, so let it
      // scroll: the ConstrainedBox + IntrinsicHeight keeps Logout pinned to
      // the bottom while there is room, and the scroll view takes over when
      // there isn't — no bottom overflow either way.
      child: LayoutBuilder(
        builder: (context, constraints) => SingleChildScrollView(
          child: ConstrainedBox(
            constraints: BoxConstraints(minHeight: constraints.maxHeight),
            child: IntrinsicHeight(
              child: Column(
                children: [
                  _logo(),
                  const SizedBox(height: 13),
                  for (final entry in items) ...[
                    _StaffNavItem(entry: entry),
                    const SizedBox(height: 4),
                  ],
                  const Spacer(),
                  _StaffNavItem(
                    entry: StaffNavRailEntry(
                      icon: Icons.logout_rounded,
                      label: 'Logout',
                      active: false,
                      onTap: onLogout,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _logo() {
    return Container(
      height: 52,
      width: 52,
      padding: const EdgeInsets.all(5),
      decoration: BoxDecoration(
        color: AppColors.gold.withValues(alpha: 0.12),
        shape: BoxShape.circle,
        border: Border.all(
          color: AppColors.gold.withValues(alpha: 0.28),
          width: 0.8,
        ),
      ),
      child: Image.asset(
        'assets/icons/Rociologosetup.png',
        fit: BoxFit.contain,
      ),
    );
  }
}

class _StaffNavItem extends StatelessWidget {
  const _StaffNavItem({required this.entry});

  final StaffNavRailEntry entry;

  @override
  Widget build(BuildContext context) {
    final tint = entry.active ? AppColors.gold : Colors.white.withValues(alpha: 0.5);

    return Material(
      color: entry.active ? Colors.white.withValues(alpha: 0.1) : Colors.transparent,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        onTap: entry.onTap,
        borderRadius: BorderRadius.circular(18),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 9),
          alignment: Alignment.center,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                height: 20,
                width: 20,
                child: Icon(entry.icon, size: 20, color: tint),
              ),
              const SizedBox(height: 5),
              Text(
                entry.label,
                textAlign: TextAlign.center,
                style: AppTypography.style(
                  color: tint,
                  fontSize: 8,
                  fontWeight: entry.active ? FontWeight.w600 : FontWeight.w500,
                  height: 1.15,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
