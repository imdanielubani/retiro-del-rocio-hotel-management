import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/sos_channel.dart';
import 'package:retirodelrocioapp/features/housekeeping/data/housekeeping_repository.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_guest_request.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_overview.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_room.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/application/housekeeping_notification_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';

final housekeepingRepositoryProvider = Provider<HousekeepingRepository>(
  (ref) => HousekeepingRepository(),
);

/// The whole dashboard, keyed by the housekeeper's staff token. Polled every
/// 15 seconds so a room a guest just checked out of, or a fresh request,
/// appears without a manual refresh.
final housekeepingOverviewProvider =
    FutureProvider.family<HousekeepingOverview, String>((ref, token) async {
      final repo = ref.watch(housekeepingRepositoryProvider);

      final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.overview(token);
    });

/// The full room grid, keyed by the housekeeper's token. Client-side status
/// and search filtering keep this one provider serving the whole Rooms
/// screen, same shape as [housekeepingOverviewProvider].
final housekeepingRoomsProvider =
    FutureProvider.family<List<HousekeepingRoom>, String>((ref, token) async {
      final repo = ref.watch(housekeepingRepositoryProvider);

      final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.rooms(token);
    });

/// Every guest request, keyed by the housekeeper's token.
final housekeepingRequestsProvider =
    FutureProvider.family<List<HousekeepingGuestRequest>, String>((
      ref,
      token,
    ) async {
      final repo = ref.watch(housekeepingRepositoryProvider);

      final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.requests(token);
    });

/// Just the pre-checkout inspection requests reception has raised, keyed by
/// the housekeeper's token — the dedicated Inspection screen's own list, kept
/// separate from [housekeepingRequestsProvider] so a busy day of towel/DND
/// asks can never push an inspection off the general Requests feed.
final housekeepingInspectionRequestsProvider =
    FutureProvider.family<List<HousekeepingGuestRequest>, String>((
      ref,
      token,
    ) async {
      final repo = ref.watch(housekeepingRepositoryProvider);

      final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.requests(token, type: 'checkout_inspection');
    });

/// Subscribes the tablet to the hotel-wide `housekeeping` channel and
/// refreshes the notification feed, the Requests list and the dashboard the
/// instant a new one lands (today, only "a guest raised a housekeeping
/// request") — so a fresh ask appears within a couple of seconds instead of
/// waiting on the 15s poll. Pure accelerator: if no broadcaster is
/// configured, or the socket drops, those providers' own periodic polls
/// still carry the feed.
final housekeepingNotificationsRealtimeProvider =
    FutureProvider.family<void, String>((ref, token) async {
      final config = (await ref.watch(appConfigProvider.future)).realtime;
      if (config == null) {
        debugPrint(
          'housekeepingNotificationsRealtime: no broadcaster configured — polling only.',
        );
        return;
      }

      final channel = SosChannel(
        config: config,
        channel: 'housekeeping',
        events: const {},
      );
      channel.connect(
        onChanged: () {},
        onNotification: () {
          ref.invalidate(housekeepingNotificationsProvider(token));
          ref.invalidate(housekeepingRequestsProvider(token));
          ref.invalidate(housekeepingInspectionRequestsProvider(token));
          ref.invalidate(housekeepingOverviewProvider(token));
        },
      );

      ref.onDispose(channel.dispose);
    });

class HousekeepingActions {
  const HousekeepingActions(this._ref, this._token);

  final Ref _ref;
  final String _token;

  /// Mark a room clean, inspected, dirty again, or out of order, then
  /// refresh the room grid and the dashboard so both reflect it.
  Future<HousekeepingRoom> updateRoomStatus(int unitId, String status) async {
    final room = await _ref
        .read(housekeepingRepositoryProvider)
        .updateRoomStatus(_token, unitId, status);
    _ref.invalidate(housekeepingRoomsProvider(_token));
    _ref.invalidate(housekeepingOverviewProvider(_token));
    return room;
  }

  Future<HousekeepingGuestRequest> createRequest({
    required int roomUnitId,
    required String type,
    String? notes,
  }) async {
    final request = await _ref
        .read(housekeepingRepositoryProvider)
        .createRequest(
          _token,
          roomUnitId: roomUnitId,
          type: type,
          notes: notes,
        );
    _ref.invalidate(housekeepingRequestsProvider(_token));
    _ref.invalidate(housekeepingOverviewProvider(_token));
    return request;
  }

  Future<HousekeepingGuestRequest> completeRequest(int id) async {
    final request = await _ref
        .read(housekeepingRepositoryProvider)
        .completeRequest(_token, id);
    _ref.invalidate(housekeepingRequestsProvider(_token));
    _ref.invalidate(housekeepingInspectionRequestsProvider(_token));
    _ref.invalidate(housekeepingOverviewProvider(_token));
    return request;
  }

  /// Report a fault noticed while turning over a room — routed straight to
  /// maintenance's own Work Orders board. Nothing on the housekeeping
  /// tablet's own lists changes as a result, so no invalidation is needed
  /// here.
  Future<WorkOrder> reportFault({
    required int roomUnitId,
    required String title,
    String? description,
    String priority = 'medium',
  }) {
    return _ref
        .read(housekeepingRepositoryProvider)
        .reportFault(
          _token,
          roomUnitId: roomUnitId,
          title: title,
          description: description,
          priority: priority,
        );
  }
}

final housekeepingActionsProvider =
    Provider.family<HousekeepingActions, String>(
      (ref, token) => HousekeepingActions(ref, token),
    );
