import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/audio/notification_chime.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/sos_channel.dart';
import 'package:retirodelrocioapp/features/maintenance/data/maintenance_repository.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/asset.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/maintenance_overview.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/parts_request.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/technician.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/application/maintenance_notification_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/presentation/widgets/sla_breach_toast.dart';

final maintenanceRepositoryProvider = Provider<MaintenanceRepository>(
  (ref) => MaintenanceRepository(),
);

/// The whole dashboard, keyed by the technician's staff token. Polled every
/// 15 seconds so a freshly reported fault appears without a manual refresh.
final maintenanceOverviewProvider =
    FutureProvider.family<MaintenanceOverview, String>((ref, token) async {
      final repo = ref.watch(maintenanceRepositoryProvider);

      final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.overview(token);
    });

/// The full work-order board, keyed by the technician's token. Client-side
/// status/priority filtering keeps this one provider serving the whole
/// Work Orders screen.
final maintenanceWorkOrdersProvider =
    FutureProvider.family<List<WorkOrder>, String>((ref, token) async {
      final repo = ref.watch(maintenanceRepositoryProvider);

      final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.workOrders(token);
    });

/// The room picker for "report a fault against a room".
final maintenanceRoomsProvider =
    FutureProvider.family<List<MaintenanceRoomOption>, String>((
      ref,
      token,
    ) async {
      final repo = ref.watch(maintenanceRepositoryProvider);
      return repo.rooms(token);
    });

/// Every registered asset — the Assets tab list (client-side filtered by
/// search/category/due, same pattern as the Work Orders board) and the
/// picker for "report a fault against an asset".
final maintenanceAssetsProvider = FutureProvider.family<List<Asset>, String>((
  ref,
  token,
) async {
  final repo = ref.watch(maintenanceRepositoryProvider);

  final timer = Timer(const Duration(seconds: 20), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.assets(token);
});

/// One asset's detail + service history, keyed by (token, assetId).
final maintenanceAssetDetailProvider =
    FutureProvider.family<Asset?, (String, int)>((ref, args) async {
      final repo = ref.watch(maintenanceRepositoryProvider);
      return repo.assetDetail(args.$1, args.$2);
    });

/// One work order's detail + attachments, keyed by (token, orderId). Polled
/// so a photo/video another technician just added appears without a manual
/// refresh.
final maintenanceWorkOrderDetailProvider =
    FutureProvider.family<WorkOrder?, (String, int)>((ref, args) async {
      final repo = ref.watch(maintenanceRepositoryProvider);

      final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.workOrderDetail(args.$1, args.$2);
    });

/// The Requests tab list, keyed by the technician's token. Client-side
/// status-filtered, same pattern as the Work Orders board.
final maintenancePartsRequestsProvider =
    FutureProvider.family<List<PartsRequest>, String>((ref, token) async {
      final repo = ref.watch(maintenanceRepositoryProvider);

      final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.partsRequests(token);
    });

/// The Assign Technician bottom sheet's picker — every active maintenance
/// technician.
final maintenanceTechniciansProvider =
    FutureProvider.family<List<Technician>, String>((ref, token) async {
      final repo = ref.watch(maintenanceRepositoryProvider);
      return repo.technicians(token);
    });

/// Subscribes the tablet to the hotel-wide `maintenance` channel and
/// refreshes the notification feed, the work-order board, the dashboard and
/// the Requests list the instant a new signal lands — so a fresh fault
/// appears within a couple of seconds instead of waiting on each screen's own
/// poll. Pure accelerator: if no broadcaster is configured, or the socket
/// drops, those providers' own periodic polls still carry the feed.
final maintenanceNotificationsRealtimeProvider =
    FutureProvider.family<void, String>((ref, token) async {
      final config = (await ref.watch(appConfigProvider.future)).realtime;
      if (config == null) {
        debugPrint(
          'maintenanceNotificationsRealtime: no broadcaster configured — polling only.',
        );
        return;
      }

      final channel = SosChannel(
        config: config,
        channel: 'maintenance',
        events: const {},
      );
      channel.connect(
        onChanged: () {},
        onNotification: () {
          ref.invalidate(maintenanceNotificationsProvider(token));
          ref.invalidate(maintenanceWorkOrdersProvider(token));
          ref.invalidate(maintenanceOverviewProvider(token));
          ref.invalidate(maintenancePartsRequestsProvider(token));
        },
      );

      ref.onDispose(channel.dispose);
    });

/// Toasts and chimes the moment a work order first crosses its priority's
/// SLA deadline — computed client-side off whatever the board's own poll (or
/// the realtime channel's refresh) already fetched, so no separate
/// server-side scheduler is needed to detect the transition. Watched
/// alongside [maintenanceNotificationChimeProvider] on every maintenance
/// screen.
final maintenanceSlaBreachChimeProvider = Provider.family<void, String>((
  ref,
  token,
) {
  final chime = NotificationChime();
  ref.onDispose(chime.dispose);

  Set<int>? previouslyBreached;

  ref.listen<AsyncValue<List<WorkOrder>>>(
    maintenanceWorkOrdersProvider(token),
    (previous, next) {
      final list = next.value;
      if (list == null) return;

      final breachedNow = list
          .where((o) => o.slaBreached)
          .map((o) => o.id)
          .toSet();
      final knownBreached = previouslyBreached;
      if (knownBreached != null) {
        final newlyBreached = list.where(
          (o) => o.slaBreached && !knownBreached.contains(o.id),
        );
        if (newlyBreached.isNotEmpty) {
          unawaited(chime.play());
          for (final order in newlyBreached) {
            showSlaBreachToast(order);
          }
        }
      }
      previouslyBreached = breachedNow;
    },
    fireImmediately: true,
  );
});

class MaintenanceActions {
  const MaintenanceActions(this._ref, this._token);

  final Ref _ref;
  final String _token;

  Future<WorkOrder> createWorkOrder({
    int? roomUnitId,
    int? assetId,
    String? assetLabel,
    required String title,
    String? description,
    String priority = 'medium',
    String? reportedBy,
  }) async {
    final order = await _ref
        .read(maintenanceRepositoryProvider)
        .createWorkOrder(
          _token,
          roomUnitId: roomUnitId,
          assetId: assetId,
          assetLabel: assetLabel,
          title: title,
          description: description,
          priority: priority,
          reportedBy: reportedBy,
        );
    _refresh();
    return order;
  }

  Future<Asset> createAsset({
    required String name,
    String? category,
    int? roomUnitId,
    String? locationLabel,
    String? notes,
    int? serviceIntervalDays,
  }) async {
    final asset = await _ref
        .read(maintenanceRepositoryProvider)
        .createAsset(
          _token,
          name: name,
          category: category,
          roomUnitId: roomUnitId,
          locationLabel: locationLabel,
          notes: notes,
          serviceIntervalDays: serviceIntervalDays,
        );
    _ref.invalidate(maintenanceAssetsProvider(_token));
    return asset;
  }

  Future<Asset> markAssetServiced(int id) async {
    final asset = await _ref
        .read(maintenanceRepositoryProvider)
        .markAssetServiced(_token, id);
    _ref.invalidate(maintenanceAssetsProvider(_token));
    _ref.invalidate(maintenanceAssetDetailProvider((_token, id)));
    return asset;
  }

  Future<WorkOrder> accept(int id) async {
    final order = await _ref
        .read(maintenanceRepositoryProvider)
        .acceptWorkOrder(_token, id);
    _refresh(id);
    return order;
  }

  Future<WorkOrder> start(int id) async {
    final order = await _ref
        .read(maintenanceRepositoryProvider)
        .startWorkOrder(_token, id);
    _refresh(id);
    return order;
  }

  Future<WorkOrder> complete(int id) async {
    final order = await _ref
        .read(maintenanceRepositoryProvider)
        .completeWorkOrder(_token, id);
    _refresh(id);
    return order;
  }

  Future<WorkOrder> uploadAttachment(int id, String filePath) async {
    final order = await _ref
        .read(maintenanceRepositoryProvider)
        .uploadAttachment(_token, id, filePath);
    _ref.invalidate(maintenanceWorkOrderDetailProvider((_token, id)));
    return order;
  }

  Future<PartsRequest> createPartsRequest(
    int workOrderId, {
    required String partName,
    int quantity = 1,
    String? note,
  }) async {
    final request = await _ref
        .read(maintenanceRepositoryProvider)
        .createPartsRequest(
          _token,
          workOrderId,
          partName: partName,
          quantity: quantity,
          note: note,
        );
    _ref.invalidate(maintenancePartsRequestsProvider(_token));
    return request;
  }

  Future<PartsRequest> fulfillPartsRequest(int id) async {
    final request = await _ref
        .read(maintenanceRepositoryProvider)
        .fulfillPartsRequest(_token, id);
    _ref.invalidate(maintenancePartsRequestsProvider(_token));
    return request;
  }

  Future<PartsRequest> denyPartsRequest(int id) async {
    final request = await _ref
        .read(maintenanceRepositoryProvider)
        .denyPartsRequest(_token, id);
    _ref.invalidate(maintenancePartsRequestsProvider(_token));
    return request;
  }

  Future<WorkOrder> escalate(int id) async {
    final order = await _ref
        .read(maintenanceRepositoryProvider)
        .escalateWorkOrder(_token, id);
    _refresh(id);
    return order;
  }

  Future<WorkOrder> updateStatus(int id, String status) async {
    final order = await _ref
        .read(maintenanceRepositoryProvider)
        .updateWorkOrderStatus(_token, id, status);
    _refresh(id);
    return order;
  }

  Future<WorkOrder> assign(int id, int technicianId) async {
    final order = await _ref
        .read(maintenanceRepositoryProvider)
        .assignWorkOrder(_token, id, technicianId);
    _refresh(id);
    return order;
  }

  void _refresh([int? id]) {
    _ref.invalidate(maintenanceWorkOrdersProvider(_token));
    _ref.invalidate(maintenanceOverviewProvider(_token));
    if (id != null)
      _ref.invalidate(maintenanceWorkOrderDetailProvider((_token, id)));
  }
}

final maintenanceActionsProvider = Provider.family<MaintenanceActions, String>(
  (ref, token) => MaintenanceActions(ref, token),
);
