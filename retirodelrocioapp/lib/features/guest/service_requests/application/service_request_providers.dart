import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/guest/service_requests/data/service_request_repository.dart';
import 'package:retirodelrocioapp/features/guest/service_requests/domain/guest_service_request.dart';

final serviceRequestRepositoryProvider = Provider<ServiceRequestRepository>(
  (ref) => ServiceRequestRepository(),
);

/// This stay's request history, keyed by the device token. Re-polled every
/// 20 seconds so a status change made by housekeeping/maintenance (e.g.
/// marked complete) appears without a manual refresh.
final guestServiceRequestsProvider =
    FutureProvider.family<List<GuestServiceRequest>, String>((
      ref,
      deviceToken,
    ) async {
      final repo = ref.watch(serviceRequestRepositoryProvider);

      final timer = Timer(const Duration(seconds: 20), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.list(deviceToken);
    });

/// The admin-managed catalog of housekeeping request types, keyed by device
/// token. Re-polled every 60 seconds — far less often than the request
/// history, since the catalog only changes when an admin edits it — so an
/// admin's edit reaches an already-open tablet within a minute.
final housekeepingRequestTypesProvider =
    FutureProvider.family<List<HousekeepingRequestTypeOption>, String>((
      ref,
      deviceToken,
    ) async {
      final repo = ref.watch(serviceRequestRepositoryProvider);

      final timer = Timer(const Duration(seconds: 60), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.types(deviceToken);
    });

class ServiceRequestActions {
  const ServiceRequestActions(this._ref, this._deviceToken);

  final Ref _ref;
  final String _deviceToken;

  Future<GuestServiceRequest> createHousekeeping({
    required String type,
    String? notes,
  }) async {
    final request = await _ref
        .read(serviceRequestRepositoryProvider)
        .createHousekeeping(_deviceToken, type: type, notes: notes);
    _ref.invalidate(guestServiceRequestsProvider(_deviceToken));
    return request;
  }

  Future<GuestServiceRequest> createMaintenance({
    required String title,
    String? description,
    String priority = 'medium',
  }) async {
    final request = await _ref
        .read(serviceRequestRepositoryProvider)
        .createMaintenance(
          _deviceToken,
          title: title,
          description: description,
          priority: priority,
        );
    _ref.invalidate(guestServiceRequestsProvider(_deviceToken));
    return request;
  }
}

final serviceRequestActionsProvider =
    Provider.family<ServiceRequestActions, String>(
      (ref, deviceToken) => ServiceRequestActions(ref, deviceToken),
    );
