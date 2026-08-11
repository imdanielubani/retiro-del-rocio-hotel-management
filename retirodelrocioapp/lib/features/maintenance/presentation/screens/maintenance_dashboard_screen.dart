import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/core/widgets/staff_top_bar.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/session_guard.dart';
import 'package:retirodelrocioapp/features/maintenance/application/maintenance_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/data/maintenance_repository.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/maintenance_overview.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/application/maintenance_notification_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/presentation/screens/maintenance_notification_screen.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/dialogs/report_fault_dialog.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/maintenance_navigation.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/screens/work_order_detail_screen.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_nav_rail.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_widgets.dart';
import 'package:retirodelrocioapp/features/staff_chat/application/staff_chat_providers.dart';
import 'package:retirodelrocioapp/features/staff_intercom/presentation/widgets/staff_intercom_call_gate.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';

/// The technician's home dashboard — the open orders needing attention next,
/// urgent first, inside the same nav-rail + top-bar shell reception and
/// security use.
class MaintenanceDashboardScreen extends ConsumerStatefulWidget {
  const MaintenanceDashboardScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<MaintenanceDashboardScreen> createState() =>
      _MaintenanceDashboardScreenState();
}

class _MaintenanceDashboardScreenState
    extends ConsumerState<MaintenanceDashboardScreen> {
  int? _busyOrderId;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) Navigator.of(context).pop();
  }

  void _onNav(MaintenanceNavItem item) {
    if (item == MaintenanceNavItem.dashboard) return; // already here
    MaintenanceNavigation.open(context, widget.session, item);
  }

  Future<void> _reportFault() async {
    await showReportFaultDialog(context, token: _token);
  }

  void _openNotifications() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => MaintenanceNotificationScreen(
          session: widget.session,
          current: MaintenanceNavItem.dashboard,
        ),
      ),
    );
  }

  Future<void> _run(
    WorkOrder order,
    Future<WorkOrder> Function() action,
  ) async {
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
        content: Text(
          message,
          style: AppTypography.style(color: Colors.white, fontSize: 14),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    ref.watch(maintenanceNotificationsRealtimeProvider(_token));
    ref.watch(maintenanceNotificationChimeProvider(_token));
    ref.watch(maintenanceSlaBreachChimeProvider(_token));
    ref.watch(staffChatChimeProvider(_token));
    ref.watch(staffChatRealtimeProvider((_token, widget.session.role)));
    watchStaffIntercomCall(context, ref, widget.session);

    final overviewAsync = ref.watch(maintenanceOverviewProvider(_token));
    final overview = overviewAsync.value ?? MaintenanceOverview.empty;
    final weather = ref.watch(weatherProvider).value;
    final unreadNotifications = ref.watch(
      maintenanceUnreadNotificationsProvider(_token),
    );

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
                      active: MaintenanceNavItem.dashboard,
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
                            hasAlert: overview.urgent > 0,
                            hasUnreadNotifications: unreadNotifications > 0,
                            onNotifications: _openNotifications,
                            initialsFallback: 'MT',
                          ),
                          const SizedBox(height: 20),
                          _header(),
                          const SizedBox(height: 20),
                          Expanded(
                            child: overviewAsync.when(
                              data: (data) => _content(data),
                              loading: () => overview.workOrders.isNotEmpty
                                  ? _content(overview)
                                  : const Center(
                                      child: CircularProgressIndicator(
                                        color: AppColors.gold,
                                      ),
                                    ),
                              error: (_, _) => overview.workOrders.isNotEmpty
                                  ? _content(overview)
                                  : _errorState(),
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
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Maintenance',
                style: AppTypography.style(color: AppColors.gold, fontSize: 12),
              ),
              const SizedBox(height: 3),
              Text(
                'Dashboard',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 36,
                  fontWeight: FontWeight.w700,
                  height: 1.1,
                ),
              ),
            ],
          ),
        ),
        _reportButton(),
      ],
    );
  }

  Widget _reportButton() {
    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: _reportFault,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 13),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.add_rounded, size: 18, color: Colors.black),
              const SizedBox(width: 6),
              Text(
                'Report Fault',
                style: AppTypography.style(
                  color: Colors.black,
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _content(MaintenanceOverview data) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: MaintenanceStatCard(
                label: 'NEW',
                value: data.newCount,
                accent: kMtSlate,
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: MaintenanceStatCard(
                label: 'IN PROGRESS',
                value: data.inProgress,
                accent: AppColors.gold,
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: MaintenanceStatCard(
                label: 'URGENT',
                value: data.urgent,
                accent: kMtRed,
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: MaintenanceStatCard(
                label: 'COMPLETED TODAY',
                value: data.completedToday,
                accent: kMtGreen,
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: MaintenanceStatCard(
                label: 'SLA BREACHES',
                value: data.slaBreaches,
                accent: kMtRed,
              ),
            ),
          ],
        ),
        const SizedBox(height: 20),
        Expanded(
          child: MaintenanceSectionPanel(
            title: 'OPEN WORK ORDERS',
            child: data.workOrders.isEmpty
                ? const MaintenanceSectionEmpty(
                    icon: Icons.build_circle_outlined,
                    message: 'No open work orders',
                  )
                : ListView.separated(
                    padding: EdgeInsets.zero,
                    itemCount: data.workOrders.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 12),
                    itemBuilder: (_, i) {
                      final order = data.workOrders[i];
                      final actions = ref.read(
                        maintenanceActionsProvider(_token),
                      );
                      return WorkOrderCard(
                        order: order,
                        busy: _busyOrderId == order.id,
                        onAccept: () =>
                            _run(order, () => actions.accept(order.id)),
                        onStart: () =>
                            _run(order, () => actions.start(order.id)),
                        onComplete: () =>
                            _run(order, () => actions.complete(order.id)),
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => WorkOrderDetailScreen(
                              session: widget.session,
                              orderId: order.id,
                            ),
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ),
      ],
    );
  }

  Widget _errorState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.wifi_off_rounded,
            size: 30,
            color: Colors.white.withValues(alpha: 0.4),
          ),
          const SizedBox(height: 12),
          Text(
            'Could not load the dashboard.',
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
              onTap: () => ref.invalidate(maintenanceOverviewProvider(_token)),
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
