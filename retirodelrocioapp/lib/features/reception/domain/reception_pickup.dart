import 'package:flutter/foundation.dart';

/// A driver on the assignable pickup roster.
@immutable
class PickupDriver {
  const PickupDriver({
    required this.id,
    required this.name,
    this.phone,
    this.vehicleDetails,
    this.statusLabel,
  });

  final int id;
  final String name;
  final String? phone;
  final String? vehicleDetails;
  final String? statusLabel;

  factory PickupDriver.fromJson(Map<String, dynamic> json) => PickupDriver(
    id: (json['id'] as num?)?.toInt() ?? 0,
    name: json['name'] as String? ?? 'Driver',
    phone: json['phone'] as String?,
    vehicleDetails: json['vehicle_details'] as String?,
    statusLabel: json['status_label'] as String?,
  );
}

/// A guest's vehicle pickup as shown on the reception Vehicle Pickup module.
@immutable
class PickupBooking {
  const PickupBooking({
    required this.id,
    required this.reference,
    required this.guestName,
    this.guestPhone,
    required this.vehicle,
    required this.passengersLabel,
    required this.from,
    required this.to,
    required this.numberLabel,
    this.flightNumber,
    this.pickupDate,
    this.pickupTime,
    required this.amountLabel,
    required this.pickupStatus,
    required this.pickupStatusLabel,
    this.driver,
  });

  final int id;
  final String reference;
  final String guestName;
  final String? guestPhone;
  final String vehicle;
  final String passengersLabel;
  final String from;
  final String to;
  final String numberLabel;
  final String? flightNumber;
  final String? pickupDate;
  final String? pickupTime;
  final String amountLabel;

  /// unassigned | assigned | completed
  final String pickupStatus;
  final String pickupStatusLabel;
  final PickupDriver? driver;

  bool get isUnassigned => pickupStatus == 'unassigned';
  bool get isAssigned => pickupStatus == 'assigned';
  bool get isCompleted => pickupStatus == 'completed';

  factory PickupBooking.fromJson(Map<String, dynamic> json) => PickupBooking(
    id: (json['id'] as num?)?.toInt() ?? 0,
    reference: json['reference'] as String? ?? '',
    guestName: json['guest_name'] as String? ?? 'Guest',
    guestPhone: json['guest_phone'] as String?,
    vehicle: json['vehicle'] as String? ?? '',
    passengersLabel: json['passengers_label'] as String? ?? '',
    from: json['from'] as String? ?? '',
    to: json['to'] as String? ?? '',
    numberLabel: json['number_label'] as String? ?? 'Flight Number',
    flightNumber: json['flight_number'] as String?,
    pickupDate: json['pickup_date'] as String?,
    pickupTime: json['pickup_time'] as String?,
    amountLabel: json['amount_label'] as String? ?? '',
    pickupStatus: json['pickup_status'] as String? ?? 'unassigned',
    pickupStatusLabel:
        json['pickup_status_label'] as String? ?? 'Awaiting Driver',
    driver: json['driver'] is Map
        ? PickupDriver.fromJson((json['driver'] as Map).cast<String, dynamic>())
        : null,
  );
}
