import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/maintenance/application/maintenance_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/data/maintenance_repository.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/application/maintenance_notification_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/presentation/screens/maintenance_notification_screen.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/dialogs/report_fault_dialog.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/maintenance_navigation.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/screens/work_order_detail_screen.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_nav_rail.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_scaffold.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_widgets.dart';

enum _StatusFilter { all, newOrder, accepted, inProgress, done }

enum _PriorityFilter { all, low, medium, high, urgent }

enum _LocationFilter { all, rooms, other }

enum _ViewMode { board, list }

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
  _ViewMode _view = _ViewMode.board;
  _StatusFilter _filter = _StatusFilter.all;
  _PriorityFilter _priorityFilter = _PriorityFilter.all;
  _LocationFilter _locationFilter = _LocationFilter.all;
  String? _categoryFilter;
  String _search = '';
  int? _busyOrderId;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) MaintenanceNavigation.afterLogout(context);
  }

  void _onNav(MaintenanceNavItem item) {
    MaintenanceNavigation.select(
      context,
      widget.session,
      item,
      current: MaintenanceNavItem.workOrders,
    );
  }

  void _openNotifications() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => MaintenanceNotificationScreen(
          session: widget.session,
          current: MaintenanceNavItem.workOrders,
        ),
      ),
    );
  }

  /// Priority/category/location/search — every filter except status, shared
  /// by both the list (which also narrows by the status chips) and the board
  /// (whose columns already are the status split).
  List<WorkOrder> _filtered(List<WorkOrder> all) {
    var rows = all;
    if (_priorityFilter != _PriorityFilter.all) {
      rows = rows.where((o) => o.priority == _priorityFilter.name).toList();
    }
    if (_locationFilter == _LocationFilter.rooms) {
      rows = rows.where((o) => (o.roomNumber ?? '').isNotEmpty).toList();
    } else if (_locationFilter == _LocationFilter.other) {
      rows = rows.where((o) => (o.roomNumber ?? '').isEmpty).toList();
    }
    if (_categoryFilter != null) {
      rows = rows.where((o) => o.assetCategory == _categoryFilter).toList();
    }
    final q = _search.trim().toLowerCase();
    if (q.isNotEmpty) {
      rows = rows
          .where(
            (o) =>
                o.title.toLowerCase().contains(q) ||
                o.locationLabel.toLowerCase().contains(q) ||
                (o.assetLabel ?? '').toLowerCase().contains(q),
          )
          .toList();
    }
    return rows;
  }

  List<WorkOrder> _visible(List<WorkOrder> all) {
    final rows = switch (_filter) {
      _StatusFilter.newOrder => all.where((o) => o.isNew).toList(),
      _StatusFilter.accepted => all.where((o) => o.isAccepted).toList(),
      _StatusFilter.inProgress => all.where((o) => o.isInProgress).toList(),
      _StatusFilter.done => all.where((o) => o.isDone).toList(),
      _StatusFilter.all => all,
    };
    return _filtered(rows);
  }

  List<String> _categories(List<WorkOrder> all) {
    return all
        .map((o) => o.assetCategory)
        .whereType<String>()
        .where((c) => c.isNotEmpty)
        .toSet()
        .toList()
      ..sort();
  }

  Future<void> _reportFault() async {
    await showReportFaultDialog(context, token: _token);
  }

  void _openDetail(WorkOrder order) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            WorkOrderDetailScreen(session: widget.session, orderId: order.id),
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

    final ordersAsync = ref.watch(maintenanceWorkOrdersProvider(_token));
    final orders = ordersAsync.value ?? const <WorkOrder>[];
    final unreadNotifications = ref.watch(
      maintenanceUnreadNotificationsProvider(_token),
    );

    return MaintenanceScaffold(
      session: widget.session,
      active: MaintenanceNavItem.workOrders,
      onNav: _onNav,
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: 'Work Orders',
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [_viewToggle(), const SizedBox(width: 10), _reportButton()],
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _searchField(),
          const SizedBox(height: 12),
          if (_view == _ViewMode.list) ...[
            _filterChips(),
            const SizedBox(height: 10),
          ],
          _priorityChips(),
          const SizedBox(height: 10),
          _locationAndCategoryChips(orders),
          const SizedBox(height: 16),
          Expanded(
            child: ordersAsync.when(
              data: (data) =>
                  _view == _ViewMode.board ? _board(data) : _list(data),
              loading: () => orders.isNotEmpty
                  ? (_view == _ViewMode.board ? _board(orders) : _list(orders))
                  : const Center(
                      child: CircularProgressIndicator(color: AppColors.gold),
                    ),
              error: (_, _) => orders.isNotEmpty
                  ? (_view == _ViewMode.board ? _board(orders) : _list(orders))
                  : Center(
                      child: TextButton(
                        onPressed: () => ref.invalidate(
                          maintenanceWorkOrdersProvider(_token),
                        ),
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
    );
  }

  Widget _viewToggle() {
    return Material(
      color: Colors.white.withValues(alpha: 0.05),
      borderRadius: BorderRadius.circular(10),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _viewToggleButton(Icons.view_column_outlined, _ViewMode.board),
          _viewToggleButton(Icons.view_list_rounded, _ViewMode.list),
        ],
      ),
    );
  }

  Widget _viewToggleButton(IconData icon, _ViewMode mode) {
    final selected = _view == mode;
    return InkWell(
      onTap: () => setState(() => _view = mode),
      borderRadius: BorderRadius.circular(10),
      child: Container(
        width: 40,
        height: 40,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: selected
              ? AppColors.gold.withValues(alpha: 0.16)
              : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Icon(
          icon,
          size: 18,
          color: selected
              ? AppColors.gold
              : Colors.white.withValues(alpha: 0.5),
        ),
      ),
    );
  }

  Widget _reportButton() {
    return Material(
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

  Widget _searchField() {
    return SizedBox(
      height: 44,
      child: TextField(
        onChanged: (v) => setState(() => _search = v),
        cursorColor: AppColors.gold,
        style: AppTypography.style(color: Colors.white, fontSize: 14),
        decoration: InputDecoration(
          isDense: true,
          filled: true,
          fillColor: Colors.white.withValues(alpha: 0.06),
          hintText: 'Search work order or asset',
          hintStyle: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.35),
            fontSize: 14,
          ),
          prefixIcon: Icon(
            Icons.search_rounded,
            size: 18,
            color: Colors.white.withValues(alpha: 0.4),
          ),
          contentPadding: const EdgeInsets.symmetric(vertical: 12),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(
              color: Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(
              color: Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(
              color: AppColors.gold.withValues(alpha: 0.5),
              width: 1,
            ),
          ),
        ),
      ),
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

  Widget _priorityChips() {
    return SizedBox(
      height: 34,
      child: ListView(
        scrollDirection: Axis.horizontal,
        children: [
          _priorityChip('Any Priority', _PriorityFilter.all),
          const SizedBox(width: 8),
          _priorityChip('Low', _PriorityFilter.low),
          const SizedBox(width: 8),
          _priorityChip('Medium', _PriorityFilter.medium),
          const SizedBox(width: 8),
          _priorityChip('High', _PriorityFilter.high),
          const SizedBox(width: 8),
          _priorityChip('Urgent', _PriorityFilter.urgent),
        ],
      ),
    );
  }

  Widget _locationAndCategoryChips(List<WorkOrder> all) {
    final categories = _categories(all);
    return SizedBox(
      height: 34,
      child: ListView(
        scrollDirection: Axis.horizontal,
        children: [
          _locationChip('Any Location', _LocationFilter.all),
          const SizedBox(width: 8),
          _locationChip('Rooms', _LocationFilter.rooms),
          const SizedBox(width: 8),
          _locationChip('Assets/Hotel-wide', _LocationFilter.other),
          if (categories.isNotEmpty) ...[
            const SizedBox(width: 16),
            Container(
              width: 1,
              height: 18,
              color: Colors.white.withValues(alpha: 0.1),
            ),
            const SizedBox(width: 16),
            _categoryChip('Any Category', null),
            for (final category in categories) ...[
              const SizedBox(width: 8),
              _categoryChip(category, category),
            ],
          ],
        ],
      ),
    );
  }

  Widget _locationChip(String label, _LocationFilter value) {
    final selected = _locationFilter == value;
    return Material(
      color: selected
          ? AppColors.gold.withValues(alpha: 0.16)
          : Colors.white.withValues(alpha: 0.04),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => setState(() => _locationFilter = value),
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.4)
                  : Colors.white.withValues(alpha: 0.08),
              width: 0.8,
            ),
          ),
          child: Text(
            label,
            style: AppTypography.style(
              color: selected
                  ? AppColors.gold
                  : Colors.white.withValues(alpha: 0.5),
              fontSize: 11,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }

  Widget _categoryChip(String label, String? value) {
    final selected = _categoryFilter == value;
    return Material(
      color: selected
          ? kMtBlue.withValues(alpha: 0.16)
          : Colors.white.withValues(alpha: 0.04),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => setState(() => _categoryFilter = value),
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected
                  ? kMtBlue.withValues(alpha: 0.4)
                  : Colors.white.withValues(alpha: 0.08),
              width: 0.8,
            ),
          ),
          child: Text(
            label,
            style: AppTypography.style(
              color: selected ? kMtBlue : Colors.white.withValues(alpha: 0.5),
              fontSize: 11,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }

  Widget _priorityChip(String label, _PriorityFilter value) {
    final selected = _priorityFilter == value;
    return Material(
      color: selected
          ? AppColors.gold.withValues(alpha: 0.16)
          : Colors.white.withValues(alpha: 0.04),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => setState(() => _priorityFilter = value),
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.4)
                  : Colors.white.withValues(alpha: 0.08),
              width: 0.8,
            ),
          ),
          child: Text(
            label,
            style: AppTypography.style(
              color: selected
                  ? AppColors.gold
                  : Colors.white.withValues(alpha: 0.5),
              fontSize: 11,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }

  Widget _chip(String label, _StatusFilter value) {
    final selected = _filter == value;
    return Material(
      color: selected
          ? AppColors.gold.withValues(alpha: 0.16)
          : Colors.white.withValues(alpha: 0.04),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => setState(() => _filter = value),
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.4)
                  : Colors.white.withValues(alpha: 0.08),
              width: 0.8,
            ),
          ),
          child: Text(
            label,
            style: AppTypography.style(
              color: selected
                  ? AppColors.gold
                  : Colors.white.withValues(alpha: 0.6),
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
      return const MaintenanceSectionEmpty(
        icon: Icons.build_circle_outlined,
        message: 'No work orders match this filter',
      );
    }
    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async =>
          ref.invalidate(maintenanceWorkOrdersProvider(_token)),
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
            onTap: () => _openDetail(order),
          );
        },
      ),
    );
  }

  Widget _board(List<WorkOrder> all) {
    final rows = _filtered(all);
    final columns = [
      ('New', rows.where((o) => o.isNew).toList(), kMtSlate),
      ('Accepted', rows.where((o) => o.isAccepted).toList(), kMtBlue),
      (
        'In Progress',
        rows.where((o) => o.isInProgress).toList(),
        AppColors.gold,
      ),
      ('Done', rows.where((o) => o.isDone).toList(), kMtGreen),
    ];

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async =>
          ref.invalidate(maintenanceWorkOrdersProvider(_token)),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            for (final (label, orders, color) in columns)
              _boardColumn(label, orders, color),
          ],
        ),
      ),
    );
  }

  Widget _boardColumn(String label, List<WorkOrder> orders, Color color) {
    final actions = ref.read(maintenanceActionsProvider(_token));
    return Padding(
      padding: const EdgeInsets.only(right: 14),
      child: SizedBox(
        width: 300,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Row(
                children: [
                  Container(
                    width: 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: color,
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    label.toUpperCase(),
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.6),
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1.2,
                    ),
                  ),
                  const SizedBox(width: 6),
                  Text(
                    '${orders.length}',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.3),
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: orders.isEmpty
                  ? Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.02),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(
                          color: Colors.white.withValues(alpha: 0.06),
                          width: 0.8,
                        ),
                      ),
                      child: Center(
                        child: Text(
                          'Nothing here',
                          style: AppTypography.style(
                            color: Colors.white.withValues(alpha: 0.25),
                            fontSize: 12,
                          ),
                        ),
                      ),
                    )
                  : ListView.separated(
                      padding: EdgeInsets.zero,
                      itemCount: orders.length,
                      separatorBuilder: (_, _) => const SizedBox(height: 10),
                      itemBuilder: (_, i) {
                        final order = orders[i];
                        return WorkOrderCard(
                          order: order,
                          busy: _busyOrderId == order.id,
                          onAccept: () =>
                              _run(order, () => actions.accept(order.id)),
                          onStart: () =>
                              _run(order, () => actions.start(order.id)),
                          onComplete: () =>
                              _run(order, () => actions.complete(order.id)),
                          onTap: () => _openDetail(order),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
