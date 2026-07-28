import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/guest/spa/data/spa_repository.dart';
import 'package:retirodelrocioapp/features/guest/spa/domain/spa_service.dart';

final spaRepositoryProvider = Provider<SpaRepository>((ref) => SpaRepository());

/// The spa catalogue, keyed by the tablet's device token. `autoDispose` so
/// re-opening the screen always starts from a fresh fetch — a service the
/// admin just activated (or deactivated) shows correctly rather than being
/// hidden behind a stale cache.
final spaCatalogProvider = FutureProvider.autoDispose
    .family<SpaCatalog, String>((ref, deviceToken) {
      final repo = ref.watch(spaRepositoryProvider);
      return repo.fetch(deviceToken);
    });

/// The guest's own confirmed spa sessions, keyed by the tablet's device
/// token. `autoDispose` so re-opening the screen always shows a session just
/// booked, rather than a stale cached list.
final spaAppointmentsProvider = FutureProvider.autoDispose
    .family<List<SpaAppointment>, String>((ref, deviceToken) {
      final repo = ref.watch(spaRepositoryProvider);
      return repo.appointments(deviceToken);
    });

class SpaActions {
  const SpaActions(this._ref, this._deviceToken);

  final Ref _ref;
  final String _deviceToken;

  Future<SpaBookingConfirmation> bookToRoom(
    String serviceSlug,
    String time,
  ) async {
    final confirmation = await _ref
        .read(spaRepositoryProvider)
        .bookToRoom(_deviceToken, serviceSlug, time);
    _ref.invalidate(spaAppointmentsProvider(_deviceToken));
    return confirmation;
  }

  Future<SpaBookingQuote> initializePaystack(String serviceSlug, String time) =>
      _ref
          .read(spaRepositoryProvider)
          .initializePaystack(_deviceToken, serviceSlug, time);

  Future<SpaBookingConfirmation> confirmPaystack(String reference) async {
    final confirmation = await _ref
        .read(spaRepositoryProvider)
        .confirmPaystack(_deviceToken, reference);
    _ref.invalidate(spaAppointmentsProvider(_deviceToken));
    return confirmation;
  }
}

final spaActionsProvider = Provider.family<SpaActions, String>(
  (ref, deviceToken) => SpaActions(ref, deviceToken),
);
