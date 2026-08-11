import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/realtime/realtime_config.dart';
import 'package:web_socket_channel/web_socket_channel.dart';

/// Listens for "an SOS changed" ping (Laravel Reverb / Pusher protocol) and
/// fires a callback so the listener can re-fetch. Used three ways:
///
///  • the security tablet subscribes to the hotel-wide `sos` channel, lighting
///    up the instant any guest raises an emergency;
///  • a guest tablet subscribes to its own room's `rooms.{id}` channel, so the
///    "Help is on the way" screen flips to "Security is on their way" the moment
///    an officer acknowledges — not on the next poll;
///  • the reception tablet subscribes to the hotel-wide `reception` channel for
///    both `booking.changed` ([onChanged]) and `notification.created`
///    ([onNotification]) — one socket, two distinct signals, same idea as
///    [RoomChannel] multiplexing room status and guest notifications.
///
/// Same handshake discipline as [RoomChannel]: wait for the socket to be ready,
/// wait for `pusher:connection_established`, only then subscribe; and answer
/// `pusher:ping` so Reverb does not hang up.
///
/// The broadcast is signal-only — it never carries guest data. The listener
/// re-fetches with its own token in response, and the periodic poll stays in
/// place so a dropped socket degrades gracefully rather than going blind.
class SosChannel {
  SosChannel({
    required this.config,
    this.channel = 'sos',
    this.events = const {'sos.changed'},
    this.notificationEvent = 'notification.created',
  });

  final RealtimeConfig config;

  /// The Reverb channel to subscribe to — `sos`, `reception`, or `rooms.{id}`.
  final String channel;

  /// The application event names on that channel that should fire [onChanged].
  /// Defaults to the SOS ping; a reception subscription passes `booking.changed`.
  final Set<String> events;

  /// The event name that should fire [onNotification] instead of [onChanged].
  final String notificationEvent;

  WebSocketChannel? _socket;
  StreamSubscription<dynamic>? _subscription;
  Timer? _reconnect;
  bool _closed = false;

  /// Calls [onChanged] whenever any alert is raised, acknowledged or resolved
  /// (or, on a `reception` subscription, whenever a booking changes), and
  /// [onNotification] whenever a new notification lands (defaults to a no-op
  /// for callers that don't care, e.g. the security tablet).
  void connect({required VoidCallback onChanged, VoidCallback? onNotification}) {
    _closed = false;
    unawaited(_open(onChanged, onNotification ?? () {}));
  }

  Future<void> _open(VoidCallback onChanged, VoidCallback onNotification) async {
    if (_closed) return;

    try {
      final socket = WebSocketChannel.connect(config.socketUri);
      _socket = socket;

      _subscription = socket.stream.listen(
        (message) => _onMessage(message, onChanged, onNotification),
        onError: (Object error) {
          debugPrint('SosChannel: socket error — $error');
          _scheduleReconnect(onChanged, onNotification);
        },
        onDone: () {
          debugPrint('SosChannel: socket closed — will retry.');
          _scheduleReconnect(onChanged, onNotification);
        },
        cancelOnError: true,
      );

      // Frames written before the handshake completes are lost.
      await socket.ready;
      debugPrint('SosChannel: connected to ${config.socketUri.host} — awaiting handshake.');
    } catch (error) {
      debugPrint('SosChannel: connect failed — $error');
      _scheduleReconnect(onChanged, onNotification);
    }
  }

  void _onMessage(
    dynamic message,
    VoidCallback onChanged,
    VoidCallback onNotification,
  ) {
    if (message is! String) return;

    try {
      final frame = jsonDecode(message) as Map<String, dynamic>;
      final event = frame['event'] as String?;
      switch (event) {
        // The server is ready — now, and only now, subscribe.
        case 'pusher:connection_established':
          _send({
            'event': 'pusher:subscribe',
            'data': {'channel': channel},
          });
          return;

        case 'pusher_internal:subscription_succeeded':
          debugPrint('SosChannel: subscribed to $channel — updates are live.');
          return;

        // Reverb hangs up on a client that never answers.
        case 'pusher:ping':
          _send({'event': 'pusher:pong', 'data': {}});
          return;
      }

      if (event == notificationEvent) {
        debugPrint('SosChannel: $channel got a new notification.');
        onNotification();
        return;
      }

      // Any application event this channel cares about triggers a re-fetch.
      if (event != null && events.contains(event)) {
        debugPrint('SosChannel: $event on $channel — refreshing.');
        onChanged();
      }
    } catch (error) {
      debugPrint('SosChannel: bad frame — $error');
    }
  }

  void _send(Map<String, dynamic> frame) {
    _socket?.sink.add(jsonEncode(frame));
  }

  void _scheduleReconnect(VoidCallback onChanged, VoidCallback onNotification) {
    if (_closed || _reconnect != null) return;

    _teardownSocket();
    _reconnect = Timer(const Duration(seconds: 10), () {
      _reconnect = null;
      unawaited(_open(onChanged, onNotification));
    });
  }

  void _teardownSocket() {
    _subscription?.cancel();
    _subscription = null;
    _socket?.sink.close();
    _socket = null;
  }

  void dispose() {
    _closed = true;
    _reconnect?.cancel();
    _reconnect = null;
    _teardownSocket();
  }
}
