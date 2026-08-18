import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/kitchen/application/kitchen_providers.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_order.dart';
import 'package:retirodelrocioapp/features/kitchen/notifications/application/kitchen_notification_providers.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/kitchen_navigation.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/screens/kitchen_order_detail_screen.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_nav_rail.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_order_card.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_scaffold.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_widgets.dart';

/// History / Recall — served and cancelled food tickets, searchable by
/// guest, order code, table or room.
class KitchenHistoryScreen extends ConsumerStatefulWidget {
  const KitchenHistoryScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<KitchenHistoryScreen> createState() =>
      _KitchenHistoryScreenState();
}

class _KitchenHistoryScreenState extends ConsumerState<KitchenHistoryScreen> {
  String _search = '';

  /// '' | pos | room — mirrors the Bar Tablet's own History filter
  /// (All / Settled Tabs / Room Orders): a ticket either rode along on a
  /// Bar Tablet POS tab (settled once the tab is closed, regardless of the
  /// ticket's own status — {@see KitchenController::history()}), or it was
  /// a guest-tablet room-service order.
  String _source = '';

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) KitchenNavigation.afterLogout(context);
  }

  Future<void> _openDetail(KitchenOrder order) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => KitchenOrderDetailScreen(
          session: widget.session,
          orderId: order.id,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final historyAsync = ref.watch(kitchenHistoryProvider(_token));
    final orders = historyAsync.value ?? const <KitchenOrder>[];

    final q = _search.trim().toLowerCase();
    final filtered = orders
        .where(
          (o) =>
              (_source.isEmpty || (_source == 'pos') == o.isPos) &&
              (q.isEmpty ||
                  o.code.toLowerCase().contains(q) ||
                  (o.tableLabel?.toLowerCase().contains(q) ?? false) ||
                  (o.roomLabel?.toLowerCase().contains(q) ?? false) ||
                  (o.guestName?.toLowerCase().contains(q) ?? false)),
        )
        .toList();
    final unreadNotifications = ref.watch(
      kitchenUnreadNotificationsProvider(_token),
    );

    return KitchenScaffold(
      session: widget.session,
      active: KitchenNavItem.history,
      onNav: (item) => KitchenNavigation.select(
        context,
        widget.session,
        item,
        current: KitchenNavItem.history,
      ),
      onLogout: _logout,
      hasAlert: unreadNotifications > 0,
      title: 'History',
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          KitchenSearchField(
            hintText: 'Search ticket, table, room or guest…',
            onChanged: (v) => setState(() => _search = v),
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 36,
            child: ListView(
              scrollDirection: Axis.horizontal,
              children: [
                KitchenFilterChip(
                  label: 'All',
                  selected: _source.isEmpty,
                  onTap: () => setState(() => _source = ''),
                ),
                KitchenFilterChip(
                  label: 'Settled',
                  selected: _source == 'pos',
                  onTap: () => setState(() => _source = 'pos'),
                ),
                KitchenFilterChip(
                  label: 'Room Orders',
                  selected: _source == 'room',
                  onTap: () => setState(() => _source = 'room'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Expanded(
            child: historyAsync.when(
              data: (_) => _list(filtered),
              loading: () => orders.isEmpty
                  ? const Center(
                      child: CircularProgressIndicator(color: AppColors.gold),
                    )
                  : _list(filtered),
              error: (_, _) => orders.isEmpty
                  ? const KitchenEmptyState(
                      icon: Icons.wifi_off_rounded,
                      message: 'Could not load history.',
                    )
                  : _list(filtered),
            ),
          ),
        ],
      ),
    );
  }

  Widget _list(List<KitchenOrder> orders) {
    if (orders.isEmpty) {
      return const KitchenEmptyState(
        icon: Icons.history_rounded,
        message: 'Nothing served yet.',
      );
    }

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(kitchenHistoryProvider(_token)),
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        itemCount: orders.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, i) => KitchenOrderCard(
          order: orders[i],
          onTap: () => _openDetail(orders[i]),
        ),
      ),
    );
  }
}
