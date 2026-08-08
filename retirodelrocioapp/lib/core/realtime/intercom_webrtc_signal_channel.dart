import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/realtime/realtime_config.dart';
import 'package:web_socket_channel/web_socket_channel.dart';

/// Listens for a WebRTC signaling message — an SDP offer/answer, or an ICE
/// candidate — on one Intercom call's own channel (`intercom-signal.
/// {callId}`). The generic form of every other named channel in this app
/// ({@see StaffIntercomChannel}, {@see NamedTypingChannel}), except this one
/// carries a real payload rather than being a pure "go re-fetch" ping — the
/// SDP/candidate data only ever exists in flight, there's nothing to
/// re-fetch it from.
///
/// Same handshake discipline as every other realtime channel here: wait for
/// the socket, wait for `pusher:connection_established`, only then
/// subscribe; answer `pusher:ping` so Reverb doesn't hang up.
class IntercomWebrtcSignalChannel {
  IntercomWebrtcSignalChannel({required this.config, required this.callId});

  final RealtimeConfig config;
  final int callId;

  WebSocketChannel? _socket;
  StreamSubscription<dynamic>? _subscription;
  Timer? _reconnect;
  bool _closed = false;

  String get _channel => 'intercom-signal.$callId';

  /// Calls [onSignal] with (from, type, data) whenever a signal arrives —
  /// `from` is `'caller'`/`'callee'`, `type` is `'offer'`/`'answer'`/
  /// `'ice-candidate'`, and `data` is the opaque SDP/candidate payload.
  void connect({
    required void Function(String from, String type, Map<String, dynamic> data)
    onSignal,
  }) {
    _closed = false;
    unawaited(_open(onSignal));
  }

  Future<void> _open(
    void Function(String from, String type, Map<String, dynamic> data) onSignal,
  ) async {
    if (_closed) return;

    try {
      final socket = WebSocketChannel.connect(config.socketUri);
      _socket = socket;

      _subscription = socket.stream.listen(
        (message) => _onMessage(message, onSignal),
        onError: (Object error) {
          debugPrint('IntercomWebrtcSignalChannel: socket error — $error');
          _scheduleReconnect(onSignal);
        },
        onDone: () {
          debugPrint(
            'IntercomWebrtcSignalChannel: socket closed — will retry.',
          );
          _scheduleReconnect(onSignal);
        },
        cancelOnError: true,
      );

      await socket.ready;
      debugPrint(
        'IntercomWebrtcSignalChannel: connected to ${config.socketUri.host} — awaiting handshake.',
      );
    } catch (error) {
      debugPrint('IntercomWebrtcSignalChannel: connect failed — $error');
      _scheduleReconnect(onSignal);
    }
  }

  void _onMessage(
    dynamic message,
    void Function(String from, String type, Map<String, dynamic> data) onSignal,
  ) {
    if (message is! String) return;

    try {
      final frame = jsonDecode(message) as Map<String, dynamic>;
      final event = frame['event'] as String?;

      switch (event) {
        case 'pusher:connection_established':
          _send({
            'event': 'pusher:subscribe',
            'data': {'channel': _channel},
          });

        case 'pusher_internal:subscription_succeeded':
          debugPrint(
            'IntercomWebrtcSignalChannel: subscribed to $_channel — updates are live.',
          );

        case 'pusher:ping':
          _send({'event': 'pusher:pong', 'data': {}});

        case 'signal':
          final payload = frame['data'];
          final body = payload is String
              ? jsonDecode(payload) as Map<String, dynamic>
              : const <String, dynamic>{};
          final from = body['from'] as String?;
          final type = body['type'] as String?;
          final data = body['data'];
          if (from != null && type != null && data is Map) {
            onSignal(from, type, data.cast<String, dynamic>());
          }
      }
    } catch (error) {
      debugPrint('IntercomWebrtcSignalChannel: bad frame — $error');
    }
  }

  void _send(Map<String, dynamic> frame) {
    _socket?.sink.add(jsonEncode(frame));
  }

  void _scheduleReconnect(
    void Function(String from, String type, Map<String, dynamic> data) onSignal,
  ) {
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
