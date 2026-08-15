import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/sos_channel.dart';
import 'package:retirodelrocioapp/features/bar/data/bar_repository.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_menu_item.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_order.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_overview.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_tab.dart';
import 'package:retirodelrocioapp/features/bar/domain/bartender.dart';
import 'package:retirodelrocioapp/features/bar/notifications/application/bar_notification_providers.dart';

final barRepositoryProvider = Provider<BarRepository>((ref) => BarRepository());

/// The dashboard headline stats, keyed by the staffer's token. Polled every
/// 15 seconds so a freshly rung-up order appears without a manual refresh.
final barOverviewProvider = FutureProvider.family<BarOverview, String>((
  ref,
  token,
) async {
  final repo = ref.watch(barRepositoryProvider);

  final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.overview(token);
});

/// The Live Orders board — every active drink order, from any source.
final barLiveOrdersProvider = FutureProvider.family<List<BarOrder>, String>((
  ref,
  token,
) async {
  final repo = ref.watch(barRepositoryProvider);

  final timer = Timer(const Duration(seconds: 12), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.liveOrders(token);
});

/// One order's detail, keyed by (token, orderId) — used by the Order Detail
/// screen so it keeps working even after the order leaves the live board's
/// active-status filter (e.g. right after being marked served).
final barOrderDetailProvider = FutureProvider.family<BarOrder?, (String, int)>((
  ref,
  args,
) async {
  final repo = ref.watch(barRepositoryProvider);

  final timer = Timer(const Duration(seconds: 10), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.orderDetail(args.$1, args.$2);
});

/// The open Tabs list, keyed by the staffer's token.
final barOpenTabsProvider = FutureProvider.family<List<BarTab>, String>((
  ref,
  token,
) async {
  final repo = ref.watch(barRepositoryProvider);

  final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.tabs(token, status: 'open');
});

/// One tab's detail + its orders, keyed by (token, tabId).
final barTabDetailProvider = FutureProvider.family<BarTab?, (String, int)>((
  ref,
  args,
) async {
  final repo = ref.watch(barRepositoryProvider);

  final timer = Timer(const Duration(seconds: 10), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.tabDetail(args.$1, args.$2);
});

/// The drink menu with staff-visible availability, keyed by the staffer's token.
final barMenuProvider = FutureProvider.family<List<BarMenuItem>, String>((
  ref,
  token,
) async {
  final repo = ref.watch(barRepositoryProvider);
  return repo.menu(token);
});

/// Settled tabs + served/cancelled room orders — the History screen.
final barHistoryProvider =
    FutureProvider.family<(List<BarTab>, List<BarOrder>), String>((
      ref,
      token,
    ) async {
      final repo = ref.watch(barRepositoryProvider);
      return repo.history(token);
    });

/// The Assign Bartender bottom sheet's picker.
final barBartendersProvider = FutureProvider.family<List<Bartender>, String>((
  ref,
  token,
) async {
  final repo = ref.watch(barRepositoryProvider);
  return repo.bartenders(token);
});

/// Subscribes the tablet to the hotel-wide `bar` channel and refreshes the
/// notification feed, live order board, open tabs and dashboard the instant
/// a new order lands — the New Order Alert popup's trigger. Pure
/// accelerator: if no broadcaster is configured, or the socket drops, each
/// provider's own periodic poll still carries the feed.
final barRealtimeProvider = FutureProvider.family<void, String>((
  ref,
  token,
) async {
  final config = (await ref.watch(appConfigProvider.future)).realtime;
  if (config == null) {
    debugPrint('barRealtime: no broadcaster configured — polling only.');
    return;
  }

  final channel = SosChannel(config: config, channel: 'bar', events: const {});
  channel.connect(
    onChanged: () {},
    onNotification: () {
      ref.invalidate(barNotificationsProvider(token));
      ref.invalidate(barLiveOrdersProvider(token));
      ref.invalidate(barOpenTabsProvider(token));
      ref.invalidate(barOverviewProvider(token));
    },
  );

  ref.onDispose(channel.dispose);
});

class BarActions {
  const BarActions(this._ref, this._token);

  final Ref _ref;
  final String _token;

  Future<BarOrder> markPreparing(int orderId) async {
    final order = await _ref
        .read(barRepositoryProvider)
        .markPreparing(_token, orderId);
    _refreshBoard(orderId);
    return order;
  }

  Future<BarOrder> markServed(int orderId) async {
    final order = await _ref
        .read(barRepositoryProvider)
        .markServed(_token, orderId);
    _refreshBoard(orderId);
    return order;
  }

  Future<BarOrder> markOnTheWay(int orderId) async {
    final order = await _ref
        .read(barRepositoryProvider)
        .markOnTheWay(_token, orderId);
    _refreshBoard(orderId);
    return order;
  }

  Future<BarOrder> verifyAge(int orderId) async {
    final order = await _ref
        .read(barRepositoryProvider)
        .verifyAge(_token, orderId);
    _refreshBoard(orderId);
    return order;
  }

  Future<BarOrder> voidItem(
    int orderId,
    int itemIndex, {
    String? reason,
  }) async {
    final order = await _ref
        .read(barRepositoryProvider)
        .voidItem(_token, orderId, itemIndex, reason: reason);
    _refreshBoard(orderId);
    return order;
  }

  Future<BarOrder> assignOrder(int orderId, int bartenderId) async {
    final order = await _ref
        .read(barRepositoryProvider)
        .assignOrder(_token, orderId, bartenderId);
    _refreshBoard(orderId);
    return order;
  }

  Future<BarTab> openTab({
    String? tableLabel,
    String? guestName,
    bool isVip = false,
  }) async {
    final tab = await _ref
        .read(barRepositoryProvider)
        .openTab(
          _token,
          tableLabel: tableLabel,
          guestName: guestName,
          isVip: isVip,
        );
    _ref.invalidate(barOpenTabsProvider(_token));
    return tab;
  }

  Future<BarTab> addOrderToTab(
    int tabId,
    List<Map<String, dynamic>> items,
  ) async {
    final tab = await _ref
        .read(barRepositoryProvider)
        .addOrderToTab(_token, tabId, items);
    _refreshBoard();
    _ref.invalidate(barTabDetailProvider((_token, tabId)));
    return tab;
  }

  Future<BarTab> closeTab(
    int tabId,
    String paymentMethod, {
    int? bookingId,
  }) async {
    final tab = await _ref
        .read(barRepositoryProvider)
        .closeTab(_token, tabId, paymentMethod, bookingId: bookingId);
    _refreshBoard();
    _ref.invalidate(barTabDetailProvider((_token, tabId)));
    _ref.invalidate(barHistoryProvider(_token));
    return tab;
  }

  /// Push a confirmed reservation straight into a new, pre-filled tab.
  Future<BarTab> confirmReservationToTab(int reservationId) async {
    final tab = await _ref
        .read(barRepositoryProvider)
        .confirmReservationToTab(_token, reservationId);
    _ref.invalidate(barOpenTabsProvider(_token));
    return tab;
  }

  Future<BarTab> toggleVip(int tabId) async {
    final tab = await _ref.read(barRepositoryProvider).toggleVip(_token, tabId);
    _ref.invalidate(barOpenTabsProvider(_token));
    _ref.invalidate(barTabDetailProvider((_token, tabId)));
    return tab;
  }

  Future<BarMenuItem> toggleMenuItemAvailability(int id) async {
    final item = await _ref
        .read(barRepositoryProvider)
        .toggleMenuItemAvailability(_token, id);
    _ref.invalidate(barMenuProvider(_token));
    return item;
  }

  void _refreshBoard([int? orderId]) {
    _ref.invalidate(barLiveOrdersProvider(_token));
    _ref.invalidate(barOverviewProvider(_token));
    _ref.invalidate(barOpenTabsProvider(_token));
    if (orderId != null) {
      _ref.invalidate(barOrderDetailProvider((_token, orderId)));
    }
  }
}

final barActionsProvider = Provider.family<BarActions, String>(
  (ref, token) => BarActions(ref, token),
);
