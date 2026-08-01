import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/widgets/staff_nav_rail.dart';

/// The housekeeping tablet's navigable modules.
enum HousekeepingNavItem { dashboard, rooms, requests, chat }

/// The housekeeping tablet's left navigation rail, built on the shared
/// [StaffNavRail] shell so it stays visually identical to reception,
/// security and maintenance.
class HousekeepingNavRail extends StatelessWidget {
  const HousekeepingNavRail({
    super.key,
    required this.active,
    required this.onSelect,
    required this.onLogout,
  });

  final HousekeepingNavItem active;
  final ValueChanged<HousekeepingNavItem> onSelect;
  final VoidCallback onLogout;

  @override
  Widget build(BuildContext context) {
    return StaffNavRail(
      onLogout: onLogout,
      items: [
        _entry(HousekeepingNavItem.dashboard, Icons.grid_view_rounded, 'Dashboard'),
        _entry(HousekeepingNavItem.rooms, Icons.meeting_room_rounded, 'Rooms'),
        _entry(HousekeepingNavItem.requests, Icons.room_service_rounded, 'Requests'),
        _entry(HousekeepingNavItem.chat, Icons.chat_bubble_outline_rounded, 'Chat'),
      ],
    );
  }

  StaffNavRailEntry _entry(HousekeepingNavItem item, IconData icon, String label) => StaffNavRailEntry(
    icon: icon,
    label: label,
    active: active == item,
    onTap: () => onSelect(item),
  );
}
