import 'package:flutter/foundation.dart';

/// One room as the housekeeping tablet sees it — occupancy plus cleanliness,
/// which are tracked separately: a room can be occupied and dirty, or
/// available and dirty, at the same time.
@immutable
class HousekeepingRoom {
  const HousekeepingRoom({
    required this.id,
    required this.number,
    this.roomName,
    required this.occupancy,
    required this.occupancyLabel,
    required this.housekeepingStatus,
    required this.housekeepingStatusLabel,
    this.guestName,
    this.checkoutToday = false,
    this.updatedLabel,
  });

  final int id;
  final String number;
  final String? roomName;
  final String occupancy;
  final String occupancyLabel;
  final String housekeepingStatus;
  final String housekeepingStatusLabel;
  final String? guestName;

  /// True when the guest currently in this room checks out today — a room
  /// about to turn over is more urgent than a mid-stay tidy.
  final bool checkoutToday;
  final String? updatedLabel;

  bool get needsAttention => housekeepingStatus == 'dirty' || housekeepingStatus == 'out_of_order';

  factory HousekeepingRoom.fromJson(Map<String, dynamic> json) => HousekeepingRoom(
    id: (json['id'] as num?)?.toInt() ?? 0,
    number: json['number'] as String? ?? '—',
    roomName: json['room_name'] as String?,
    occupancy: json['occupancy'] as String? ?? 'available',
    occupancyLabel: json['occupancy_label'] as String? ?? 'Available',
    housekeepingStatus: json['housekeeping_status'] as String? ?? 'clean',
    housekeepingStatusLabel: json['housekeeping_status_label'] as String? ?? 'Clean',
    guestName: json['guest_name'] as String?,
    checkoutToday: json['checkout_today'] as bool? ?? false,
    updatedLabel: json['updated_label'] as String?,
  );
}
