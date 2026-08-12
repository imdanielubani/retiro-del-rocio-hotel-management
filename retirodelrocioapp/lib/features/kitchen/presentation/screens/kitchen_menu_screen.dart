import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/kitchen/application/kitchen_providers.dart';
import 'package:retirodelrocioapp/features/kitchen/data/kitchen_repository.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_menu_item.dart';
import 'package:retirodelrocioapp/features/kitchen/notifications/application/kitchen_notification_providers.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/kitchen_navigation.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_nav_rail.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_scaffold.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_widgets.dart';

/// Menu Availability — the dish catalog the admin Kitchen dashboard
/// manages, with a quick 86 / restore toggle for when something runs out.
class KitchenMenuScreen extends ConsumerStatefulWidget {
  const KitchenMenuScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<KitchenMenuScreen> createState() => _KitchenMenuScreenState();
}

class _KitchenMenuScreenState extends ConsumerState<KitchenMenuScreen> {
  String _search = '';
  int? _busyId;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) KitchenNavigation.afterLogout(context);
  }

  Future<void> _toggle(KitchenMenuItem item) async {
    final was86d = !item.isActive;
    final confirmed = await _confirm86(item, restoring: was86d);
    if (!confirmed) return;

    setState(() => _busyId = item.id);
    try {
      await ref
          .read(kitchenActionsProvider(_token))
          .toggleMenuItemAvailability(item.id);
    } on KitchenException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            backgroundColor: const Color(0xFF7F1D1D),
            behavior: SnackBarBehavior.floating,
            content: Text(e.message),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  /// The 86-item confirm — a dish is temporarily out and needs pulling from
  /// the guest/waiter-facing menu (or restored once stock is back).
  Future<bool> _confirm86(KitchenMenuItem item, {required bool restoring}) {
    return showDialog<bool>(
      context: context,
      barrierColor: Colors.black.withValues(alpha: 0.4),
      builder: (_) => AlertDialog(
        backgroundColor: const Color(0xFF1A1712),
        title: Text(
          restoring ? 'Restore Dish?' : '86 This Dish?',
          style: AppTypography.style(color: Colors.white, fontSize: 18),
        ),
        content: Text(
          restoring
              ? '${item.name} will be available to order again.'
              : '${item.name} will be pulled from the menu everywhere it\'s ordered from.',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.6),
            fontSize: 14,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(restoring ? 'Restore' : '86 It'),
          ),
        ],
      ),
    ).then((v) => v ?? false);
  }

  @override
  Widget build(BuildContext context) {
    final menuAsync = ref.watch(kitchenMenuProvider(_token));
    final menu = menuAsync.value ?? const <KitchenMenuItem>[];
    final filtered = menu
        .where(
          (m) =>
              _search.isEmpty ||
              m.name.toLowerCase().contains(_search.toLowerCase()),
        )
        .toList();
    final unreadNotifications = ref.watch(
      kitchenUnreadNotificationsProvider(_token),
    );

    return KitchenScaffold(
      session: widget.session,
      active: KitchenNavItem.menu,
      onNav: (item) => KitchenNavigation.select(
        context,
        widget.session,
        item,
        current: KitchenNavItem.menu,
      ),
      onLogout: _logout,
      hasAlert: unreadNotifications > 0,
      title: 'Menu Availability',
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          KitchenSearchField(
            hintText: 'Search dishes…',
            onChanged: (v) => setState(() => _search = v),
          ),
          const SizedBox(height: 14),
          Expanded(
            child: menuAsync.when(
              data: (_) => _list(filtered),
              loading: () => menu.isNotEmpty
                  ? _list(filtered)
                  : const Center(
                      child: CircularProgressIndicator(color: AppColors.gold),
                    ),
              error: (_, _) => menu.isNotEmpty
                  ? _list(filtered)
                  : const KitchenEmptyState(
                      icon: Icons.wifi_off_rounded,
                      message: 'Could not load the menu.',
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _list(List<KitchenMenuItem> items) {
    if (items.isEmpty) {
      return const KitchenEmptyState(
        icon: Icons.restaurant_menu_outlined,
        message: 'No dishes match your search.',
      );
    }

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(kitchenMenuProvider(_token)),
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        itemCount: items.length,
        separatorBuilder: (_, _) => const SizedBox(height: 8),
        itemBuilder: (_, i) {
          final item = items[i];
          return Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.05),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Row(
              children: [
                Icon(
                  Icons.restaurant_rounded,
                  color: item.isActive
                      ? AppColors.gold
                      : Colors.white.withValues(alpha: 0.3),
                  size: 20,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.name,
                        style: AppTypography.style(
                          color: Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      Text(
                        '${item.categoryLabel} · ${item.priceLabel}',
                        style: AppTypography.style(
                          color: Colors.white.withValues(alpha: 0.5),
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                if (!item.isActive)
                  Padding(
                    padding: const EdgeInsets.only(right: 10),
                    child: KitchenStatusPill(
                      label: '86\'D',
                      color: const Color(0xFFDC2626),
                    ),
                  ),
                _busyId == item.id
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: AppColors.gold,
                        ),
                      )
                    : Switch(
                        value: item.isActive,
                        onChanged: (_) => _toggle(item),
                        activeThumbColor: AppColors.gold,
                      ),
              ],
            ),
          );
        },
      ),
    );
  }
}
