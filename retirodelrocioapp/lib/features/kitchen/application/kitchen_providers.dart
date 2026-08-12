import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/sos_channel.dart';
import 'package:retirodelrocioapp/features/kitchen/data/kitchen_repository.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_menu_item.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_order.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_overview.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_staff.dart';
import 'package:retirodelrocioapp/features/kitchen/notifications/application/kitchen_notification_providers.dart';

final kitchenRepositoryProvider = Provider<KitchenRepository>(
  (ref) => KitchenRepository(),
);

/// The dashboard headline stats, keyed by the staffer's token. Polled every
/// 15 seconds so a freshly placed ticket appears without a manual refresh.
final kitchenOverviewProvider = FutureProvider.family<KitchenOverview, String>((
  ref,
  token,
) async {
  final repo = ref.watch(kitchenRepositoryProvider);

  final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.overview(token);
});

/// The Live Board — every active food ticket, from any source.
final kitchenLiveOrdersProvider =
    FutureProvider.family<List<KitchenOrder>, String>((ref, token) async {
      final repo = ref.watch(kitchenRepositoryProvider);

      final timer = Timer(const Duration(seconds: 12), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.liveOrders(token);
    });

/// One ticket's detail, keyed by (token, orderId) — used by the Ticket
/// Detail screen so it keeps working even after the ticket leaves the live
/// board's active-status filter (e.g. right after being marked served).
final kitchenOrderDetailProvider =
    FutureProvider.family<KitchenOrder?, (String, int)>((ref, args) async {
      final repo = ref.watch(kitchenRepositoryProvider);

      final timer = Timer(const Duration(seconds: 10), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.orderDetail(args.$1, args.$2);
    });

/// The dish menu with staff-visible availability, keyed by the staffer's token.
final kitchenMenuProvider =
    FutureProvider.family<List<KitchenMenuItem>, String>((ref, token) async {
      final repo = ref.watch(kitchenRepositoryProvider);
      return repo.menu(token);
    });

/// Served/cancelled tickets — the History / Recall screen.
final kitchenHistoryProvider =
    FutureProvider.family<List<KitchenOrder>, String>((ref, token) async {
      final repo = ref.watch(kitchenRepositoryProvider);
      return repo.history(token);
    });

/// The Assign to Station bottom sheet's picker.
final kitchenChefsProvider = FutureProvider.family<List<KitchenStaff>, String>((
  ref,
  token,
) async {
  final repo = ref.watch(kitchenRepositoryProvider);
  return repo.chefs(token);
});

/// Subscribes the tablet to the hotel-wide `kitchen` channel and refreshes
/// the notification feed, live board and dashboard the instant a new ticket
/// lands — the New Order Alert popup's trigger. Pure accelerator: if no
/// broadcaster is configured, or the socket drops, each provider's own
/// periodic poll still carries the feed.
final kitchenRealtimeProvider = FutureProvider.family<void, String>((
  ref,
  token,
) async {
  final config = (await ref.watch(appConfigProvider.future)).realtime;
  if (config == null) {
    debugPrint('kitchenRealtime: no broadcaster configured — polling only.');
    return;
  }

  final channel = SosChannel(
    config: config,
    channel: 'kitchen',
    events: const {},
  );
  channel.connect(
    onChanged: () {},
    onNotification: () {
      ref.invalidate(kitchenNotificationsProvider(token));
      ref.invalidate(kitchenLiveOrdersProvider(token));
      ref.invalidate(kitchenOverviewProvider(token));
    },
  );

  ref.onDispose(channel.dispose);
});

class KitchenActions {
  const KitchenActions(this._ref, this._token);

  final Ref _ref;
  final String _token;

  Future<KitchenOrder> markPreparing(int orderId) async {
    final order = await _ref
        .read(kitchenRepositoryProvider)
        .markPreparing(_token, orderId);
    _refreshBoard(orderId);
    return order;
  }

  Future<KitchenOrder> markServed(int orderId) async {
    final order = await _ref
        .read(kitchenRepositoryProvider)
        .markServed(_token, orderId);
    _refreshBoard(orderId);
    return order;
  }

  Future<KitchenOrder> voidItem(
    int orderId,
    int itemIndex, {
    String? reason,
  }) async {
    final order = await _ref
        .read(kitchenRepositoryProvider)
        .voidItem(_token, orderId, itemIndex, reason: reason);
    _refreshBoard(orderId);
    return order;
  }

  Future<KitchenOrder> assignOrder(int orderId, int chefId) async {
    final order = await _ref
        .read(kitchenRepositoryProvider)
        .assignOrder(_token, orderId, chefId);
    _refreshBoard(orderId);
    return order;
  }

  Future<KitchenMenuItem> toggleMenuItemAvailability(int id) async {
    final item = await _ref
        .read(kitchenRepositoryProvider)
        .toggleMenuItemAvailability(_token, id);
    _ref.invalidate(kitchenMenuProvider(_token));
    return item;
  }

  void _refreshBoard([int? orderId]) {
    _ref.invalidate(kitchenLiveOrdersProvider(_token));
    _ref.invalidate(kitchenOverviewProvider(_token));
    _ref.invalidate(kitchenHistoryProvider(_token));
    if (orderId != null) {
      _ref.invalidate(kitchenOrderDetailProvider((_token, orderId)));
    }
  }
}

final kitchenActionsProvider = Provider.family<KitchenActions, String>(
  (ref, token) => KitchenActions(ref, token),
);
