import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/realtime/realtime_config.dart';
import 'package:web_socket_channel/web_socket_channel.dart';

/// Listens for "this room changed" pings from the backend (Laravel Reverb).
///
/// Reverb speaks the Pusher protocol, so subscribing is a single JSON frame; no
/// heavyweight client is needed. The server sends a *signal only* — never guest
/// data — so the tablet reacts by re-fetching `room-status` with its own token.
///
/// This is an accelerator, not a dependency: the tablet's periodic poll stays in
/// place, so a Reverb that is down (or hotel Wi-Fi that dropped) degrades to the
/// old 20-second behaviour rather than breaking check-in.
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
    _open(onChanged);
  }

  void _open(VoidCallback onChanged) {
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

      _send({
        'event': 'pusher:subscribe',
        'data': {'channel': _channel},
      });
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

      // Reverb prefixes app events with the broadcast name we set server-side.
      if (event == 'room.status.changed') {
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
      _open(onChanged);
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
