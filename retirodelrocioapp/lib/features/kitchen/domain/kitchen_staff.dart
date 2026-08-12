import 'package:flutter/foundation.dart';

/// An active kitchen staff member — the Assign to Station picker
/// (`GET /kitchen/chefs`).
@immutable
class KitchenStaff {
  const KitchenStaff({required this.id, required this.name});

  final int id;
  final String name;

  factory KitchenStaff.fromJson(Map<String, dynamic> json) => KitchenStaff(
    id: (json['id'] as num?)?.toInt() ?? 0,
    name: json['name'] as String? ?? 'Kitchen Staff',
  );
}
