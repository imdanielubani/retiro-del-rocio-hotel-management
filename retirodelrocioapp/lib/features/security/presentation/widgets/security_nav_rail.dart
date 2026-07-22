import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/glass_panel.dart';

/// The security tablet's navigable modules.
enum SecurityNavItem { dashboard, verifiedPass, chat, incidentResponse }

/// The security tablet's left navigation rail (Figma 205:5285) — a 74px frosted
/// column with the brand mark, the module icons and Logout pinned to the bottom.
///
/// The screen owns which item is [active] and handles taps via [onSelect]; only
/// Dashboard and Incident Response are live today, so Verified Pass and Chat
/// read as navigable but land on a "coming soon" placeholder.
class SecurityNavRail extends StatelessWidget {
  const SecurityNavRail({
    super.key,
    required this.active,
    required this.onSelect,
    required this.onLogout,
  });

  final SecurityNavItem active;
  final ValueChanged<SecurityNavItem> onSelect;
  final VoidCallback onLogout;

  @override
  Widget build(BuildContext context) {
    return GlassPanel(
      width: 74,
      padding: const EdgeInsets.fromLTRB(11, 8, 11, 9),
      child: Column(
        children: [
          _logo(),
          const SizedBox(height: 13),
          _NavItem(
            asset: 'assets/icons/dashboard.png',
            label: 'Dashboard',
            active: active == SecurityNavItem.dashboard,
            onTap: () => onSelect(SecurityNavItem.dashboard),
          ),
          const SizedBox(height: 4),
          _NavItem(
            asset: 'assets/icons/Verification.png',
            label: 'Verified\nPass',
            active: active == SecurityNavItem.verifiedPass,
            onTap: () => onSelect(SecurityNavItem.verifiedPass),
          ),
          const SizedBox(height: 4),
          _NavItem(
            asset: 'assets/icons/chat1.png',
            label: 'Chat',
            active: active == SecurityNavItem.chat,
            onTap: () => onSelect(SecurityNavItem.chat),
          ),
          const SizedBox(height: 4),
          _NavItem(
            asset: 'assets/icons/incident.png',
            label: 'Incident\nResponse',
            active: active == SecurityNavItem.incidentResponse,
            onTap: () => onSelect(SecurityNavItem.incidentResponse),
          ),
          const Spacer(),
          _NavItem(
            icon: Icons.logout_rounded,
            label: 'Logout',
            onTap: onLogout,
          ),
        ],
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
        border: Border.all(color: AppColors.gold.withValues(alpha: 0.28), width: 0.8),
      ),
      child: Image.asset('assets/icons/Rociologosetup.png', fit: BoxFit.contain),
    );
  }
}

class _NavItem extends StatelessWidget {
  const _NavItem({
    this.asset,
    this.icon,
    required this.label,
    this.active = false,
    required this.onTap,
  });

  final String? asset;
  final IconData? icon;
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
          padding: const EdgeInsets.symmetric(vertical: 10),
          alignment: Alignment.center,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                height: 18,
                width: 18,
                child: icon != null
                    ? Icon(icon, size: 18, color: tint)
                    : Image.asset(
                        asset!,
                        width: 18,
                        height: 18,
                        color: active ? AppColors.gold : null,
                      ),
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
