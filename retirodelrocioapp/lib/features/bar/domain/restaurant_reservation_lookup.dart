import 'package:flutter/foundation.dart';

/// A table/lounge reservation looked up by its code at the door
/// (`GET /bar/reservations/{code}`) — enough detail for the waiter to
/// confirm it's the right guest before pushing it into a tab.
@immutable
class RestaurantReservationLookup {
  const RestaurantReservationLookup({
    required this.id,
    required this.code,
    required this.areaLabel,
    required this.tableLabel,
    required this.floorLabel,
    required this.guestsLabel,
    required this.reservedDateLabel,
    required this.reservedTimeLabel,
    required this.customerName,
    required this.customerPhone,
    required this.statusLabel,
    required this.canOpenTab,
  });

  final int id;
  final String code;
  final String areaLabel;
  final String? tableLabel;
  final String? floorLabel;
  final String guestsLabel;
  final String? reservedDateLabel;
  final String reservedTimeLabel;
  final String customerName;
  final String? customerPhone;
  final String statusLabel;
  final bool canOpenTab;

  factory RestaurantReservationLookup.fromJson(Map<String, dynamic> json) =>
      RestaurantReservationLookup(
        id: (json['id'] as num?)?.toInt() ?? 0,
        code: json['code'] as String? ?? '',
        areaLabel: json['area_label'] as String? ?? 'Table Reservation',
        tableLabel: json['table_label'] as String?,
        floorLabel: json['floor_label'] as String?,
        guestsLabel: json['guests_label'] as String? ?? '',
        reservedDateLabel: json['reserved_date_label'] as String?,
        reservedTimeLabel: json['reserved_time_label'] as String? ?? '—',
        customerName: json['customer_name'] as String? ?? '',
        customerPhone: json['customer_phone'] as String?,
        statusLabel: json['status_label'] as String? ?? '',
        canOpenTab: json['can_open_tab'] as bool? ?? false,
      );
}
