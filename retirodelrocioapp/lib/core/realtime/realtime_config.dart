import 'package:flutter/foundation.dart';

/// Where the tablet listens for live room updates (Laravel Reverb), taken from
/// `GET /v1/app/config`.
@immutable
class RealtimeConfig {
  const RealtimeConfig({
    required this.key,
    required this.host,
    required this.port,
    required this.scheme,
  });

  final String key;
  final String host;
  final int port;
  final String scheme;

  bool get isUsable => key.isNotEmpty && host.isNotEmpty && port > 0;

  bool get isSecure => scheme == 'https' || scheme == 'wss';

  /// Reverb speaks the Pusher protocol, so the socket lives at `/app/{key}`.
  Uri get socketUri => Uri(
        scheme: isSecure ? 'wss' : 'ws',
        host: host,
        port: port,
        path: '/app/$key',
        queryParameters: const {
          'protocol': '7',
          'client': 'rocio-tablet',
          'version': '1.0',
        },
      );

  static RealtimeConfig? fromJson(Map<String, dynamic>? json) {
    if (json == null) return null;

    final config = RealtimeConfig(
      key: (json['key'] as String?) ?? '',
      host: (json['host'] as String?) ?? '',
      port: (json['port'] as num?)?.toInt() ?? 0,
      scheme: (json['scheme'] as String?) ?? 'http',
    );

    return config.isUsable ? config : null;
  }
}
