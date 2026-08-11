import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/guest/visitor_pass/data/visitor_pass_repository.dart';
import 'package:retirodelrocioapp/features/guest/visitor_pass/domain/visitor_pass.dart';

final visitorPassRepositoryProvider = Provider<VisitorPassRepository>(
  (ref) => VisitorPassRepository(),
);

/// This room's visitor history, keyed by the tablet's device token.
///
/// Re-polled every 30 seconds so a security verify / deny reflects on the guest
/// tablet without a manual refresh — the same belt-and-braces poll as room
/// status and SOS, just slower because a pass is not time-critical.
final visitorPassesProvider = FutureProvider.family<List<VisitorPass>, String>((
  ref,
  deviceToken,
) async {
  final repo = ref.watch(visitorPassRepositoryProvider);

  final timer = Timer(const Duration(seconds: 30), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.list(deviceToken);
});

/// Issue a visitor pass, then refresh the history so the list renders from the
/// server — one source of truth — rather than from whatever the call returned.
class VisitorPassActions {
  const VisitorPassActions(this._ref, this._deviceToken);

  final Ref _ref;
  final String _deviceToken;

  Future<VisitorPass> create({
    required String name,
    String? email,
    String? phone,
  }) async {
    final pass = await _ref
        .read(visitorPassRepositoryProvider)
        .create(_deviceToken, name: name, email: email, phone: phone);
    _ref.invalidate(visitorPassesProvider(_deviceToken));
    return pass;
  }
}

final visitorPassActionsProvider = Provider.family<VisitorPassActions, String>(
  (ref, deviceToken) => VisitorPassActions(ref, deviceToken),
);
