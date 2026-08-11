import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/session_guard.dart';
import 'package:retirodelrocioapp/features/bar/application/bar_providers.dart';
import 'package:retirodelrocioapp/features/bar/notifications/application/bar_notification_providers.dart';
import 'package:retirodelrocioapp/features/bar/presentation/screens/bar_history_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/screens/bar_live_orders_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/screens/bar_menu_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/screens/bar_tabs_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_top_bar.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';

enum BarTabItem { liveOrders, tabs, menu, history }

/// The Bar Tablet's shell — a bottom tab bar (Live Orders · Tabs · Menu ·
/// History), distinct from the left nav rail every other staff tablet uses,
/// closer to a dedicated POS app. Hosts the 4 screens via [IndexedStack] so
/// each keeps its scroll position when switching tabs.
class BarShell extends ConsumerStatefulWidget {
  const BarShell({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<BarShell> createState() => _BarShellState();
}

class _BarShellState extends ConsumerState<BarShell> {
  BarTabItem _tab = BarTabItem.liveOrders;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    ref.watch(barRealtimeProvider(_token));
    ref.watch(barNotificationChimeProvider(_token));

    final overview = ref.watch(barOverviewProvider(_token)).value;
    final weather = ref.watch(weatherProvider).value;
    final unreadNotifications = ref.watch(
      barUnreadNotificationsProvider(_token),
    );

    return SessionGuard(
      child: Scaffold(
        backgroundColor: AppColors.background,
        body: Stack(
          fit: StackFit.expand,
          children: [
            Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
            const ColoredBox(color: Color(0xF2000000)),
            SafeArea(
              child: Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(24, 20, 24, 0),
                    child: BarTopBar(
                      name: widget.session.name,
                      role: 'Bar & Lounge',
                      weather: weather,
                      hasAlert: (overview?.newCount ?? 0) > 0,
                      hasUnreadNotifications: unreadNotifications > 0,
                    ),
                  ),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 24),
                      child: IndexedStack(
                        index: _tab.index,
                        children: [
                          BarLiveOrdersScreen(session: widget.session),
                          BarTabsScreen(session: widget.session),
                          BarMenuScreen(session: widget.session),
                          BarHistoryScreen(session: widget.session),
                        ],
                      ),
                    ),
                  ),
                  _BottomNav(
                    active: _tab,
                    onSelect: (t) => setState(() => _tab = t),
                    onLogout: _logout,
                    newCount: overview?.newCount ?? 0,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _BottomNav extends StatelessWidget {
  const _BottomNav({
    required this.active,
    required this.onSelect,
    required this.onLogout,
    required this.newCount,
  });

  final BarTabItem active;
  final ValueChanged<BarTabItem> onSelect;
  final VoidCallback onLogout;
  final int newCount;

  static const _items = [
    (BarTabItem.liveOrders, Icons.local_bar_rounded, 'Live Orders'),
    (BarTabItem.tabs, Icons.receipt_long_rounded, 'Tabs'),
    (BarTabItem.menu, Icons.menu_book_rounded, 'Menu'),
    (BarTabItem.history, Icons.history_rounded, 'History'),
  ];

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(24, 8, 24, 16),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.1),
          width: 0.8,
        ),
      ),
      child: Row(
        children: [
          for (final (item, icon, label) in _items)
            Expanded(
              child: _NavButton(
                icon: icon,
                label: label,
                selected: active == item,
                badge: item == BarTabItem.liveOrders && newCount > 0
                    ? newCount
                    : null,
                onTap: () => onSelect(item),
              ),
            ),
          _LogoutIcon(onTap: onLogout),
        ],
      ),
    );
  }
}

class _NavButton extends StatelessWidget {
  const _NavButton({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
    this.badge,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;
  final int? badge;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected
          ? AppColors.gold.withValues(alpha: 0.14)
          : Colors.transparent,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 10),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Stack(
                clipBehavior: Clip.none,
                children: [
                  Icon(
                    icon,
                    size: 22,
                    color: selected
                        ? AppColors.gold
                        : Colors.white.withValues(alpha: 0.55),
                  ),
                  if (badge != null)
                    Positioned(
                      top: -4,
                      right: -8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 5,
                          vertical: 1,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFFEF4444),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          badge! > 9 ? '9+' : '$badge',
                          style: AppTypography.style(
                            color: Colors.white,
                            fontSize: 9,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                label,
                style: AppTypography.style(
                  color: selected
                      ? AppColors.gold
                      : Colors.white.withValues(alpha: 0.55),
                  fontSize: 11,
                  fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _LogoutIcon extends StatelessWidget {
  const _LogoutIcon({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Icon(
            Icons.logout_rounded,
            size: 20,
            color: Colors.white.withValues(alpha: 0.5),
          ),
        ),
      ),
    );
  }
}
