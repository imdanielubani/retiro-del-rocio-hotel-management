import 'package:flutter/foundation.dart';

/// An active bar staff member — the Assign Bartender picker
/// (`GET /bar/bartenders`).
@immutable
class Bartender {
  const Bartender({required this.id, required this.name});

  final int id;
  final String name;

  factory Bartender.fromJson(Map<String, dynamic> json) => Bartender(
    id: (json['id'] as num?)?.toInt() ?? 0,
    name: json['name'] as String? ?? 'Bar Staff',
  );
}
