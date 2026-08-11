import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/guest/bills/data/bills_repository.dart';
import 'package:retirodelrocioapp/features/guest/bills/domain/bill.dart';

final billsRepositoryProvider = Provider<BillsRepository>(
  (ref) => BillsRepository(),
);

/// The guest's itemised folio, keyed by the tablet's device token.
/// `autoDispose` so re-opening the screen always starts from a fresh
/// fetch — a charge just booked elsewhere (spa, extension) shows correctly
/// rather than being hidden behind a stale cache.
final billProvider = FutureProvider.autoDispose.family<Bill, String>((
  ref,
  deviceToken,
) {
  final repo = ref.watch(billsRepositoryProvider);
  return repo.fetch(deviceToken);
});

class BillActions {
  const BillActions(this._ref, this._deviceToken);

  final Ref _ref;
  final String _deviceToken;

  Future<BillPaymentQuote> initializePaystack() =>
      _ref.read(billsRepositoryProvider).initializePaystack(_deviceToken);

  Future<BillPaymentConfirmation> confirmPaystack(String reference) => _ref
      .read(billsRepositoryProvider)
      .confirmPaystack(_deviceToken, reference);
}

final billActionsProvider = Provider.family<BillActions, String>(
  (ref, deviceToken) => BillActions(ref, deviceToken),
);
