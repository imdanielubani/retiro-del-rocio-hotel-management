import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/maintenance/data/maintenance_repository.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/maintenance_overview.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';

final maintenanceRepositoryProvider = Provider<MaintenanceRepository>(
  (ref) => MaintenanceRepository(),
);

/// The whole dashboard, keyed by the technician's staff token. Polled every
/// 15 seconds so a freshly reported fault appears without a manual refresh.
final maintenanceOverviewProvider = FutureProvider.family<MaintenanceOverview, String>((ref, token) async {
  final repo = ref.watch(maintenanceRepositoryProvider);

  final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.overview(token);
});

/// The full work-order board, keyed by the technician's token. Client-side
/// status/priority filtering keeps this one provider serving the whole
/// Work Orders screen.
final maintenanceWorkOrdersProvider = FutureProvider.family<List<WorkOrder>, String>((ref, token) async {
  final repo = ref.watch(maintenanceRepositoryProvider);

  final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.workOrders(token);
});

/// The room picker for "report a fault against a room".
final maintenanceRoomsProvider = FutureProvider.family<List<MaintenanceRoomOption>, String>((ref, token) async {
  final repo = ref.watch(maintenanceRepositoryProvider);
  return repo.rooms(token);
});

class MaintenanceActions {
  const MaintenanceActions(this._ref, this._token);

  final Ref _ref;
  final String _token;

  Future<WorkOrder> createWorkOrder({
    int? roomUnitId,
    String? assetLabel,
    required String title,
    String? description,
    String priority = 'medium',
    String? reportedBy,
  }) async {
    final order = await _ref.read(maintenanceRepositoryProvider).createWorkOrder(
      _token,
      roomUnitId: roomUnitId,
      assetLabel: assetLabel,
      title: title,
      description: description,
      priority: priority,
      reportedBy: reportedBy,
    );
    _refresh();
    return order;
  }

  Future<WorkOrder> accept(int id) async {
    final order = await _ref.read(maintenanceRepositoryProvider).acceptWorkOrder(_token, id);
    _refresh();
    return order;
  }

  Future<WorkOrder> start(int id) async {
    final order = await _ref.read(maintenanceRepositoryProvider).startWorkOrder(_token, id);
    _refresh();
    return order;
  }

  Future<WorkOrder> complete(int id) async {
    final order = await _ref.read(maintenanceRepositoryProvider).completeWorkOrder(_token, id);
    _refresh();
    return order;
  }

  void _refresh() {
    _ref.invalidate(maintenanceWorkOrdersProvider(_token));
    _ref.invalidate(maintenanceOverviewProvider(_token));
  }
}

final maintenanceActionsProvider = Provider.family<MaintenanceActions, String>(
  (ref, token) => MaintenanceActions(ref, token),
);
