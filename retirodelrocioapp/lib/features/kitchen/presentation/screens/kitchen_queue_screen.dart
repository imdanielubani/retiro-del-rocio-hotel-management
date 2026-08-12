import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/kitchen/application/kitchen_providers.dart';
import 'package:retirodelrocioapp/features/kitchen/data/kitchen_repository.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_order.dart';
import 'package:retirodelrocioapp/features/kitchen/notifications/application/kitchen_notification_providers.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/dialogs/mark_ready_dialog.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/kitchen_navigation.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/screens/kitchen_order_detail_screen.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_nav_rail.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_scaffold.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_widgets.dart';

/// Order Queue / Expo view — every active ticket in a single oldest-first
/// line, the way an expo station reads the pass: what's been waiting
/// longest is first, with the next status action one tap away without
/// opening the full ticket. The Live Board (grid, grouped by column) and
/// this Queue (flat, sorted by wait time) are two views over the exact same
/// tickets.
class KitchenQueueScreen extends ConsumerStatefulWidget {
  const KitchenQueueScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<KitchenQueueScreen> createState() => _KitchenQueueScreenState();
}

class _KitchenQueueScreenState extends ConsumerState<KitchenQueueScreen> {
  bool _busy = false;

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

  Future<void> _startPreparing(KitchenOrder order) async {
    setState(() => _busy = true);
    try {
      await ref.read(kitchenActionsProvider(_token)).markPreparing(order.id);
    } on KitchenException catch (e) {
      _showFailure(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _markReady(KitchenOrder order) async {
    setState(() => _busy = true);
    await handleMarkReady(context, ref, _token, order);
    if (mounted) setState(() => _busy = false);
  }

  void _showFailure(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: const Color(0xFF7F1D1D),
        behavior: SnackBarBehavior.floating,
        content: Text(message),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ordersAsync = ref.watch(kitchenLiveOrdersProvider(_token));
    final orders = List<KitchenOrder>.from(
      ordersAsync.value ?? const <KitchenOrder>[],
    )..sort((a, b) => a.id.compareTo(b.id)); // oldest first
    final unreadNotifications = ref.watch(
      kitchenUnreadNotificationsProvider(_token),
    );

    return KitchenScaffold(
      session: widget.session,
      active: KitchenNavItem.queue,
      onNav: (item) => KitchenNavigation.select(
        context,
        widget.session,
        item,
        current: KitchenNavItem.queue,
      ),
      onLogout: _logout,
      hasAlert: unreadNotifications > 0,
      title: 'Queue',
      body: ordersAsync.when(
        data: (_) => _queue(orders),
        loading: () => orders.isNotEmpty
            ? _queue(orders)
            : const Center(
                child: CircularProgressIndicator(color: AppColors.gold),
              ),
        error: (_, _) => orders.isNotEmpty
            ? _queue(orders)
            : const KitchenEmptyState(
                icon: Icons.wifi_off_rounded,
                message: 'Could not load the queue. Pull to retry.',
              ),
      ),
    );
  }

  Widget _queue(List<KitchenOrder> orders) {
    if (orders.isEmpty) {
      return const KitchenEmptyState(
        icon: Icons.done_all_rounded,
        message: 'No active tickets — all caught up.',
      );
    }

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(kitchenLiveOrdersProvider(_token)),
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        itemCount: orders.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, i) => _row(orders[i], position: i + 1),
      ),
    );
  }

  Widget _row(KitchenOrder order, {required int position}) {
    final accent = kitchenBoardColumnColor(order.boardColumn);

    return Material(
      color: Colors.white.withValues(alpha: 0.05),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: () => _openDetail(order),
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            children: [
              Container(
                width: 30,
                height: 30,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.06),
                  shape: BoxShape.circle,
                ),
                child: Text(
                  '$position',
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.6),
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(
                          order.destinationLabel,
                          style: AppTypography.style(
                            color: Colors.white,
                            fontSize: 14,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(width: 8),
                        KitchenStatusPill(
                          label: order.boardColumnLabel,
                          color: accent,
                        ),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${order.itemsLabel} · ${order.placedAtShort ?? ''}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.55),
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              _action(order),
            ],
          ),
        ),
      ),
    );
  }

  Widget _action(KitchenOrder order) {
    if (order.boardColumn == 'new') {
      return Material(
        color: AppColors.gold,
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: _busy ? null : () => _startPreparing(order),
          child: const Padding(
            padding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            child: Text(
              'Start',
              style: TextStyle(
                color: Color(0xFF0A0F1E),
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ),
      );
    }
    if (order.boardColumn == 'preparing') {
      return Material(
        color: const Color(0xFF16A34A).withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: _busy ? null : () => _markReady(order),
          child: const Padding(
            padding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            child: Text(
              'Ready',
              style: TextStyle(
                color: Color(0xFF16A34A),
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ),
      );
    }
    return const Icon(
      Icons.check_circle_rounded,
      color: Color(0xFF16A34A),
      size: 20,
    );
  }
}
