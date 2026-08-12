import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/bar/application/bar_providers.dart';
import 'package:retirodelrocioapp/features/bar/data/bar_repository.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_tab.dart';
import 'package:retirodelrocioapp/features/bar/notifications/application/bar_notification_providers.dart';
import 'package:retirodelrocioapp/features/bar/notifications/presentation/screens/bar_notification_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/dialogs/close_tab_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/bar/presentation/screens/bar_chat_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/screens/bar_intercom_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/screens/bar_pos_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_order_card.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_scaffold.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_widgets.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/settle_payment_sheet.dart';

/// Tab Detail — the running order list on this tab, adding more drinks via
/// the full Point of Sale screen, and closing/settling it.
class BarTabDetailScreen extends ConsumerStatefulWidget {
  const BarTabDetailScreen({
    super.key,
    required this.session,
    required this.tabId,
  });

  final StaffSession session;
  final int tabId;

  @override
  ConsumerState<BarTabDetailScreen> createState() => _BarTabDetailScreenState();
}

class _BarTabDetailScreenState extends ConsumerState<BarTabDetailScreen> {
  bool _busy = false;

  String get _token => widget.session.token;

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

  Future<void> _addDrink(BarTab tab) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => BarPosScreen(
          session: widget.session,
          tabId: tab.id,
          tabLabel: tab.tableLabel ?? tab.code,
        ),
      ),
    );
  }

  Future<void> _toggleVip(BarTab tab) async {
    try {
      await ref.read(barActionsProvider(_token)).toggleVip(tab.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: const Color(0xFF1B1D1A),
          behavior: SnackBarBehavior.floating,
          content: Text(
            tab.isVip ? 'VIP flag removed.' : 'Marked as VIP guest.',
          ),
        ),
      );
    } on BarException catch (e) {
      _showFailure(e.message);
    }
  }

  Future<void> _closeTab(BarTab tab) async {
    final confirmed = await showCloseTabConfirmDialog(context, tab);
    if (!confirmed || !mounted) return;

    final result = await showSettlePaymentSheet(
      context,
      tab: tab,
      token: _token,
    );
    if (result == null || !mounted) return;

    setState(() => _busy = true);
    try {
      await ref
          .read(barActionsProvider(_token))
          .closeTab(tab.id, result.method, bookingId: result.bookingId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          backgroundColor: Color(0xFF14532D),
          behavior: SnackBarBehavior.floating,
          content: Text('Tab settled.'),
        ),
      );
      Navigator.of(context).pop();
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
    final tabAsync = ref.watch(barTabDetailProvider((_token, widget.tabId)));
    final tab = tabAsync.value;
    final unreadNotifications = ref.watch(
      barUnreadNotificationsProvider(_token),
    );

    return BarScaffold(
      session: widget.session,
      title: tab?.tableLabel ?? 'Tab Detail',
      onBack: () => Navigator.of(context).pop(),
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      onChat: _openChat,
      onIntercom: _openIntercom,
      trailing: tab == null
          ? null
          : _VipToggleButton(
              isVip: tab.isVip,
              onTap: _busy ? null : () => _toggleVip(tab),
            ),
      body: tab == null
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.gold),
            )
          : _content(tab),
    );
  }

  Widget _content(BarTab tab) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text(
              tab.code,
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.5),
                fontSize: 13,
              ),
            ),
            const Spacer(),
            if (tab.isVip) const VipBadge(),
          ],
        ),
        const SizedBox(height: 4),
        Text(
          tab.guestName ?? 'Walk-in guest',
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 16),
        Expanded(
          child: tab.orders.isEmpty
              ? const BarEmptyState(
                  icon: Icons.local_bar_outlined,
                  message: 'No drinks on this tab yet.',
                )
              : ListView.separated(
                  itemCount: tab.orders.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 10),
                  itemBuilder: (_, i) =>
                      BarOrderCard(order: tab.orders[i], onTap: () {}),
                ),
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.05),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Tab Total',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.7),
                  fontSize: 14,
                ),
              ),
              Text(
                tab.totalLabel,
                style: AppTypography.style(
                  color: AppColors.gold,
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
        if (tab.chargedRoomLabel != null) ...[
          const SizedBox(height: 8),
          Row(
            children: [
              const Icon(Icons.hotel_rounded, size: 14, color: AppColors.gold),
              const SizedBox(width: 6),
              Text(
                'Charged to ${tab.chargedRoomLabel}',
                style: AppTypography.style(
                  color: AppColors.gold,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ],
        const SizedBox(height: 14),
        if (tab.isOpen)
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _busy ? null : () => _addDrink(tab),
                  icon: const Icon(
                    Icons.point_of_sale_rounded,
                    size: 18,
                    color: AppColors.gold,
                  ),
                  label: Text(
                    'Add Drink',
                    style: AppTypography.style(
                      color: AppColors.gold,
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(color: AppColors.gold),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Material(
                  color: AppColors.gold,
                  borderRadius: BorderRadius.circular(12),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(12),
                    onTap: _busy || tab.orders.isEmpty
                        ? null
                        : () => _closeTab(tab),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      child: Center(
                        child: Text(
                          'Close Tab',
                          style: AppTypography.style(
                            color: const Color(0xFF0A0F1E),
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
      ],
    );
  }
}

class _VipToggleButton extends StatelessWidget {
  const _VipToggleButton({required this.isVip, required this.onTap});

  final bool isVip;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      shape: CircleBorder(
        side: BorderSide(
          color: Colors.white.withValues(alpha: 0.1),
          width: 0.8,
        ),
      ),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 44,
          height: 44,
          child: Icon(
            isVip ? Icons.star_rounded : Icons.star_outline_rounded,
            size: 20,
            color: AppColors.gold,
          ),
        ),
      ),
    );
  }
}
