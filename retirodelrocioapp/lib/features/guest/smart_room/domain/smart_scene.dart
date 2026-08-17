import 'package:flutter/foundation.dart';

/// A one-tap scene for this room (`GET guest/room/scenes`) — e.g. "Welcome",
/// "Relax", "Sleep" — that fires a preset group of device commands server-side
/// when activated.
@immutable
class SmartScene {
  const SmartScene({
    required this.id,
    required this.name,
    required this.slug,
    required this.icon,
  });

  final int id;
  final String name;

  /// `welcome` | `relax` | `sleep` | `checkout` | ... — not globally unique,
  /// only used for a default icon fallback if [icon] is unset.
  final String slug;

  /// Icon key for the Flutter UI, admin-assigned; may be null.
  final String? icon;

  factory SmartScene.fromJson(Map<String, dynamic> json) => SmartScene(
    id: (json['id'] as num?)?.toInt() ?? 0,
    name: json['name'] as String? ?? '',
    slug: json['slug'] as String? ?? '',
    icon: json['icon'] as String?,
  );
}
