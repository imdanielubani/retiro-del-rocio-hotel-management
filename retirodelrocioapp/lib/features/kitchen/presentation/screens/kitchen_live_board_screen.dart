import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/kitchen/application/kitchen_providers.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_order.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_overview.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/kitchen_navigation.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/screens/kitchen_order_detail_screen.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_nav_rail.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_order_card.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_scaffold.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_widgets.dart';

/// The Live Board — New → Preparing → Ready, grouped from the exact same
/// `DiningOrder` rows the admin Kitchen dashboard manages, whether placed
/// via a guest's room tablet or rung up mixed with drinks on the Bar
/// Tablet's POS. This is the Kitchen Tablet's dashboard/home screen.
class KitchenLiveBoardScreen extends ConsumerStatefulWidget {
  const KitchenLiveBoardScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<KitchenLiveBoardScreen> createState() =>
      _KitchenLiveBoardScreenState();
}

class _KitchenLiveBoardScreenState
    extends ConsumerState<KitchenLiveBoardScreen> {
  String _search = '';
  String _column = ''; // '' | new | preparing | ready

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) KitchenNavigation.afterLogout(context);
  }

  List<KitchenOrder> _filtered(List<KitchenOrder> orders) {
    final q = _search.trim().toLowerCase();
    return orders.where((o) {
      final matchesColumn = _column.isEmpty || o.boardColumn == _column;
      final matchesSearch =
          q.isEmpty ||
          o.code.toLowerCase().contains(q) ||
          (o.tableLabel?.toLowerCase().contains(q) ?? false) ||
          (o.roomLabel?.toLowerCase().contains(q) ?? false) ||
          (o.guestName?.toLowerCase().contains(q) ?? false);
      return matchesColumn && matchesSearch;
    }).toList();
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
    final ordersAsync = ref.watch(kitchenLiveOrdersProvider(_token));
    final orders = ordersAsync.value ?? const <KitchenOrder>[];
    final overview = ref.watch(kitchenOverviewProvider(_token)).value;
    ref.watch(kitchenRealtimeProvider(_token));

    return KitchenScaffold(
      session: widget.session,
      active: KitchenNavItem.liveBoard,
      onNav: (item) => KitchenNavigation.select(
        context,
        widget.session,
        item,
        current: KitchenNavItem.liveBoard,
      ),
      onLogout: _logout,
      hasAlert: (overview?.newCount ?? 0) > 0,
      title: 'Live Board',
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _stats(overview ?? KitchenOverview.empty),
          const SizedBox(height: 18),
          KitchenSearchField(
            hintText: 'Search ticket, table or room…',
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
                  selected: _column.isEmpty,
                  onTap: () => setState(() => _column = ''),
                ),
                KitchenFilterChip(
                  label: 'New',
                  selected: _column == 'new',
                  onTap: () => setState(() => _column = 'new'),
                ),
                KitchenFilterChip(
                  label: 'Preparing',
                  selected: _column == 'preparing',
                  onTap: () => setState(() => _column = 'preparing'),
                ),
                KitchenFilterChip(
                  label: 'Ready',
                  selected: _column == 'ready',
                  onTap: () => setState(() => _column = 'ready'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Expanded(
            child: ordersAsync.when(
              data: (data) => _board(_filtered(data)),
              loading: () => orders.isNotEmpty
                  ? _board(_filtered(orders))
                  : const Center(
                      child: CircularProgressIndicator(color: AppColors.gold),
                    ),
              error: (_, _) => orders.isNotEmpty
                  ? _board(_filtered(orders))
                  : const KitchenEmptyState(
                      icon: Icons.wifi_off_rounded,
                      message: 'Could not load tickets. Pull to retry.',
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _stats(KitchenOverview overview) {
    return Row(
      children: [
        Expanded(
          child: KitchenStatCard(
            label: 'NEW',
            value: '${overview.newCount}',
            accent: kitchenBoardColumnColor('new'),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: KitchenStatCard(
            label: 'PREPARING',
            value: '${overview.preparingCount}',
            accent: kitchenBoardColumnColor('preparing'),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: KitchenStatCard(
            label: 'READY',
            value: '${overview.readyCount}',
            accent: kitchenBoardColumnColor('ready'),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: KitchenStatCard(
            label: 'SERVED TODAY',
            value: '${overview.servedToday}',
            accent: AppColors.gold,
          ),
        ),
      ],
    );
  }

  Widget _board(List<KitchenOrder> orders) {
    if (orders.isEmpty) {
      return const KitchenEmptyState(
        icon: Icons.restaurant_menu_rounded,
        message: 'No active tickets — all caught up.',
      );
    }

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(kitchenLiveOrdersProvider(_token)),
      child: GridView.builder(
        physics: const AlwaysScrollableScrollPhysics(),
        gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
          maxCrossAxisExtent: 280,
          mainAxisExtent: 112,
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
        ),
        itemCount: orders.length,
        itemBuilder: (_, i) => KitchenOrderCard(
          order: orders[i],
          onTap: () => _openDetail(orders[i]),
        ),
      ),
    );
  }
}
