import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/guest/cinema/data/cinema_repository.dart';
import 'package:retirodelrocioapp/features/guest/cinema/domain/cinema_service.dart';

final cinemaRepositoryProvider = Provider<CinemaRepository>(
  (ref) => CinemaRepository(),
);

/// The cinema catalogue, keyed by the tablet's device token. `autoDispose` so
/// re-opening the screen always starts from a fresh fetch — a movie the
/// admin just activated (or deactivated) shows correctly rather than being
/// hidden behind a stale cache.
final cinemaCatalogProvider = FutureProvider.autoDispose
    .family<CinemaCatalog, String>((ref, deviceToken) {
      final repo = ref.watch(cinemaRepositoryProvider);
      return repo.fetch(deviceToken);
    });

/// The guest's own confirmed cinema bookings, keyed by the tablet's device
/// token. `autoDispose` so re-opening the screen always shows a booking just
/// made, rather than a stale cached list.
final cinemaBookingsProvider = FutureProvider.autoDispose
    .family<List<CinemaBookingAppointment>, String>((ref, deviceToken) {
      final repo = ref.watch(cinemaRepositoryProvider);
      return repo.bookings(deviceToken);
    });

/// Which private rooms are already taken for a given movie/date/time — keyed
/// by a composite string so picking a different showing re-fetches.
final cinemaRoomAvailabilityProvider = FutureProvider.autoDispose
    .family<
      List<String>,
      ({String deviceToken, String movieSlug, String date, String time})
    >((ref, args) {
      final repo = ref.watch(cinemaRepositoryProvider);
      return repo.roomAvailability(
        args.deviceToken,
        args.movieSlug,
        args.date,
        args.time,
      );
    });

class CinemaActions {
  const CinemaActions(this._ref, this._deviceToken);

  final Ref _ref;
  final String _deviceToken;

  Future<CinemaBookingConfirmation> bookToRoom(
    Map<String, dynamic> payload,
  ) async {
    final confirmation = await _ref
        .read(cinemaRepositoryProvider)
        .bookToRoom(_deviceToken, payload);
    _ref.invalidate(cinemaBookingsProvider(_deviceToken));
    return confirmation;
  }

  Future<CinemaBookingQuote> initializePaystack(Map<String, dynamic> payload) =>
      _ref
          .read(cinemaRepositoryProvider)
          .initializePaystack(_deviceToken, payload);

  Future<CinemaBookingConfirmation> confirmPaystack(String reference) async {
    final confirmation = await _ref
        .read(cinemaRepositoryProvider)
        .confirmPaystack(_deviceToken, reference);
    _ref.invalidate(cinemaBookingsProvider(_deviceToken));
    return confirmation;
  }
}

final cinemaActionsProvider = Provider.family<CinemaActions, String>(
  (ref, deviceToken) => CinemaActions(ref, deviceToken),
);
