import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/guest/bills/application/bills_providers.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/data/my_stay_repository.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/domain/guest_stay.dart';
import 'package:retirodelrocioapp/features/welcome/application/room_status_providers.dart';

final myStayRepositoryProvider = Provider<MyStayRepository>(
  (ref) => MyStayRepository(),
);

/// The current reservation for this room, keyed by the tablet's device token.
///
/// Re-polled every 30 seconds so a change made at reception (a rate edit, a
/// checkout move) reflects on the tablet without a manual refresh — the same
/// belt-and-braces poll as room status, just slower because the stay rarely
/// changes minute to minute.
final myStayProvider = FutureProvider.family<GuestStay, String>((
  ref,
  deviceToken,
) async {
  final repo = ref.watch(myStayRepositoryProvider);

  final timer = Timer(const Duration(seconds: 30), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.fetch(deviceToken);
});

/// Extend the stay, then refresh both the stay and the room status so every
/// guest screen renders the new checkout from the server.
class MyStayActions {
  const MyStayActions(this._ref, this._deviceToken);

  final Ref _ref;
  final String _deviceToken;

  /// Price the extension and open a Paystack charge.
  Future<ExtensionQuote> initializeExtension(DateTime newCheckOut) => _ref
      .read(myStayRepositoryProvider)
      .initializeExtension(_deviceToken, newCheckOut);

  /// Verify the paid [reference] and apply the extension, then refresh the stay
  /// and room status so every guest screen renders the new checkout.
  Future<GuestStay> extend(DateTime newCheckOut, String reference) async {
    final stay = await _ref
        .read(myStayRepositoryProvider)
        .extend(_deviceToken, newCheckOut, reference);
    _ref.invalidate(myStayProvider(_deviceToken));
    _ref.invalidate(roomStatusProvider(_deviceToken));
    return stay;
  }

  /// Extend the stay straight against the room's folio, then refresh the
  /// stay, room status and My Bills — the new charge is real outstanding
  /// balance the guest can pay off from there.
  Future<GuestStay> chargeToRoom(DateTime newCheckOut) async {
    final stay = await _ref
        .read(myStayRepositoryProvider)
        .chargeToRoom(_deviceToken, newCheckOut);
    _ref.invalidate(myStayProvider(_deviceToken));
    _ref.invalidate(roomStatusProvider(_deviceToken));
    _ref.invalidate(billProvider(_deviceToken));
    return stay;
  }
}

final myStayActionsProvider = Provider.family<MyStayActions, String>(
  (ref, deviceToken) => MyStayActions(ref, deviceToken),
);
