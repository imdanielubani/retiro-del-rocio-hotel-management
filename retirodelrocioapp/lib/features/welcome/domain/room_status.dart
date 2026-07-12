import 'package:flutter/foundation.dart';

enum Occupancy { available, occupied, maintenance, unassigned }

/// The checked-in guest's booking details.
@immutable
class GuestInfo {
  const GuestInfo({
    required this.name,
    this.reference,
    this.nights,
    this.checkIn,
    this.checkOut,
  });

  final String name;

  /// Booking code, e.g. "RDR-000121".
  final String? reference;

  /// Nights as recorded on the booking (authoritative over the date maths).
  final int? nights;

  /// Arrival / departure, carrying the hotel's policy times (3:00 PM / 11:00 AM).
  final DateTime? checkIn;
  final DateTime? checkOut;

  factory GuestInfo.fromJson(Map<String, dynamic> json) => GuestInfo(
        name: (json['name'] as String?)?.trim().isNotEmpty == true
            ? json['name'] as String
            : 'Guest',
        reference: json['reference'] as String?,
        nights: (json['nights'] as num?)?.toInt(),
        checkIn: DateTime.tryParse(json['check_in'] as String? ?? ''),
        checkOut: DateTime.tryParse(json['check_out'] as String? ?? ''),
      );
}

/// Live occupancy of the tablet's room (from `GET /v1/tablets/room-status`).
@immutable
class RoomStatus {
  const RoomStatus({
    required this.occupancy,
    this.suiteName,
    this.roomNumber,
    this.guest,
  });

  final Occupancy occupancy;
  final String? suiteName;
  final String? roomNumber;
  final GuestInfo? guest;

  bool get isOccupied => occupancy == Occupancy.occupied;
  bool get hasGuest => guest != null;

  factory RoomStatus.fromJson(Map<String, dynamic> json) => RoomStatus(
        occupancy: switch (json['occupancy'] as String?) {
          'occupied' => Occupancy.occupied,
          'maintenance' => Occupancy.maintenance,
          'unassigned' => Occupancy.unassigned,
          _ => Occupancy.available,
        },
        suiteName: json['suite_name'] as String?,
        roomNumber: json['room_number'] as String?,
        guest: json['guest'] is Map
            ? GuestInfo.fromJson((json['guest'] as Map).cast<String, dynamic>())
            : null,
      );
}
