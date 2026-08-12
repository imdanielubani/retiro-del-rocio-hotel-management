import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/widgets/staff_nav_rail.dart';

/// The kitchen tablet's navigable modules.
enum KitchenNavItem { liveBoard, queue, menu, history, chat, intercom }

/// The kitchen tablet's left navigation rail, built on the shared
/// [StaffNavRail] shell so it stays visually identical to reception,
/// maintenance and the other nav-rail staff tablets.
class KitchenNavRail extends StatelessWidget {
  const KitchenNavRail({
    super.key,
    required this.active,
    required this.onSelect,
    required this.onLogout,
  });

  final KitchenNavItem active;
  final ValueChanged<KitchenNavItem> onSelect;
  final VoidCallback onLogout;

  @override
  Widget build(BuildContext context) {
    return StaffNavRail(
      onLogout: onLogout,
      items: [
        _entry(
          KitchenNavItem.liveBoard,
          Icons.grid_view_rounded,
          'Live\nBoard',
        ),
        _entry(KitchenNavItem.queue, Icons.receipt_long_rounded, 'Queue'),
        _entry(KitchenNavItem.menu, Icons.restaurant_menu_rounded, 'Menu'),
        _entry(KitchenNavItem.history, Icons.history_rounded, 'History'),
        _entry(KitchenNavItem.chat, Icons.chat_bubble_outline_rounded, 'Chat'),
        _entry(KitchenNavItem.intercom, Icons.call_rounded, 'Intercom'),
      ],
    );
  }

  StaffNavRailEntry _entry(KitchenNavItem item, IconData icon, String label) =>
      StaffNavRailEntry(
        icon: icon,
        label: label,
        active: active == item,
        onTap: () => onSelect(item),
      );
}
