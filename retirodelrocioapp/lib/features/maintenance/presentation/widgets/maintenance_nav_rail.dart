import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/widgets/staff_nav_rail.dart';

/// The maintenance tablet's navigable modules.
enum MaintenanceNavItem {
  dashboard,
  workOrders,
  assets,
  requests,
  chat,
  intercom,
}

/// The maintenance tablet's left navigation rail, built on the shared
/// [StaffNavRail] shell so it stays visually identical to reception,
/// security and housekeeping.
class MaintenanceNavRail extends StatelessWidget {
  const MaintenanceNavRail({
    super.key,
    required this.active,
    required this.onSelect,
    required this.onLogout,
  });

  final MaintenanceNavItem active;
  final ValueChanged<MaintenanceNavItem> onSelect;
  final VoidCallback onLogout;

  @override
  Widget build(BuildContext context) {
    return StaffNavRail(
      onLogout: onLogout,
      items: [
        _entry(
          MaintenanceNavItem.dashboard,
          Icons.grid_view_rounded,
          'Dashboard',
        ),
        _entry(
          MaintenanceNavItem.workOrders,
          Icons.build_rounded,
          'Work\nOrders',
        ),
        _entry(MaintenanceNavItem.assets, Icons.inventory_2_rounded, 'Assets'),
        _entry(
          MaintenanceNavItem.requests,
          Icons.assignment_outlined,
          'Requests',
        ),
        _entry(
          MaintenanceNavItem.chat,
          Icons.chat_bubble_outline_rounded,
          'Chat',
        ),
        _entry(MaintenanceNavItem.intercom, Icons.call_rounded, 'Intercom'),
      ],
    );
  }

  StaffNavRailEntry _entry(
    MaintenanceNavItem item,
    IconData icon,
    String label,
  ) => StaffNavRailEntry(
    icon: icon,
    label: label,
    active: active == item,
    onTap: () => onSelect(item),
  );
}
