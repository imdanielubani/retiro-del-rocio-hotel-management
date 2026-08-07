import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/realtime/realtime_config.dart';
import 'package:web_socket_channel/web_socket_channel.dart';

/// Listens for a `typing` signal on an arbitrary named public channel —
/// the generic form of the `typing` handling in {@see RoomChannel}, for
/// channels that aren't tied to a room (e.g. `staff-chat.{channelKey}`).
///
/// Same handshake discipline as `RoomChannel`/`SosChannel`: wait for the
/// socket to be ready, wait for `pusher:connection_established`, only then
/// subscribe; answer `pusher:ping` so Reverb doesn't hang up.
class NamedTypingChannel {
  NamedTypingChannel({required this.config, required this.channel});

  final RealtimeConfig config;
  final String channel;

  WebSocketChannel? _socket;
  StreamSubscription<dynamic>? _subscription;
  Timer? _reconnect;
  bool _closed = false;

  /// Calls [onTyping] whenever a `typing` signal arrives, carrying the
  /// sender's own role (e.g. `'maintenance'`).
  void connect({required ValueChanged<String> onTyping}) {
    _closed = false;
    unawaited(_open(onTyping));
  }

  Future<void> _open(ValueChanged<String> onTyping) async {
    if (_closed) return;

    try {
      final socket = WebSocketChannel.connect(config.socketUri);
      _socket = socket;

      _subscription = socket.stream.listen(
        (message) => _onMessage(message, onTyping),
        onError: (Object error) {
          debugPrint('NamedTypingChannel: socket error — $error');
          _scheduleReconnect(onTyping);
        },
        onDone: () {
          debugPrint('NamedTypingChannel: socket closed — will retry.');
          _scheduleReconnect(onTyping);
        },
        cancelOnError: true,
      );

      await socket.ready;
      debugPrint(
        'NamedTypingChannel: connected to ${config.socketUri.host} — awaiting handshake.',
      );
    } catch (error) {
      debugPrint('NamedTypingChannel: connect failed — $error');
      _scheduleReconnect(onTyping);
    }
  }

  void _onMessage(dynamic message, ValueChanged<String> onTyping) {
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
            'NamedTypingChannel: subscribed to $channel — updates are live.',
          );

        case 'pusher:ping':
          _send({'event': 'pusher:pong', 'data': {}});

        case 'typing':
          final payload = frame['data'];
          final data = payload is String
              ? jsonDecode(payload) as Map<String, dynamic>
              : const {};
          final from = data['from'] as String?;
          if (from != null) onTyping(from);
      }
    } catch (error) {
      debugPrint('NamedTypingChannel: bad frame — $error');
    }
  }

  void _send(Map<String, dynamic> frame) {
    _socket?.sink.add(jsonEncode(frame));
  }

  void _scheduleReconnect(ValueChanged<String> onTyping) {
    if (_closed || _reconnect != null) return;

    _teardownSocket();
    _reconnect = Timer(const Duration(seconds: 10), () {
      _reconnect = null;
      unawaited(_open(onTyping));
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
