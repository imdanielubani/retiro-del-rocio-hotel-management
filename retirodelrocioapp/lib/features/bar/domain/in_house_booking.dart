import 'package:flutter/foundation.dart';

/// A checked-in guest, for the "Charge to Room" room picker
/// (`GET /bar/bookings/in-house`).
@immutable
class InHouseBooking {
  const InHouseBooking({
    required this.bookingId,
    required this.guestName,
    required this.roomLabel,
  });

  final int bookingId;
  final String guestName;
  final String roomLabel;

  factory InHouseBooking.fromJson(Map<String, dynamic> json) => InHouseBooking(
    bookingId: (json['booking_id'] as num?)?.toInt() ?? 0,
    guestName: json['guest_name'] as String? ?? 'Guest',
    roomLabel: json['room_label'] as String? ?? '—',
  );
}
