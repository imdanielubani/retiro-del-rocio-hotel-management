import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/reception/application/reception_providers.dart';
import 'package:retirodelrocioapp/features/reception/data/reception_repository.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_pickup.dart';
import 'package:retirodelrocioapp/features/reception/notifications/application/reception_notification_providers.dart';
import 'package:retirodelrocioapp/features/reception/notifications/presentation/screens/reception_notification_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/reception_navigation.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_assign_driver_dialog.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_nav_rail.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_scaffold.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_widgets.dart';

/// The Vehicle Pickup module — the front desk sees every guest arriving by car
/// and assigns a driver from the roster. Unassigned pickups sort first.
class ReceptionVehiclePickupScreen extends ConsumerStatefulWidget {
  const ReceptionVehiclePickupScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<ReceptionVehiclePickupScreen> createState() =>
      _ReceptionVehiclePickupScreenState();
}

/// A status filter tab: its label and the pickup `status` it keeps (null = all).
typedef _Filter = ({String label, String? status});

class _ReceptionVehiclePickupScreenState
    extends ConsumerState<ReceptionVehiclePickupScreen> {
  static const List<_Filter> _filters = [
    (label: 'All', status: null),
    (label: 'Awaiting Driver', status: 'unassigned'),
    (label: 'Assigned', status: 'assigned'),
    (label: 'Picked Up', status: 'completed'),
  ];

  String? _status;
  int? _busyId;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) ReceptionNavigation.afterLogout(context);
  }

  List<PickupBooking> _filter(List<PickupBooking> all) {
    if (_status == null) return all;
    return all.where((p) => p.pickupStatus == _status).toList();
  }

  Future<void> _assign(PickupBooking pickup) async {
    final changed = await showReceptionAssignDriverDialog(
      context,
      session: widget.session,
      pickup: pickup,
    );
    if (changed == true && mounted)
      _toast('Driver updated for ${pickup.reference}.');
  }

  Future<void> _complete(PickupBooking pickup) async {
    setState(() => _busyId = pickup.id);
    try {
      await ref
          .read(receptionActionsProvider(_token))
          .completePickup(pickup.id);
      if (mounted) _toast('${pickup.guestName} marked as picked up.');
    } on ReceptionException catch (e) {
      if (mounted) _toast(e.message, error: true);
    } catch (_) {
      if (mounted)
        _toast('Something went wrong. Please try again.', error: true);
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  void _toast(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: error
            ? const Color(0xFF7F1D1D)
            : const Color(0xFF14532D),
        content: Text(
          message,
          style: AppTypography.style(color: Colors.white, fontSize: 14),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final pickupsAsync = ref.watch(receptionPickupsProvider(_token));
    final unreadNotifications = ref.watch(
      receptionUnreadNotificationsProvider(_token),
    );

    return ReceptionScaffold(
      session: widget.session,
      active: ReceptionNavItem.vehiclePickup,
      onNav: (item) => ReceptionNavigation.select(
        context,
        widget.session,
        item,
        current: ReceptionNavItem.vehiclePickup,
      ),
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: 'Vehicle Pickup',
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _filterBar(),
          const SizedBox(height: 16),
          Expanded(
            child: pickupsAsync.when(
              loading: () => const Center(
                child: CircularProgressIndicator(color: AppColors.gold),
              ),
              error: (_, _) => Center(
                child: TextButton(
                  onPressed: () =>
                      ref.invalidate(receptionPickupsProvider(_token)),
                  child: const Text(
                    'Could not load pickups. Retry',
                    style: TextStyle(color: AppColors.gold),
                  ),
                ),
              ),
              data: (all) {
                final rows = _filter(all);
                if (rows.isEmpty) {
                  return const ReceptionSectionEmpty(
                    icon: Icons.directions_car_outlined,
                    message: 'No vehicle pickups here',
                  );
                }
                return RefreshIndicator(
                  color: AppColors.gold,
                  onRefresh: () async =>
                      ref.invalidate(receptionPickupsProvider(_token)),
                  child: GridView.builder(
                    padding: const EdgeInsets.only(bottom: 24),
                    gridDelegate:
                        const SliverGridDelegateWithMaxCrossAxisExtent(
                          maxCrossAxisExtent: 460,
                          mainAxisExtent: 232,
                          crossAxisSpacing: 16,
                          mainAxisSpacing: 16,
                        ),
                    itemCount: rows.length,
                    itemBuilder: (_, i) => _PickupCard(
                      pickup: rows[i],
                      busy: _busyId == rows[i].id,
                      onAssign: () => _assign(rows[i]),
                      onComplete: () => _complete(rows[i]),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  void _openNotifications() {
    ReceptionNavigation.push(
      context,
      'notifications',
      ReceptionNotificationScreen(
        session: widget.session,
        current: ReceptionNavItem.vehiclePickup,
      ),
    );
  }

  Widget _filterBar() {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final f in _filters) ...[_chip(f), const SizedBox(width: 10)],
        ],
      ),
    );
  }

  Widget _chip(_Filter f) {
    final selected = _status == f.status;
    return Material(
      color: selected
          ? AppColors.gold.withValues(alpha: 0.15)
          : Colors.white.withValues(alpha: 0.04),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => setState(() => _status = f.status),
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.4)
                  : Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          child: Text(
            f.label,
            style: AppTypography.style(
              color: selected
                  ? AppColors.gold
                  : Colors.white.withValues(alpha: 0.6),
              fontSize: 13,
              fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
            ),
          ),
        ),
      ),
    );
  }
}

/// One pickup card: guest, vehicle & route, driver assignment and actions.
class _PickupCard extends StatelessWidget {
  const _PickupCard({
    required this.pickup,
    required this.busy,
    required this.onAssign,
    required this.onComplete,
  });

  final PickupBooking pickup;
  final bool busy;
  final VoidCallback onAssign;
  final VoidCallback onComplete;

  Color get _statusColor => switch (pickup.pickupStatus) {
    'assigned' => kReceptionBlue,
    'completed' => kReceptionGreen,
    _ => AppColors.gold,
  };

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.08),
          width: 0.8,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      pickup.reference,
                      style: AppTypography.style(
                        color: AppColors.gold,
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      pickup.guestName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.style(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              _statusChip(),
            ],
          ),
          const SizedBox(height: 12),
          _line(
            Icons.directions_car_rounded,
            '${pickup.vehicle} · ${pickup.passengersLabel}',
          ),
          const SizedBox(height: 6),
          _line(Icons.route_rounded, '${pickup.from} → ${pickup.to}'),
          const SizedBox(height: 6),
          _line(
            Icons.schedule_rounded,
            [
              if ((pickup.pickupDate ?? '').isNotEmpty) pickup.pickupDate!,
              if ((pickup.pickupTime ?? '').isNotEmpty) pickup.pickupTime!,
              if ((pickup.flightNumber ?? '').isNotEmpty)
                '${pickup.numberLabel} ${pickup.flightNumber}',
            ].join(' · '),
          ),
          const Spacer(),
          Container(height: 0.8, color: Colors.white.withValues(alpha: 0.08)),
          const SizedBox(height: 10),
          _driverRow(context),
        ],
      ),
    );
  }

  Widget _statusChip() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: _statusColor.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: _statusColor.withValues(alpha: 0.3),
          width: 0.8,
        ),
      ),
      child: Text(
        pickup.pickupStatusLabel,
        style: AppTypography.style(
          color: _statusColor,
          fontSize: 11,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  Widget _line(IconData icon, String text) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 15, color: Colors.white.withValues(alpha: 0.4)),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            text,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.7),
              fontSize: 13,
            ),
          ),
        ),
      ],
    );
  }

  Widget _driverRow(BuildContext context) {
    final driver = pickup.driver;
    return Row(
      children: [
        Expanded(
          child: driver != null
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      driver.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.style(
                        color: Colors.white,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if ((driver.vehicleDetails ?? driver.phone ?? '')
                        .isNotEmpty)
                      Text(
                        driver.vehicleDetails ?? driver.phone ?? '',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTypography.style(
                          color: Colors.white.withValues(alpha: 0.4),
                          fontSize: 12,
                        ),
                      ),
                  ],
                )
              : Text(
                  'No driver assigned',
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.4),
                    fontSize: 13,
                  ),
                ),
        ),
        const SizedBox(width: 10),
        if (pickup.isAssigned)
          _button(
            'Picked up',
            kReceptionGreen,
            busy ? null : onComplete,
            busy: busy,
          ),
        if (!pickup.isCompleted) ...[
          const SizedBox(width: 8),
          _button(
            pickup.isUnassigned ? 'Assign Driver' : 'Reassign',
            AppColors.gold,
            onAssign,
          ),
        ],
      ],
    );
  }

  Widget _button(
    String label,
    Color color,
    VoidCallback? onTap, {
    bool busy = false,
  }) {
    return Material(
      color: color.withValues(alpha: 0.14),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: color.withValues(alpha: 0.35),
              width: 0.8,
            ),
          ),
          child: busy
              ? SizedBox(
                  width: 14,
                  height: 14,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: color,
                  ),
                )
              : Text(
                  label,
                  style: AppTypography.style(
                    color: color,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
        ),
      ),
    );
  }
}
