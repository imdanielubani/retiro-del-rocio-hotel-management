import 'package:flutter/foundation.dart';

/// A guest ask — towels, amenities, Do Not Disturb, or a make-up-room visit —
/// for housekeeping to clear (`GET /housekeeping/requests`).
@immutable
class HousekeepingGuestRequest {
  const HousekeepingGuestRequest({
    required this.id,
    required this.roomUnitId,
    this.roomNumber,
    this.roomName,
    this.guestName,
    required this.type,
    required this.typeLabel,
    this.notes,
    required this.status,
    required this.isPending,
    this.createdLabel,
  });

  final int id;
  final int roomUnitId;
  final String? roomNumber;
  final String? roomName;

  /// The guest the request was raised for, when the room has an active
  /// booking — null for a request logged against an empty room.
  final String? guestName;
  final String type;
  final String typeLabel;
  final String? notes;
  final String status;
  final bool isPending;
  final String? createdLabel;

  factory HousekeepingGuestRequest.fromJson(Map<String, dynamic> json) => HousekeepingGuestRequest(
    id: (json['id'] as num?)?.toInt() ?? 0,
    roomUnitId: (json['room_unit_id'] as num?)?.toInt() ?? 0,
    roomNumber: json['room_number'] as String?,
    roomName: json['room_name'] as String?,
    guestName: json['guest_name'] as String?,
    type: json['type'] as String? ?? 'other',
    typeLabel: json['type_label'] as String? ?? 'Other',
    notes: json['notes'] as String?,
    status: json['status'] as String? ?? 'pending',
    isPending: json['is_pending'] as bool? ?? true,
    createdLabel: json['created_label'] as String?,
  );
}
