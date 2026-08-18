import 'package:flutter/foundation.dart';

/// Describes one capability's control shape on a [SmartDevice] — parsed from
/// the normalized `capabilities` map the backend already translated from
/// Tuya's raw `functions` response (see
/// `retiro-del-rocio/docs/architecture/03-tuya-architecture.md`). The
/// vendor's own DP `code` never reaches the Flutter side; only [key]
/// (`power`, `brightness`, `mode`, ...), [type] and the range/enum bounds do.
@immutable
class SmartCapabilitySpec {
  const SmartCapabilitySpec({
    required this.key,
    required this.type,
    this.min,
    this.max,
    this.values,
  });

  /// Our normalized vocabulary key, e.g. `power`, `brightness`, `mode`.
  final String key;

  /// `bool` | `int` | `enum` — which control shape to render. An unknown
  /// type renders nothing rather than guessing.
  final String type;

  /// Set for [type] `int` — the slider bounds.
  final int? min;
  final int? max;

  /// Set for [type] `enum` — the segmented control's options.
  final List<String>? values;

  bool get isBool => type == 'bool';
  bool get isInt => type == 'int';
  bool get isEnum => type == 'enum';

  factory SmartCapabilitySpec.fromJson(String key, Map<String, dynamic> json) =>
      SmartCapabilitySpec(
        key: key,
        type: json['type'] as String? ?? 'bool',
        min: (json['min'] as num?)?.toInt(),
        max: (json['max'] as num?)?.toInt(),
        values: (json['values'] as List?)?.map((v) => v.toString()).toList(),
      );
}

/// One Tuya-backed in-room device (`GET guest/room/devices`), normalized so
/// the tablet never sees a raw Tuya DP code or credential — only the
/// vocabulary keys in [capabilities] and their last-known values in [state].
@immutable
class SmartDevice {
  const SmartDevice({
    required this.id,
    required this.name,
    required this.type,
    required this.status,
    required this.capabilities,
    required this.state,
  });

  final int id;
  final String name;

  /// `light` | `ac` | `curtain` | `tv` — open string, matches the backend;
  /// never switched on for control-shape decisions, only for grouping
  /// devices onto the right screen.
  final String type;

  /// `online` | `offline` | `unknown`.
  final String status;

  /// Normalized capability key -> spec. A device simply omits a key it
  /// doesn't support.
  final Map<String, SmartCapabilitySpec> capabilities;

  /// Last known value per capability key, cached for instant paint before a
  /// live fetch resolves.
  final Map<String, dynamic> state;

  bool get isOnline => status == 'online';

  /// The last-known value for [capabilityKey], or null if unset/unsupported.
  dynamic valueOf(String capabilityKey) => state[capabilityKey];

  factory SmartDevice.fromJson(Map<String, dynamic> json) {
    final capsJson =
        (json['capabilities'] as Map?)?.cast<String, dynamic>() ?? const {};
    final stateJson =
        (json['state'] as Map?)?.cast<String, dynamic>() ?? const {};
    return SmartDevice(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: json['name'] as String? ?? '',
      type: json['type'] as String? ?? '',
      status: json['status'] as String? ?? 'unknown',
      capabilities: capsJson.map(
        (key, value) => MapEntry(
          key,
          SmartCapabilitySpec.fromJson(
            key,
            (value as Map).cast<String, dynamic>(),
          ),
        ),
      ),
      state: stateJson,
    );
  }
}
