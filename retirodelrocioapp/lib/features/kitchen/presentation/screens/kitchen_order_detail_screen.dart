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
import 'package:retirodelrocioapp/features/kitchen/presentation/dialogs/mark_ready_dialog.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/dialogs/set_eta_dialog.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/dialogs/void_item_dialog.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/kitchen_navigation.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/assign_station_sheet.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_nav_rail.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_scaffold.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_widgets.dart';

/// The Ticket Detail screen — items (with per-item void/cancel), status
/// progression (New → Preparing → Ready), and station assignment.
class KitchenOrderDetailScreen extends ConsumerStatefulWidget {
  const KitchenOrderDetailScreen({
    super.key,
    required this.session,
    required this.orderId,
  });

  final StaffSession session;
  final int orderId;

  @override
  ConsumerState<KitchenOrderDetailScreen> createState() =>
      _KitchenOrderDetailScreenState();
}

class _KitchenOrderDetailScreenState
    extends ConsumerState<KitchenOrderDetailScreen> {
  bool _busy = false;

  String get _token => widget.session.token;

  void _showFailure(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: const Color(0xFF7F1D1D),
        behavior: SnackBarBehavior.floating,
        content: Text(message, style: const TextStyle(color: Colors.white)),
      ),
    );
  }

  Future<void> _startPreparing() async {
    setState(() => _busy = true);
    try {
      await ref
          .read(kitchenActionsProvider(_token))
          .markPreparing(widget.orderId);
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

  Future<void> _setEta(KitchenOrder order) async {
    final minutes = await showSetEtaDialog(context, order);
    if (minutes == null || !mounted) return;

    setState(() => _busy = true);
    try {
      await ref.read(kitchenActionsProvider(_token)).setEta(order.id, minutes);
    } on KitchenException catch (e) {
      _showFailure(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _voidItem(KitchenOrder order, int index) async {
    final reason = await showVoidItemDialog(context, order.items[index]);
    if (reason == null) return;
    setState(() => _busy = true);
    try {
      await ref
          .read(kitchenActionsProvider(_token))
          .voidItem(order.id, index, reason: reason.isEmpty ? null : reason);
    } on KitchenException catch (e) {
      _showFailure(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) KitchenNavigation.afterLogout(context);
  }

  Future<void> _assign() async {
    final chef = await showAssignStationSheet(context, token: _token);
    if (chef == null) return;
    setState(() => _busy = true);
    try {
      await ref
          .read(kitchenActionsProvider(_token))
          .assignOrder(widget.orderId, chef.id);
    } on KitchenException catch (e) {
      _showFailure(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final orderAsync = ref.watch(
      kitchenOrderDetailProvider((_token, widget.orderId)),
    );
    final order = orderAsync.value;

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
      title: 'Ticket Detail',
      onBack: () => Navigator.of(context).pop(),
      body: order == null
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.gold),
            )
          : _content(order),
    );
  }

  Widget _content(KitchenOrder order) {
    final accent = kitchenBoardColumnColor(order.boardColumn);

    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  order.destinationLabel,
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              if (order.hasDrinks)
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: Icon(
                    Icons.local_bar_rounded,
                    size: 16,
                    color: Colors.white.withValues(alpha: 0.5),
                  ),
                ),
              KitchenStatusPill(label: order.boardColumnLabel, color: accent),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            '${order.code} · ${order.isPos ? 'Bar POS' : 'Room order'} · ${order.placedAtLabel ?? ''}',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.5),
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 20),
          Container(
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.05),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              children: [
                for (var i = 0; i < order.items.length; i++)
                  Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 12,
                    ),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                order.items[i].name,
                                style:
                                    AppTypography.style(
                                      color: order.items[i].voided
                                          ? Colors.white.withValues(alpha: 0.35)
                                          : Colors.white,
                                      fontSize: 14,
                                      fontWeight: FontWeight.w600,
                                    ).copyWith(
                                      decoration: order.items[i].voided
                                          ? TextDecoration.lineThrough
                                          : null,
                                    ),
                              ),
                              Text(
                                'Qty ${order.items[i].qty}${order.items[i].note != null && order.items[i].note!.isNotEmpty ? ' · ${order.items[i].note}' : ''}',
                                style: AppTypography.style(
                                  color: Colors.white.withValues(alpha: 0.5),
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                        if (!order.items[i].voided) ...[
                          Text(
                            '₦${order.items[i].lineTotal}',
                            style: AppTypography.style(
                              color: Colors.white,
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          IconButton(
                            icon: const Icon(
                              Icons.remove_circle_outline_rounded,
                              size: 18,
                              color: Color(0xFFDC2626),
                            ),
                            onPressed: _busy ? null : () => _voidItem(order, i),
                          ),
                        ],
                      ],
                    ),
                  ),
                Container(
                  height: 1,
                  color: Colors.white.withValues(alpha: 0.08),
                ),
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Total',
                        style: AppTypography.style(
                          color: Colors.white.withValues(alpha: 0.7),
                          fontSize: 14,
                        ),
                      ),
                      Text(
                        order.totalLabel,
                        style: AppTypography.style(
                          color: AppColors.gold,
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          if (order.boardColumn == 'new' ||
              order.boardColumn == 'preparing') ...[
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: BoxDecoration(
                color:
                    (order.estimatedReadyOverdue
                            ? const Color(0xFFEF4444)
                            : AppColors.gold)
                        .withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.schedule_rounded,
                    size: 18,
                    color: order.estimatedReadyOverdue
                        ? const Color(0xFFEF4444)
                        : AppColors.gold,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      order.estimatedReadyLabel == null
                          ? 'No ready time set yet.'
                          : order.estimatedReadyOverdue
                          ? 'Running late — was due ${order.estimatedReadyLabel}'
                          : 'Ready by ${order.estimatedReadyLabel}',
                      style: AppTypography.style(
                        color: order.estimatedReadyOverdue
                            ? const Color(0xFFEF4444)
                            : Colors.white,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  TextButton(
                    onPressed: _busy ? null : () => _setEta(order),
                    child: Text(
                      order.estimatedReadyLabel == null
                          ? 'Add Time'
                          : 'Increase Time',
                      style: AppTypography.style(
                        color: AppColors.gold,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _busy ? null : _assign,
                  icon: const Icon(
                    Icons.person_add_alt_rounded,
                    size: 16,
                    color: Colors.white70,
                  ),
                  label: Text(
                    order.assignedToName ?? 'Assign to Station',
                    style: AppTypography.style(
                      color: Colors.white70,
                      fontSize: 13,
                    ),
                  ),
                  style: OutlinedButton.styleFrom(
                    side: BorderSide(
                      color: Colors.white.withValues(alpha: 0.15),
                    ),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          if (order.boardColumn == 'new')
            _primaryButton(
              'Start Preparing',
              Icons.local_fire_department_rounded,
              _busy ? null : _startPreparing,
            )
          else if (order.boardColumn == 'preparing')
            MarkReadyButton(onTap: _busy ? () {} : () => _markReady(order))
          else
            Center(
              child: Text(
                'Ready to Serve',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.5),
                  fontSize: 14,
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _primaryButton(String label, IconData icon, VoidCallback? onTap) {
    return SizedBox(
      width: double.infinity,
      height: 50,
      child: Material(
        color: AppColors.gold,
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: onTap,
          child: Center(
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(icon, size: 18, color: const Color(0xFF0A0F1E)),
                const SizedBox(width: 8),
                Text(
                  label,
                  style: AppTypography.style(
                    color: const Color(0xFF0A0F1E),
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
