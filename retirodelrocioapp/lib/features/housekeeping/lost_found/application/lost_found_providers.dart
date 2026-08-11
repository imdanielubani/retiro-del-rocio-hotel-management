import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/housekeeping/lost_found/data/lost_found_repository.dart';
import 'package:retirodelrocioapp/features/housekeeping/lost_found/domain/lost_found_item.dart';

final lostFoundRepositoryProvider = Provider<LostFoundRepository>(
  (ref) => LostFoundRepository(),
);

/// Every logged Lost & Found item, keyed by the housekeeper's token. Polled
/// every 15 seconds, matching the rest of the housekeeping tablet's lists.
final lostFoundItemsProvider = FutureProvider.family<List<LostFoundItem>, String>((ref, token) async {
  final repo = ref.watch(lostFoundRepositoryProvider);

  final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.items(token);
});

class LostFoundActions {
  const LostFoundActions(this._ref, this._token);

  final Ref _ref;
  final String _token;

  Future<LostFoundItem> createItem({
    int? roomUnitId,
    required String itemDescription,
    String? notes,
  }) async {
    final item = await _ref
        .read(lostFoundRepositoryProvider)
        .createItem(_token, roomUnitId: roomUnitId, itemDescription: itemDescription, notes: notes);
    _ref.invalidate(lostFoundItemsProvider(_token));
    return item;
  }

  Future<LostFoundItem> markReturned(int id, {String? claimantName, String? claimantContact}) async {
    final item = await _ref
        .read(lostFoundRepositoryProvider)
        .markReturned(_token, id, claimantName: claimantName, claimantContact: claimantContact);
    _ref.invalidate(lostFoundItemsProvider(_token));
    return item;
  }

  Future<LostFoundItem> markDisposed(int id) async {
    final item = await _ref.read(lostFoundRepositoryProvider).markDisposed(_token, id);
    _ref.invalidate(lostFoundItemsProvider(_token));
    return item;
  }
}

final lostFoundActionsProvider = Provider.family<LostFoundActions, String>(
  (ref, token) => LostFoundActions(ref, token),
);
