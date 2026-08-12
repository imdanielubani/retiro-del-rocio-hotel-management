import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/realtime/realtime_config.dart';
import 'package:web_socket_channel/web_socket_channel.dart';

/// Listens for a `message.created` signal on a station's own Staff Chat
/// inbox channel (`staff-chat-inbox.{role}`) — the generic form of the
/// `typing` handling in {@see NamedTypingChannel}, for the "a new message
/// landed for me" signal instead. Scoped to the listener's own role alone
/// (not a per-pair channel like typing), so it's live no matter which
/// channel, if any, is currently open.
///
/// Same handshake discipline as every other realtime channel in this app:
/// wait for the socket to be ready, wait for `pusher:connection_established`,
/// only then subscribe; answer `pusher:ping` so Reverb doesn't hang up.
class StaffChatInboxChannel {
  StaffChatInboxChannel({required this.config, required this.channel});

  final RealtimeConfig config;
  final String channel;

  WebSocketChannel? _socket;
  StreamSubscription<dynamic>? _subscription;
  Timer? _reconnect;
  bool _closed = false;

  /// Calls [onMessage] whenever a new message lands for this staffer,
  /// carrying the sender's own user ID as a string.
  void connect({required ValueChanged<String> onMessage}) {
    _closed = false;
    unawaited(_open(onMessage));
  }

  Future<void> _open(ValueChanged<String> onMessage) async {
    if (_closed) return;

    try {
      final socket = WebSocketChannel.connect(config.socketUri);
      _socket = socket;

      _subscription = socket.stream.listen(
        (message) => _onMessage(message, onMessage),
        onError: (Object error) {
          debugPrint('StaffChatInboxChannel: socket error — $error');
          _scheduleReconnect(onMessage);
        },
        onDone: () {
          debugPrint('StaffChatInboxChannel: socket closed — will retry.');
          _scheduleReconnect(onMessage);
        },
        cancelOnError: true,
      );

      await socket.ready;
      debugPrint(
        'StaffChatInboxChannel: connected to ${config.socketUri.host} — awaiting handshake.',
      );
    } catch (error) {
      debugPrint('StaffChatInboxChannel: connect failed — $error');
      _scheduleReconnect(onMessage);
    }
  }

  void _onMessage(dynamic message, ValueChanged<String> onMessage) {
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
            'StaffChatInboxChannel: subscribed to $channel — updates are live.',
          );

        case 'pusher:ping':
          _send({'event': 'pusher:pong', 'data': {}});

        case 'message.created':
          final payload = frame['data'];
          final data = payload is String
              ? jsonDecode(payload) as Map<String, dynamic>
              : const {};
          final from = data['from_user_id'];
          if (from != null) onMessage(from.toString());
      }
    } catch (error) {
      debugPrint('StaffChatInboxChannel: bad frame — $error');
    }
  }

  void _send(Map<String, dynamic> frame) {
    _socket?.sink.add(jsonEncode(frame));
  }

  void _scheduleReconnect(ValueChanged<String> onMessage) {
    if (_closed || _reconnect != null) return;

    _teardownSocket();
    _reconnect = Timer(const Duration(seconds: 10), () {
      _reconnect = null;
      unawaited(_open(onMessage));
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
