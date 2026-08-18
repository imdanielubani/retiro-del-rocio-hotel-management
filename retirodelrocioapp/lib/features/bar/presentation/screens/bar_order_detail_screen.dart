import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/bar/application/bar_providers.dart';
import 'package:retirodelrocioapp/features/bar/data/bar_repository.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_order.dart';
import 'package:retirodelrocioapp/features/bar/notifications/application/bar_notification_providers.dart';
import 'package:retirodelrocioapp/features/bar/notifications/presentation/screens/bar_notification_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/dialogs/mark_served_dialog.dart';
import 'package:retirodelrocioapp/features/bar/presentation/dialogs/void_item_dialog.dart';
import 'package:retirodelrocioapp/features/bar/presentation/screens/bar_chat_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/screens/bar_intercom_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/assign_bartender_sheet.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_scaffold.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_widgets.dart';

/// The Order Detail screen — items (with per-item void), status
/// progression, age-verification state, and bartender assignment.
class BarOrderDetailScreen extends ConsumerStatefulWidget {
  const BarOrderDetailScreen({
    super.key,
    required this.session,
    required this.orderId,
  });

  final StaffSession session;
  final int orderId;

  @override
  ConsumerState<BarOrderDetailScreen> createState() =>
      _BarOrderDetailScreenState();
}

class _BarOrderDetailScreenState extends ConsumerState<BarOrderDetailScreen> {
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
      await ref.read(barActionsProvider(_token)).markPreparing(widget.orderId);
    } on BarException catch (e) {
      _showFailure(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _serve(BarOrder order) async {
    setState(() => _busy = true);
    await handleMarkServed(context, ref, _token, order);
    if (mounted) setState(() => _busy = false);
  }

  Future<void> _markOnTheWay() async {
    setState(() => _busy = true);
    try {
      await ref.read(barActionsProvider(_token)).markOnTheWay(widget.orderId);
    } on BarException catch (e) {
      _showFailure(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _voidItem(BarOrder order, int index) async {
    final reason = await showVoidItemDialog(context, order.items[index]);
    if (reason == null) return;
    setState(() => _busy = true);
    try {
      await ref
          .read(barActionsProvider(_token))
          .voidItem(order.id, index, reason: reason.isEmpty ? null : reason);
    } on BarException catch (e) {
      _showFailure(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _assign() async {
    final bartender = await showAssignBartenderSheet(context, token: _token);
    if (bartender == null) return;
    setState(() => _busy = true);
    try {
      await ref
          .read(barActionsProvider(_token))
          .assignOrder(widget.orderId, bartender.id);
    } on BarException catch (e) {
      _showFailure(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _openNotifications() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => BarNotificationScreen(session: widget.session),
      ),
    );
  }

  void _openChat() {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => BarChatScreen(session: widget.session)),
    );
  }

  void _openIntercom() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => BarIntercomScreen(session: widget.session),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final orderAsync = ref.watch(
      barOrderDetailProvider((_token, widget.orderId)),
    );
    final order = orderAsync.value;
    final unreadNotifications = ref.watch(
      barUnreadNotificationsProvider(_token),
    );

    return BarScaffold(
      session: widget.session,
      title: 'Order Detail',
      onBack: () => Navigator.of(context).pop(),
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      onChat: _openChat,
      onIntercom: _openIntercom,
      body: order == null
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.gold),
            )
          : _content(order),
    );
  }

  Widget _content(BarOrder order) {
    final accent = barBoardColumnColor(order.boardColumn);

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
              if (order.isVip)
                const Padding(
                  padding: EdgeInsets.only(right: 8),
                  child: VipBadge(),
                ),
              BarStatusPill(label: order.boardColumnLabel, color: accent),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            '${order.code} · ${order.isPos ? 'POS' : 'Room order'} · ${order.placedAtLabel ?? ''}',
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
                              Row(
                                children: [
                                  Text(
                                    order.items[i].name,
                                    style:
                                        AppTypography.style(
                                          color: order.items[i].voided
                                              ? Colors.white.withValues(
                                                  alpha: 0.35,
                                                )
                                              : Colors.white,
                                          fontSize: 14,
                                          fontWeight: FontWeight.w600,
                                        ).copyWith(
                                          decoration: order.items[i].voided
                                              ? TextDecoration.lineThrough
                                              : null,
                                        ),
                                  ),
                                  if (order.items[i].isAlcoholic)
                                    Padding(
                                      padding: const EdgeInsets.only(left: 6),
                                      child: Icon(
                                        Icons.local_bar_rounded,
                                        size: 13,
                                        color: Colors.amber.withValues(
                                          alpha: 0.8,
                                        ),
                                      ),
                                    ),
                                ],
                              ),
                              Text(
                                'Qty ${order.items[i].qty}${order.items[i].note != null && order.items[i].note!.isNotEmpty ? ' · ${order.items[i].note}' : ''}',
                                style: AppTypography.style(
                                  color: Colors.white.withValues(alpha: 0.5),
                                  fontSize: 12,
                                ),
                              ),
                              if (order.items[i].allergies != null &&
                                  order.items[i].allergies!.isNotEmpty)
                                Padding(
                                  padding: const EdgeInsets.only(top: 4),
                                  child: Row(
                                    children: [
                                      Icon(
                                        Icons.warning_amber_rounded,
                                        size: 13,
                                        color: Colors.amber.withValues(
                                          alpha: 0.85,
                                        ),
                                      ),
                                      const SizedBox(width: 5),
                                      Expanded(
                                        child: Text(
                                          'Allergy: ${order.items[i].allergies}',
                                          style: AppTypography.style(
                                            color: Colors.amber.withValues(
                                              alpha: 0.85,
                                            ),
                                            fontSize: 12,
                                            fontWeight: FontWeight.w600,
                                          ),
                                        ),
                                      ),
                                    ],
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
          if (order.hasFood &&
              order.estimatedReadyLabel != null &&
              (order.boardColumn == 'preparing' ||
                  order.boardColumn == 'ready')) ...[
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
                    order.boardColumn == 'ready'
                        ? Icons.restaurant_rounded
                        : Icons.schedule_rounded,
                    size: 18,
                    color: order.estimatedReadyOverdue
                        ? const Color(0xFFEF4444)
                        : AppColors.gold,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      order.boardColumn == 'ready'
                          ? 'The kitchen has this ready — go pick it up.'
                          : order.estimatedReadyOverdue
                          ? 'Running late — was due ${order.estimatedReadyLabel}'
                          : 'The kitchen says ready by ${order.estimatedReadyLabel}',
                      style: AppTypography.style(
                        color: order.estimatedReadyOverdue
                            ? const Color(0xFFEF4444)
                            : Colors.white,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
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
                    order.assignedToName ?? 'Assign Bartender',
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
          if (order.boardColumn == 'new' && order.hasFood)
            _primaryButton(
              'Start Preparing',
              Icons.local_fire_department_rounded,
              _busy ? null : _startPreparing,
            )
          else if (order.boardColumn == 'new' && !order.hasFood && !order.isPos)
            Column(
              children: [
                _primaryButton(
                  'On the Way to Room',
                  Icons.delivery_dining_rounded,
                  _busy ? null : _markOnTheWay,
                ),
                const SizedBox(height: 10),
                TextButton(
                  onPressed: _busy ? null : () => _serve(order),
                  child: Text(
                    'Serve Directly',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.6),
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            )
          else if (order.boardColumn == 'ready' &&
              order.hasFood &&
              order.status == 'ready' &&
              order.isPos)
            // The kitchen has this dine-in ticket cooked and waiting at the
            // pass — picking it up to walk it to the table tells the
            // kitchen's own board it's been claimed, rather than it
            // silently sitting there until served. A room-service ticket
            // (no bar tab) stays Kitchen's own dispatch instead.
            Column(
              children: [
                _primaryButton(
                  'On the Way',
                  Icons.delivery_dining_rounded,
                  _busy ? null : _markOnTheWay,
                ),
                const SizedBox(height: 10),
                TextButton(
                  onPressed: _busy ? null : () => _serve(order),
                  child: Text(
                    'Serve Directly',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.6),
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            )
          else if (order.boardColumn == 'new' ||
              order.boardColumn == 'preparing' ||
              order.boardColumn == 'ready')
            ServeButton(
              needsAgeCheck: order.needsAgeCheck,
              onTap: _busy ? () {} : () => _serve(order),
            )
          else
            Center(
              child: Text(
                'Served',
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
