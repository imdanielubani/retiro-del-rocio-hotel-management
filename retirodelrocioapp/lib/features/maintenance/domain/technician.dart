import 'package:flutter/foundation.dart';

/// An active maintenance technician — the Assign Technician picker
/// (`GET /maintenance/technicians`).
@immutable
class Technician {
  const Technician({required this.id, required this.name});

  final int id;
  final String name;

  factory Technician.fromJson(Map<String, dynamic> json) => Technician(
    id: (json['id'] as num?)?.toInt() ?? 0,
    name: json['name'] as String? ?? 'Technician',
  );
}
