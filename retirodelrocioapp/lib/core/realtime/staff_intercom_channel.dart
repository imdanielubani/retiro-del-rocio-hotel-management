import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/realtime/realtime_config.dart';
import 'package:web_socket_channel/web_socket_channel.dart';

/// Listens for Intercom call signals on a staff public channel — either an
/// individual staffer's own (`staff-intercom.user.{id}`, for a staff-to-
/// staff call addressed to them specifically) or a whole department's
/// (`staff-intercom.{role}`, for a guest calling the front desk as a
/// whole) — the staff-side counterpart of the `intercom.ringing`/
/// `intercom.updated` handling {@see RoomChannel} does for a guest's room.
/// Same handshake discipline as every other realtime channel here: wait
/// for the socket, wait for `pusher:connection_established`, only then
/// subscribe; answer `pusher:ping` so Reverb doesn't hang up.
///
/// Both signals mean the same thing to a caller — "go re-fetch the current
/// call" — so they share one callback rather than two, unlike `RoomChannel`
/// which keeps them distinct for readability alongside its other events.
class StaffIntercomChannel {
  StaffIntercomChannel({required this.config, required this.channel});

  final RealtimeConfig config;

  /// The full public channel name, e.g. `staff-intercom.user.12` or
  /// `staff-intercom.reception`.
  final String channel;

  WebSocketChannel? _socket;
  StreamSubscription<dynamic>? _subscription;
  Timer? _reconnect;
  bool _closed = false;

  void connect({required VoidCallback onSignal}) {
    _closed = false;
    unawaited(_open(onSignal));
  }

  Future<void> _open(VoidCallback onSignal) async {
    if (_closed) return;

    try {
      final socket = WebSocketChannel.connect(config.socketUri);
      _socket = socket;

      _subscription = socket.stream.listen(
        (message) => _onMessage(message, onSignal),
        onError: (Object error) {
          debugPrint('StaffIntercomChannel: socket error — $error');
          _scheduleReconnect(onSignal);
        },
        onDone: () {
          debugPrint('StaffIntercomChannel: socket closed — will retry.');
          _scheduleReconnect(onSignal);
        },
        cancelOnError: true,
      );

      await socket.ready;
      debugPrint(
        'StaffIntercomChannel: connected to ${config.socketUri.host} — awaiting handshake.',
      );
    } catch (error) {
      debugPrint('StaffIntercomChannel: connect failed — $error');
      _scheduleReconnect(onSignal);
    }
  }

  void _onMessage(dynamic message, VoidCallback onSignal) {
    if (message is! String) return;

    try {
      final frame = jsonDecode(message) as Map<String, dynamic>;
      final event = frame['event'] as String?;

      switch (event) {
        case 'pusher:connection_established':
          _send({
            'event': 'pusher:subscribe',
            'data': {'channel': channel},
          });

        case 'pusher_internal:subscription_succeeded':
          debugPrint(
            'StaffIntercomChannel: subscribed to $channel — updates are live.',
          );

        case 'pusher:ping':
          _send({'event': 'pusher:pong', 'data': {}});

        case 'intercom.ringing':
        case 'intercom.updated':
          debugPrint('StaffIntercomChannel: $channel call signal.');
          onSignal();
      }
    } catch (error) {
      debugPrint('StaffIntercomChannel: bad frame — $error');
    }
  }

  void _send(Map<String, dynamic> frame) {
    _socket?.sink.add(jsonEncode(frame));
  }

  void _scheduleReconnect(VoidCallback onSignal) {
    if (_closed || _reconnect != null) return;

    _teardownSocket();
    _reconnect = Timer(const Duration(seconds: 10), () {
      _reconnect = null;
      unawaited(_open(onSignal));
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
