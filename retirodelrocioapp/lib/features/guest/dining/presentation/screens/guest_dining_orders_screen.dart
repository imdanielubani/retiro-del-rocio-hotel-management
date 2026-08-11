import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/dining/application/dining_providers.dart';
import 'package:retirodelrocioapp/features/guest/dining/domain/menu_item.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/presentation/screens/guest_notification_screen.dart';
import 'package:retirodelrocioapp/features/guest/sos/presentation/screens/sos_screen.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

const _blue = Color(0xFF60A5FA);
const _green = Color(0xFF00FF00);
const _red = Color(0xFFEF4444);

/// My Orders (Figma 130:415) — every dining order the guest has placed,
/// each with a live 5-step kitchen progress tracker (Received → Preparing →
/// Ready → On Way → Done) while it's still active, or a final status pill
/// once it's delivered/cancelled. Tapping "Reorder" on a finished order
/// pops this screen with that order's items, which `GuestDiningScreen`
/// merges straight into the cart.
class GuestDiningOrdersScreen extends ConsumerWidget {
  const GuestDiningOrdersScreen({
    super.key,
    required this.device,
    required this.status,
  });

  final ProvisionedDevice device;
  final RoomStatus status;

  String get _token => device.token;

  void _openNotifications(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => GuestNotificationScreen(device: device, status: status),
      ),
    );
  }

  void _openEmergency(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SosScreen(device: device, status: status),
      ),
    );
  }

  void _reorder(BuildContext context, DiningOrderSummary order) {
    Navigator.of(context).pop(order.items);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ordersAsync = ref.watch(diningOrdersProvider(_token));
    final orders = ordersAsync.value ?? const <DiningOrderSummary>[];
    final weather = ref.watch(weatherProvider).value;
    final guest = status.guest;

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
          const ColoredBox(color: Color.fromARGB(243, 0, 0, 0)),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(25, 24, 25, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  GuestTopBar(
                    suiteName: status.suiteName ?? 'Suite',
                    roomNumber: status.roomNumber ?? device.roomNumber ?? '—',
                    guestName: guest?.name ?? 'Guest',
                    weather: weather,
                    onNotifications: () => _openNotifications(context),
                    onProfile: () {},
                    onEmergency: () => _openEmergency(context),
                    hasUnreadNotifications:
                        ref.watch(guestUnreadNotificationsProvider(_token)) > 0,
                  ),
                  const SizedBox(height: 20),
                  _header(context),
                  const SizedBox(height: 19),
                  Expanded(
                    child: ordersAsync.when(
                      data: (data) => _content(context, ref, data),
                      loading: () => orders.isNotEmpty
                          ? _content(context, ref, orders)
                          : const Center(
                              child: CircularProgressIndicator(
                                color: AppColors.gold,
                              ),
                            ),
                      error: (_, _) => orders.isNotEmpty
                          ? _content(context, ref, orders)
                          : _errorState(ref),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _header(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Material(
          color: Colors.white.withValues(alpha: 0.06),
          shape: CircleBorder(
            side: BorderSide(
              color: Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          child: InkWell(
            onTap: () => Navigator.of(context).maybePop(),
            customBorder: const CircleBorder(),
            child: SizedBox(
              width: 40,
              height: 40,
              child: Icon(
                Icons.arrow_back_rounded,
                size: 18,
                color: Colors.white.withValues(alpha: 0.8),
              ),
            ),
          ),
        ),
        const SizedBox(width: 15),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'My Orders',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 36,
                  fontWeight: FontWeight.w700,
                  height: 1.15,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                'Track your room service orders',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 15,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _content(
    BuildContext context,
    WidgetRef ref,
    List<DiningOrderSummary> orders,
  ) {
    if (orders.isEmpty) return _emptyState();

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(diningOrdersProvider(_token)),
      child: ListView.separated(
        padding: const EdgeInsets.only(bottom: 24),
        itemCount: orders.length,
        separatorBuilder: (_, _) => const SizedBox(height: 16),
        itemBuilder: (_, i) => _OrderCard(
          order: orders[i],
          onReorder: () => _reorder(context, orders[i]),
        ),
      ),
    );
  }

  Widget _emptyState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.receipt_long_rounded,
            size: 32,
            color: Colors.white.withValues(alpha: 0.3),
          ),
          const SizedBox(height: 12),
          Text(
            'No orders yet',
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 15,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'Place an order from the menu and it\'ll show up here.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }

  Widget _errorState(WidgetRef ref) {
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
            'Could not load your orders.',
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
              onTap: () => ref.invalidate(diningOrdersProvider(_token)),
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

class _OrderCard extends StatelessWidget {
  const _OrderCard({required this.order, required this.onReorder});

  final DiningOrderSummary order;
  final VoidCallback onReorder;

  bool get _cancelled => order.status == 'cancelled';

  Color get _borderColor {
    if (_cancelled) return _red.withValues(alpha: 0.2);
    if (order.isActive) return _blue.withValues(alpha: 0.3);
    return _green.withValues(alpha: 0.1);
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(25),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: _borderColor, width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _topRow(),
          const SizedBox(height: 20),
          _itemChips(),
          if (order.isActive) ...[
            const SizedBox(height: 20),
            _progressTracker(),
          ],
          const SizedBox(height: 20),
          _bottomRow(),
        ],
      ),
    );
  }

  Widget _topRow() {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'ORDER',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 11,
                  letterSpacing: 1.1,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                order.code,
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                ),
              ),
              if (order.placedAtLabel != null) ...[
                const SizedBox(height: 2),
                Text(
                  order.placedAtLabel!,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.4),
                    fontSize: 12,
                  ),
                ),
              ],
            ],
          ),
        ),
        _statusPill(),
        if (order.isActive && order.etaLabel != null) ...[
          const SizedBox(width: 8),
          Align(
            alignment: Alignment.centerRight,
            child: Text(
              order.etaLabel!,
              style: AppTypography.style(color: AppColors.gold, fontSize: 12),
            ),
          ),
        ],
      ],
    );
  }

  Widget _statusPill() {
    final (color, icon) = switch (order.status) {
      'delivered' => (_green, Icons.check_circle_rounded),
      'cancelled' => (_red, Icons.cancel_rounded),
      'on_way' => (_blue, Icons.delivery_dining_rounded),
      'ready' => (_blue, Icons.check_circle_outline_rounded),
      _ => (_blue, Icons.soup_kitchen_rounded),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: color),
          const SizedBox(width: 8),
          Text(
            order.statusLabel,
            style: AppTypography.style(
              color: color,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  Widget _itemChips() {
    return Wrap(
      spacing: 12,
      runSpacing: 12,
      children: [for (final item in order.items) _itemChip(item)],
    );
  }

  Widget _itemChip(OrderLineItem item) {
    return Container(
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.06),
              borderRadius: BorderRadius.circular(10),
              image: item.imageUrl != null
                  ? DecorationImage(
                      image: NetworkImage(item.imageUrl!),
                      fit: BoxFit.cover,
                    )
                  : null,
            ),
            child: item.imageUrl == null
                ? Icon(
                    Icons.restaurant_rounded,
                    size: 16,
                    color: Colors.white.withValues(alpha: 0.25),
                  )
                : null,
          ),
          const SizedBox(width: 12),
          Text(
            item.qty > 1 ? '${item.name} ×${item.qty}' : item.name,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.7),
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }

  Widget _progressTracker() {
    const steps = [
      (DiningOrderStage.received, 'Received'),
      (DiningOrderStage.preparing, 'Preparing'),
      (DiningOrderStage.ready, 'Ready'),
      (DiningOrderStage.onWay, 'On Way'),
      (DiningOrderStage.done, 'Done'),
    ];
    final currentStep = order.stage.stepNumber;

    return Row(
      children: [
        for (var i = 0; i < steps.length; i++) ...[
          _stepCircle(steps[i].$1.stepNumber, steps[i].$2, currentStep),
          if (i != steps.length - 1)
            Expanded(
              child: Container(
                height: 2,
                margin: const EdgeInsets.symmetric(horizontal: 4),
                color: steps[i].$1.stepNumber < currentStep
                    ? AppColors.gold
                    : Colors.white.withValues(alpha: 0.1),
              ),
            ),
        ],
      ],
    );
  }

  Widget _stepCircle(int stepIndex, String label, int currentIndex) {
    final completed = stepIndex <= currentIndex;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 28,
          height: 28,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: completed
                ? AppColors.gold
                : Colors.white.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: completed
              ? const Icon(
                  Icons.check_rounded,
                  size: 14,
                  color: Color(0xFF0A0F1E),
                )
              : Text(
                  '$stepIndex',
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.3),
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
        ),
        const SizedBox(height: 4),
        Text(
          label,
          style: AppTypography.style(
            color: completed
                ? AppColors.gold
                : Colors.white.withValues(alpha: 0.3),
            fontSize: 9,
          ),
        ),
      ],
    );
  }

  Widget _bottomRow() {
    return Row(
      children: [
        Text(
          order.totalLabel.replaceFirst('NGN', '₦'),
          style: AppTypography.style(
            color: AppColors.gold,
            fontSize: 18,
            fontWeight: FontWeight.w700,
          ),
        ),
        const Spacer(),
        if (!order.isActive)
          Material(
            color: AppColors.gold.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(14),
            child: InkWell(
              onTap: onReorder,
              borderRadius: BorderRadius.circular(14),
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 17,
                  vertical: 9,
                ),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: AppColors.gold.withValues(alpha: 0.25),
                    width: 0.8,
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.replay_rounded,
                      size: 14,
                      color: AppColors.gold,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      'Reorder',
                      style: AppTypography.style(
                        color: AppColors.gold,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
      ],
    );
  }
}
