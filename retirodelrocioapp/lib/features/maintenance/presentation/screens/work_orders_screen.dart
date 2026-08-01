import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/core/widgets/coming_soon_screen.dart';
import 'package:retirodelrocioapp/core/widgets/staff_top_bar.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/session_guard.dart';
import 'package:retirodelrocioapp/features/maintenance/application/maintenance_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/data/maintenance_repository.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/dialogs/report_fault_dialog.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/maintenance_navigation.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_nav_rail.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_widgets.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';

enum _StatusFilter { all, newOrder, accepted, inProgress, done }

/// The full work-order board — every order, filterable by status and
/// priority, with the same accept/start/complete action the dashboard's
/// list offers.
class WorkOrdersScreen extends ConsumerStatefulWidget {
  const WorkOrdersScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<WorkOrdersScreen> createState() => _WorkOrdersScreenState();
}

class _WorkOrdersScreenState extends ConsumerState<WorkOrdersScreen> {
  _StatusFilter _filter = _StatusFilter.all;
  int? _busyOrderId;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) MaintenanceNavigation.afterLogout(context);
  }

  void _onNav(MaintenanceNavItem item) {
    if (item == MaintenanceNavItem.chat) {
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ComingSoonScreen(title: 'Chat')));
      return;
    }
    MaintenanceNavigation.select(context, widget.session, item, current: MaintenanceNavItem.workOrders);
  }

  List<WorkOrder> _visible(List<WorkOrder> all) {
    return switch (_filter) {
      _StatusFilter.newOrder => all.where((o) => o.isNew).toList(),
      _StatusFilter.accepted => all.where((o) => o.isAccepted).toList(),
      _StatusFilter.inProgress => all.where((o) => o.isInProgress).toList(),
      _StatusFilter.done => all.where((o) => o.isDone).toList(),
      _StatusFilter.all => all,
    };
  }

  Future<void> _reportFault() async {
    await showReportFaultDialog(context, token: _token);
  }

  Future<void> _run(WorkOrder order, Future<WorkOrder> Function() action) async {
    setState(() => _busyOrderId = order.id);
    try {
      await action();
    } on MaintenanceException catch (e) {
      _showFailure(e.message);
    } catch (_) {
      _showFailure('Could not update this order.');
    } finally {
      if (mounted) setState(() => _busyOrderId = null);
    }
  }

  void _showFailure(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: const Color(0xFF7F1D1D),
        behavior: SnackBarBehavior.floating,
        content: Text(message, style: AppTypography.style(color: Colors.white, fontSize: 14)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ordersAsync = ref.watch(maintenanceWorkOrdersProvider(_token));
    final orders = ordersAsync.value ?? const <WorkOrder>[];
    final weather = ref.watch(weatherProvider).value;

    return SessionGuard(
      child: Scaffold(
        backgroundColor: AppColors.background,
        body: Stack(
          fit: StackFit.expand,
          children: [
            Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
            const ColoredBox(color: Color(0xF2000000)),
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    MaintenanceNavRail(
                      active: MaintenanceNavItem.workOrders,
                      onSelect: _onNav,
                      onLogout: _logout,
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          StaffTopBar(
                            name: widget.session.name,
                            role: 'Maintenance',
                            brandLabel: 'MAINTENANCE',
                            weather: weather,
                            initialsFallback: 'MT',
                          ),
                          const SizedBox(height: 20),
                          _header(),
                          const SizedBox(height: 16),
                          _filterChips(),
                          const SizedBox(height: 16),
                          Expanded(
                            child: ordersAsync.when(
                              data: (data) => _list(data),
                              loading: () => orders.isNotEmpty
                                  ? _list(orders)
                                  : const Center(child: CircularProgressIndicator(color: AppColors.gold)),
                              error: (_, _) => orders.isNotEmpty
                                  ? _list(orders)
                                  : Center(
                                      child: TextButton(
                                        onPressed: () => ref.invalidate(maintenanceWorkOrdersProvider(_token)),
                                        child: const Text(
                                          'Could not load work orders. Retry',
                                          style: TextStyle(color: AppColors.gold),
                                        ),
                                      ),
                                    ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _header() {
    return Row(
      children: [
        IconButton(
          onPressed: () => Navigator.of(context).maybePop(),
          icon: const Icon(Icons.arrow_back_rounded, color: Colors.white),
        ),
        Expanded(
          child: Text(
            'Work Orders',
            style: AppTypography.style(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w700),
          ),
        ),
        Material(
          color: AppColors.gold,
          borderRadius: BorderRadius.circular(12),
          child: InkWell(
            onTap: _reportFault,
            borderRadius: BorderRadius.circular(12),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.add_rounded, size: 16, color: Colors.black),
                  const SizedBox(width: 6),
                  Text(
                    'Report Fault',
                    style: AppTypography.style(color: Colors.black, fontSize: 13, fontWeight: FontWeight.w700),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _filterChips() {
    return SizedBox(
      height: 38,
      child: ListView(
        scrollDirection: Axis.horizontal,
        children: [
          _chip('All', _StatusFilter.all),
          const SizedBox(width: 8),
          _chip('New', _StatusFilter.newOrder),
          const SizedBox(width: 8),
          _chip('Accepted', _StatusFilter.accepted),
          const SizedBox(width: 8),
          _chip('In Progress', _StatusFilter.inProgress),
          const SizedBox(width: 8),
          _chip('Done', _StatusFilter.done),
        ],
      ),
    );
  }

  Widget _chip(String label, _StatusFilter value) {
    final selected = _filter == value;
    return Material(
      color: selected ? AppColors.gold.withValues(alpha: 0.16) : Colors.white.withValues(alpha: 0.04),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => setState(() => _filter = value),
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected ? AppColors.gold.withValues(alpha: 0.4) : Colors.white.withValues(alpha: 0.08),
              width: 0.8,
            ),
          ),
          child: Text(
            label,
            style: AppTypography.style(
              color: selected ? AppColors.gold : Colors.white.withValues(alpha: 0.6),
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }

  Widget _list(List<WorkOrder> all) {
    final rows = _visible(all);
    if (rows.isEmpty) {
      return const MaintenanceSectionEmpty(icon: Icons.build_circle_outlined, message: 'No work orders match this filter');
    }
    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(maintenanceWorkOrdersProvider(_token)),
      child: ListView.separated(
        padding: const EdgeInsets.only(bottom: 24),
        itemCount: rows.length,
        separatorBuilder: (_, _) => const SizedBox(height: 12),
        itemBuilder: (_, i) {
          final order = rows[i];
          final actions = ref.read(maintenanceActionsProvider(_token));
          return WorkOrderCard(
            order: order,
            busy: _busyOrderId == order.id,
            onAccept: () => _run(order, () => actions.accept(order.id)),
            onStart: () => _run(order, () => actions.start(order.id)),
            onComplete: () => _run(order, () => actions.complete(order.id)),
          );
        },
      ),
    );
  }
}
