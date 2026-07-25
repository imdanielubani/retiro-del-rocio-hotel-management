import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/glass_panel.dart';

/// The reception tablet's navigable modules (Figma 344:14347–14384).
enum ReceptionNavItem {
  dashboard,
  guests,
  bookings,
  visitorPass,
  roomStatus,
  vehiclePickup,
  chat,
}

/// The reception tablet's left navigation rail — a 74px frosted column with the
/// brand mark, the module icons and Logout pinned to the bottom.
///
/// The screen owns which item is [active] and handles taps via [onSelect]; only
/// Dashboard is live today, so the rest read as navigable but land on a "coming
/// soon" placeholder.
class ReceptionNavRail extends StatelessWidget {
  const ReceptionNavRail({
    super.key,
    required this.active,
    required this.onSelect,
    required this.onLogout,
  });

  final ReceptionNavItem active;
  final ValueChanged<ReceptionNavItem> onSelect;
  final VoidCallback onLogout;

  @override
  Widget build(BuildContext context) {
    return GlassPanel(
      width: 74,
      padding: const EdgeInsets.fromLTRB(11, 8, 11, 9),
      // When the keyboard is up the rail is shorter than its items, so let it
      // scroll: the ConstrainedBox + IntrinsicHeight keeps Logout pinned to the
      // bottom while there is room, and the scroll view takes over when there
      // isn't — no bottom overflow either way.
      child: LayoutBuilder(
        builder: (context, constraints) => SingleChildScrollView(
          child: ConstrainedBox(
            constraints: BoxConstraints(minHeight: constraints.maxHeight),
            child: IntrinsicHeight(
              child: Column(
                children: [
                  _logo(),
                  const SizedBox(height: 13),
                  _item(ReceptionNavItem.dashboard, Icons.grid_view_rounded, 'Dashboard'),
                  const SizedBox(height: 4),
                  _item(ReceptionNavItem.guests, Icons.people_alt_rounded, 'Guests'),
                  const SizedBox(height: 4),
                  _item(ReceptionNavItem.bookings, Icons.calendar_month_rounded, 'Bookings'),
                  const SizedBox(height: 4),
                  _item(ReceptionNavItem.visitorPass, Icons.badge_rounded, 'Visitor\nPass'),
                  const SizedBox(height: 4),
                  _item(ReceptionNavItem.roomStatus, Icons.meeting_room_rounded, 'Room\nStatus'),
                  const SizedBox(height: 4),
                  _item(ReceptionNavItem.vehiclePickup, Icons.directions_car_rounded, 'Vehicle\nPickup'),
                  const SizedBox(height: 4),
                  _item(ReceptionNavItem.chat, Icons.chat_bubble_outline_rounded, 'Chat'),
                  const Spacer(),
                  _NavItem(icon: Icons.logout_rounded, label: 'Logout', onTap: onLogout),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _item(ReceptionNavItem item, IconData icon, String label) => _NavItem(
        icon: icon,
        label: label,
        active: active == item,
        onTap: () => onSelect(item),
      );

  Widget _logo() {
    return Container(
      height: 52,
      width: 52,
      padding: const EdgeInsets.all(5),
      decoration: BoxDecoration(
        color: AppColors.gold.withValues(alpha: 0.12),
        shape: BoxShape.circle,
        border: Border.all(color: AppColors.gold.withValues(alpha: 0.28), width: 0.8),
      ),
      child: Image.asset('assets/icons/Rociologosetup.png', fit: BoxFit.contain),
    );
  }
}

class _NavItem extends StatelessWidget {
  const _NavItem({
    required this.icon,
    required this.label,
    this.active = false,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final tint = active ? AppColors.gold : Colors.white.withValues(alpha: 0.5);

    return Material(
      color: active ? Colors.white.withValues(alpha: 0.1) : Colors.transparent,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        onTap: onTap,
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
                child: Icon(icon, size: 20, color: tint),
              ),
              const SizedBox(height: 5),
              Text(
                label,
                textAlign: TextAlign.center,
                style: AppTypography.style(
                  color: tint,
                  fontSize: 8,
                  fontWeight: active ? FontWeight.w600 : FontWeight.w500,
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
