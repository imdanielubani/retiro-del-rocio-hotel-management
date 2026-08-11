import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/guest/dining/data/dining_repository.dart';
import 'package:retirodelrocioapp/features/guest/dining/domain/menu_item.dart';

final diningRepositoryProvider = Provider<DiningRepository>(
  (ref) => DiningRepository(),
);

/// The restaurant menu, keyed by the tablet's device token. `autoDispose` so
/// re-opening the screen always starts from a fresh fetch — a dish the
/// admin just activated (or deactivated) shows correctly rather than being
/// hidden behind a stale cache.
final diningMenuProvider = FutureProvider.autoDispose
    .family<DiningMenu, String>((ref, deviceToken) {
      final repo = ref.watch(diningRepositoryProvider);
      return repo.fetchMenu(deviceToken);
    });

/// The guest's own confirmed dining orders, keyed by the tablet's device
/// token. `autoDispose` so re-opening My Orders always shows an order just
/// placed, rather than a stale cached list.
final diningOrdersProvider = FutureProvider.autoDispose
    .family<List<DiningOrderSummary>, String>((ref, deviceToken) {
      final repo = ref.watch(diningRepositoryProvider);
      return repo.orders(deviceToken);
    });

class DiningActions {
  const DiningActions(this._ref, this._deviceToken);

  final Ref _ref;
  final String _deviceToken;

  Future<DiningOrderConfirmation> bookToRoom(
    Map<String, dynamic> payload,
  ) async {
    final confirmation = await _ref
        .read(diningRepositoryProvider)
        .bookToRoom(_deviceToken, payload);
    _ref.invalidate(diningOrdersProvider(_deviceToken));
    return confirmation;
  }

  Future<DiningOrderQuote> initializePaystack(Map<String, dynamic> payload) =>
      _ref
          .read(diningRepositoryProvider)
          .initializePaystack(_deviceToken, payload);

  Future<DiningOrderConfirmation> confirmPaystack(String reference) async {
    final confirmation = await _ref
        .read(diningRepositoryProvider)
        .confirmPaystack(_deviceToken, reference);
    _ref.invalidate(diningOrdersProvider(_deviceToken));
    return confirmation;
  }
}

final diningActionsProvider = Provider.family<DiningActions, String>(
  (ref, deviceToken) => DiningActions(ref, deviceToken),
);
