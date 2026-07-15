import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/realtime/realtime_config.dart';
import 'package:web_socket_channel/web_socket_channel.dart';

/// Listens for "this room changed" pings from the backend (Laravel Reverb).
///
/// Reverb speaks the Pusher protocol, so no heavyweight client is needed — but
/// the handshake order matters:
///
///   1. connect, and **wait for the socket to be ready**;
///   2. wait for the server's `pusher:connection_established`;
///   3. only then send `pusher:subscribe`.
///
/// Subscribing before the handshake completes silently drops the frame, and the
/// tablet sits there subscribed to nothing. The server also pings periodically
/// and hangs up on a client that never pongs.
///
/// The server sends a *signal only* — never guest data — so the tablet reacts by
/// re-fetching `room-status` with its own token.
///
/// This is an accelerator, not a dependency: the periodic poll stays in place,
/// so a Reverb that is down (or hotel Wi-Fi that dropped) degrades to the old
/// 20-second behaviour rather than breaking check-in.
class RoomChannel {
  RoomChannel({required this.config, required this.roomUnitId});

  final RealtimeConfig config;
  final int roomUnitId;

  WebSocketChannel? _socket;
  StreamSubscription<dynamic>? _subscription;
  Timer? _reconnect;
  bool _closed = false;

  String get _channel => 'rooms.$roomUnitId';

  /// Calls [onChanged] whenever this room's occupancy changes.
  void connect({required VoidCallback onChanged}) {
    _closed = false;
    unawaited(_open(onChanged));
  }

  Future<void> _open(VoidCallback onChanged) async {
    if (_closed) return;

    try {
      final socket = WebSocketChannel.connect(config.socketUri);
      _socket = socket;

      _subscription = socket.stream.listen(
        (message) => _onMessage(message, onChanged),
        onError: (Object error) {
          debugPrint('RoomChannel: socket error — $error');
          _scheduleReconnect(onChanged);
        },
        onDone: () {
          debugPrint('RoomChannel: socket closed — will retry.');
          _scheduleReconnect(onChanged);
        },
        cancelOnError: true,
      );

      // Frames written before the handshake completes are lost.
      await socket.ready;
      debugPrint('RoomChannel: connected to ${config.socketUri.host} — awaiting handshake.');
    } catch (error) {
      debugPrint('RoomChannel: connect failed — $error');
      _scheduleReconnect(onChanged);
    }
  }

  void _onMessage(dynamic message, VoidCallback onChanged) {
    if (message is! String) return;

    try {
      final frame = jsonDecode(message) as Map<String, dynamic>;
      final event = frame['event'] as String?;

      switch (event) {
        // The server is ready for us — now, and only now, subscribe.
        case 'pusher:connection_established':
          _send({
            'event': 'pusher:subscribe',
            'data': {'channel': _channel},
          });

        case 'pusher_internal:subscription_succeeded':
          debugPrint('RoomChannel: subscribed to $_channel — updates are live.');

        // Reverb hangs up on a client that never answers.
        case 'pusher:ping':
          _send({'event': 'pusher:pong', 'data': {}});

        case 'room.status.changed':
          debugPrint('RoomChannel: $_channel changed — refreshing.');
          onChanged();
      }
    } catch (error) {
      debugPrint('RoomChannel: bad frame — $error');
    }
  }

  void _send(Map<String, dynamic> frame) {
    _socket?.sink.add(jsonEncode(frame));
  }

  void _scheduleReconnect(VoidCallback onChanged) {
    if (_closed || _reconnect != null) return;

    _teardownSocket();
    _reconnect = Timer(const Duration(seconds: 10), () {
      _reconnect = null;
      unawaited(_open(onChanged));
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
