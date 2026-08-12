import 'package:flutter/foundation.dart';

/// A booking row for the reception bookings list and a guest's stay history.
@immutable
class ReceptionBookingRow {
  const ReceptionBookingRow({
    required this.id,
    required this.reference,
    required this.guestName,
    required this.roomLabel,
    required this.checkInLabel,
    required this.checkOutLabel,
    required this.nights,
    required this.amountLabel,
    required this.status,
    required this.statusLabel,
    this.isWalkIn = false,
    this.originLabel,
  });

  final int id;
  final String reference;
  final String guestName;
  final String roomLabel;

  /// "Jul 24" — the arrival day.
  final String checkInLabel;

  /// "Jul 26, 2026" — the departure day, carrying the year.
  final String checkOutLabel;
  final int nights;
  final String amountLabel;
  final String status;
  final String statusLabel;

  /// True when the guest booked at the front desk (a walk-in).
  final bool isWalkIn;

  /// "Walk-in" / "Phone" for a desk booking, null for an ordinary online one.
  final String? originLabel;

  factory ReceptionBookingRow.fromJson(Map<String, dynamic> json) =>
      ReceptionBookingRow(
        id: (json['id'] as num?)?.toInt() ?? 0,
        reference: json['reference'] as String? ?? '',
        guestName: json['guest_name'] as String? ?? 'Guest',
        roomLabel: json['room_label'] as String? ?? '',
        checkInLabel: json['check_in_label'] as String? ?? '',
        checkOutLabel: json['check_out_label'] as String? ?? '',
        nights: (json['nights'] as num?)?.toInt() ?? 0,
        amountLabel: json['amount_label'] as String? ?? '',
        status: json['status'] as String? ?? '',
        statusLabel: json['status_label'] as String? ?? '',
        isWalkIn: json['is_walk_in'] as bool? ?? false,
        originLabel:
            (json['origin_label'] as String?)?.trim().isNotEmpty == true
            ? json['origin_label'] as String
            : null,
      );
}
