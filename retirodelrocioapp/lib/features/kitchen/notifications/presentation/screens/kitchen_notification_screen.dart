import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_notification.dart';
import 'package:retirodelrocioapp/features/kitchen/notifications/application/kitchen_notification_providers.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/kitchen_navigation.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/screens/kitchen_order_detail_screen.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_nav_rail.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_scaffold.dart';

enum _Filter { all, unread }

/// The Kitchen Tablet's notification feed (`GET /kitchen/notifications`):
/// an unread count, All/Unread filter chips, and the list.
/// `kitchenNotificationChimeProvider` (watched by every kitchen screen via
/// [KitchenScaffold]) is what rings the chime and lights the bell's badge —
/// this screen just renders the feed and lets the chef act on it.
class KitchenNotificationScreen extends ConsumerStatefulWidget {
  const KitchenNotificationScreen({
    super.key,
    required this.session,
    this.current = KitchenNavItem.liveBoard,
  });

  final StaffSession session;

  /// The kitchen screen the chef was on before opening the bell.
  final KitchenNavItem current;

  @override
  ConsumerState<KitchenNotificationScreen> createState() =>
      _KitchenNotificationScreenState();
}

class _KitchenNotificationScreenState
    extends ConsumerState<KitchenNotificationScreen> {
  _Filter _filter = _Filter.all;
  int? _busyId;
  bool _markingAll = false;

  String get _token => widget.session.token;

  List<KitchenNotification> _visible(List<KitchenNotification> all) {
    if (_filter == _Filter.unread) return all.where((n) => !n.read).toList();
    return all;
  }

  Future<void> _markAllRead() async {
    setState(() => _markingAll = true);
    await ref.read(kitchenNotificationActionsProvider(_token)).markAllRead();
    if (mounted) setState(() => _markingAll = false);
  }

  Future<void> _open(KitchenNotification n) async {
    if (!n.read) {
      setState(() => _busyId = n.id);
      await ref.read(kitchenNotificationActionsProvider(_token)).markRead(n.id);
      if (mounted) setState(() => _busyId = null);
    }
    if (n.diningOrderId != null && mounted) {
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => KitchenOrderDetailScreen(
            session: widget.session,
            orderId: n.diningOrderId!,
          ),
        ),
      );
    }
  }

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) KitchenNavigation.afterLogout(context);
  }

  void _onNav(KitchenNavItem item) {
    KitchenNavigation.select(
      context,
      widget.session,
      item,
      current: widget.current,
    );
  }

  @override
  Widget build(BuildContext context) {
    final notificationsAsync = ref.watch(kitchenNotificationsProvider(_token));
    final notifications =
        notificationsAsync.value ?? const <KitchenNotification>[];
    final unreadCount = notifications.where((n) => !n.read).length;

    return KitchenScaffold(
      session: widget.session,
      active: widget.current,
      onNav: _onNav,
      onLogout: _logout,
      onBack: () => Navigator.of(context).maybePop(),
      title: 'Notifications',
      trailing: _markAllReadButton(unreadCount),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _unreadPill(unreadCount),
          const SizedBox(height: 16),
          _filterChips(unreadCount),
          const SizedBox(height: 19),
          Expanded(
            child: notificationsAsync.when(
              data: (data) => _list(data),
              loading: () => notifications.isNotEmpty
                  ? _list(notifications)
                  : const Center(
                      child: CircularProgressIndicator(color: AppColors.gold),
                    ),
              error: (_, _) => notifications.isNotEmpty
                  ? _list(notifications)
                  : _errorState(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _unreadPill(int unreadCount) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 8,
          height: 8,
          decoration: const BoxDecoration(
            color: AppColors.gold,
            shape: BoxShape.circle,
          ),
        ),
        const SizedBox(width: 8),
        Text(
          unreadCount == 1 ? '1 unread' : '$unreadCount unread',
          style: AppTypography.style(color: AppColors.gold, fontSize: 15),
        ),
      ],
    );
  }

  Widget _markAllReadButton(int unreadCount) {
    final enabled = unreadCount > 0 && !_markingAll;
    return Material(
      color: Colors.white.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: enabled ? _markAllRead : null,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 17, vertical: 11),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.2),
              width: 0.8,
            ),
          ),
          child: _markingAll
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: AppColors.gold,
                  ),
                )
              : Text(
                  'Mark all read',
                  style: AppTypography.style(
                    color: enabled
                        ? AppColors.gold
                        : AppColors.gold.withValues(alpha: 0.4),
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                  ),
                ),
        ),
      ),
    );
  }

  Widget _filterChips(int unreadCount) {
    return SizedBox(
      height: 41,
      child: ListView(
        scrollDirection: Axis.horizontal,
        children: [
          _chip(label: 'All', value: _Filter.all),
          const SizedBox(width: 8),
          _chip(label: 'Unread', value: _Filter.unread, badge: unreadCount),
        ],
      ),
    );
  }

  Widget _chip({required String label, required _Filter value, int? badge}) {
    final selected = _filter == value;

    return Material(
      color: selected ? AppColors.gold : Colors.white.withValues(alpha: 0.07),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => setState(() => _filter = value),
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 17, vertical: 9),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.5)
                  : Colors.white.withValues(alpha: 0.2),
              width: 0.8,
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                label,
                style: AppTypography.style(
                  color: selected
                      ? const Color(0xFF0A0F1E)
                      : Colors.white.withValues(alpha: 0.6),
                  fontSize: 13,
                  fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
                ),
              ),
              if (badge != null && badge > 0) ...[
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 6,
                    vertical: 2,
                  ),
                  decoration: const BoxDecoration(
                    color: Color(0xFFEF4444),
                    shape: BoxShape.circle,
                  ),
                  child: Text(
                    '$badge',
                    style: AppTypography.style(
                      color: Colors.white,
                      fontSize: 12,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _list(List<KitchenNotification> all) {
    final items = _visible(all);

    if (items.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.notifications_off_outlined,
              size: 32,
              color: Colors.white.withValues(alpha: 0.3),
            ),
            const SizedBox(height: 12),
            Text(
              'No notifications here',
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.4),
                fontSize: 14,
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async =>
          ref.invalidate(kitchenNotificationsProvider(_token)),
      child: ListView.separated(
        padding: EdgeInsets.zero,
        itemCount: items.length,
        separatorBuilder: (_, _) => const SizedBox(height: 19),
        itemBuilder: (_, i) => _card(items[i]),
      ),
    );
  }

  Widget _card(KitchenNotification n) {
    final busy = _busyId == n.id;

    return Material(
      color: n.read
          ? Colors.white.withValues(alpha: 0.04)
          : Colors.white.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: busy ? null : () => _open(n),
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(21),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: n.read
                  ? Colors.white.withValues(alpha: 0.2)
                  : AppColors.gold.withValues(alpha: 0.14),
              width: 0.8,
            ),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.gold.withValues(alpha: 0.09),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: busy
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: AppColors.gold,
                        ),
                      )
                    : const Icon(
                        Icons.restaurant_rounded,
                        size: 20,
                        color: AppColors.gold,
                      ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            n.title,
                            style: AppTypography.style(
                              color: n.read
                                  ? Colors.white.withValues(alpha: 0.7)
                                  : Colors.white,
                              fontSize: 14,
                              fontWeight: n.read
                                  ? FontWeight.w500
                                  : FontWeight.w600,
                            ),
                          ),
                        ),
                        if (!n.read) ...[
                          const SizedBox(width: 8),
                          Container(
                            width: 8,
                            height: 8,
                            decoration: const BoxDecoration(
                              color: AppColors.gold,
                              shape: BoxShape.circle,
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      n.message,
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.45),
                        fontSize: 13,
                      ),
                    ),
                    if (n.orderCode != null) ...[
                      const SizedBox(height: 6),
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.receipt_long_rounded,
                            size: 13,
                            color: AppColors.gold.withValues(alpha: 0.6),
                          ),
                          const SizedBox(width: 5),
                          Text(
                            n.orderCode!,
                            style: AppTypography.style(
                              color: AppColors.gold.withValues(alpha: 0.75),
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ],
                    if (n.createdLabel != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        n.createdLabel!,
                        style: AppTypography.style(
                          color: Colors.white.withValues(alpha: 0.25),
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _errorState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.cloud_off_rounded,
            size: 30,
            color: Colors.white.withValues(alpha: 0.4),
          ),
          const SizedBox(height: 12),
          Text(
            'Could not load the notifications.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.6),
              fontSize: 15,
            ),
          ),
          const SizedBox(height: 16),
          Material(
            color: AppColors.gold,
            borderRadius: BorderRadius.circular(12),
            child: InkWell(
              onTap: () => ref.invalidate(kitchenNotificationsProvider(_token)),
              borderRadius: BorderRadius.circular(12),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 24,
                  vertical: 12,
                ),
                child: Text(
                  'Retry',
                  style: AppTypography.style(
                    color: AppColors.onGold,
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
