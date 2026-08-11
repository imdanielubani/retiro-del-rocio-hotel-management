import 'package:flutter/foundation.dart';

/// One selectable room unit in the check-in "Room Assignment" step.
@immutable
class RoomOption {
  const RoomOption({
    required this.id,
    required this.number,
    required this.roomName,
    this.priceLabel,
    this.hasTablet = false,
  });

  final int id;
  final String number;
  final String roomName;

  /// "NGN 42,000/night", or null when the room has no price.
  final String? priceLabel;

  /// Whether the unit has an in-room tablet — the guest's details appear on it
  /// the moment they are checked in. Mirrors the admin dashboard's tablet mark.
  final bool hasTablet;

  factory RoomOption.fromJson(Map<String, dynamic> json) => RoomOption(
        id: (json['id'] as num?)?.toInt() ?? 0,
        number: json['number'] as String? ?? '',
        roomName: json['room_name'] as String? ?? '',
        priceLabel: json['price_label'] as String?,
        hasTablet: json['has_tablet'] as bool? ?? false,
      );
}

/// The room-assignment options: the unit the booking holds plus same-type
/// alternatives the desk can move the guest to.
@immutable
class RoomOptions {
  const RoomOptions({this.assigned, this.available = const []});

  final RoomOption? assigned;
  final List<RoomOption> available;

  factory RoomOptions.fromJson(Map<String, dynamic> json) => RoomOptions(
        assigned: json['assigned'] is Map
            ? RoomOption.fromJson((json['assigned'] as Map).cast<String, dynamic>())
            : null,
        available: ((json['available'] as List?) ?? const [])
            .whereType<Map>()
            .map((e) => RoomOption.fromJson(e.cast<String, dynamic>()))
            .toList(),
      );
}

/// The "Check-In Complete" summary returned when a guest is checked in.
@immutable
class CheckinConfirmation {
  const CheckinConfirmation({
    required this.confirmation,
    required this.guestName,
    this.roomNumber,
    this.roomLabel,
    this.checkInTime,
    this.documentLabel,
    this.documentNumber,
    this.documentUrl,
  });

  final String confirmation;
  final String guestName;
  final String? roomNumber;
  final String? roomLabel;
  final String? checkInTime;
  final String? documentLabel;
  final String? documentNumber;
  final String? documentUrl;

  factory CheckinConfirmation.fromJson(Map<String, dynamic> json) =>
      CheckinConfirmation(
        confirmation: json['confirmation'] as String? ?? '',
        guestName: json['guest_name'] as String? ?? 'Guest',
        roomNumber: json['room_number'] as String?,
        roomLabel: json['room_label'] as String?,
        checkInTime: json['check_in_time'] as String?,
        documentLabel: json['document_label'] as String?,
        documentNumber: json['document_number'] as String?,
        documentUrl: json['document_url'] as String?,
      );
}
