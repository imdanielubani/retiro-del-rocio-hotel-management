import 'package:flutter/foundation.dart';

/// How serious an alert is, driving its accent colour on the dashboard.
enum AlertSeverity { high, medium, low }

/// One guest on the reception dashboard's arrivals or departures list.
@immutable
class ReceptionBooking {
  const ReceptionBooking({
    required this.id,
    required this.reference,
    required this.guestName,
    required this.roomLabel,
    required this.dateLabel,
    required this.status,
    this.statusLabel = '',
  });

  final int id;

  /// Booking reference, e.g. "BK-0137".
  final String reference;
  final String guestName;

  /// "Brisa Residence · Room 201".
  final String roomLabel;

  /// Pre-formatted date from the server, e.g. "Jul 24, 2026".
  final String dateLabel;
  final String status;

  /// Human status, e.g. "Checked In" — shown once the desk has processed them.
  final String statusLabel;

  factory ReceptionBooking.fromJson(Map<String, dynamic> json) =>
      ReceptionBooking(
        id: (json['id'] as num?)?.toInt() ?? 0,
        reference: json['reference'] as String? ?? '',
        guestName: json['guest_name'] as String? ?? 'Guest',
        roomLabel: json['room_label'] as String? ?? '',
        dateLabel: json['date_label'] as String? ?? '',
        status: json['status'] as String? ?? '',
        statusLabel: json['status_label'] as String? ?? '',
      );
}

/// One row in the Alerts panel.
@immutable
class ReceptionAlert {
  const ReceptionAlert({
    required this.id,
    required this.title,
    required this.timeLabel,
    required this.severity,
  });

  final int id;
  final String title;
  final String timeLabel;
  final AlertSeverity severity;

  factory ReceptionAlert.fromJson(Map<String, dynamic> json) => ReceptionAlert(
        id: (json['id'] as num?)?.toInt() ?? 0,
        title: json['title'] as String? ?? 'Alert',
        timeLabel: json['time_label'] as String? ?? '',
        severity: switch (json['severity'] as String?) {
          'high' => AlertSeverity.high,
          'low' => AlertSeverity.low,
          _ => AlertSeverity.medium,
        },
      );
}

/// The room-status tally shown as three tiles.
@immutable
class ReceptionRoomStatus {
  const ReceptionRoomStatus({
    this.occupied = 0,
    this.dirty = 0,
    this.maintenance = 0,
  });

  final int occupied;
  final int dirty;
  final int maintenance;

  factory ReceptionRoomStatus.fromJson(Map<String, dynamic> json) =>
      ReceptionRoomStatus(
        occupied: (json['occupied'] as num?)?.toInt() ?? 0,
        dirty: (json['dirty'] as num?)?.toInt() ?? 0,
        maintenance: (json['maintenance'] as num?)?.toInt() ?? 0,
      );
}

/// The whole reception dashboard in one payload (Figma 299:322 / 345:14699).
@immutable
class ReceptionOverview {
  const ReceptionOverview({
    required this.receptionistName,
    required this.receptionistRole,
    required this.arrivalsToday,
    required this.checkInsToday,
    required this.checkOutsToday,
    required this.visitorPassCheckIns,
    required this.arrivals,
    required this.departures,
    required this.alerts,
    required this.roomStatus,
  });

  final String receptionistName;
  final String receptionistRole;

  final int arrivalsToday;
  final int checkInsToday;
  final int checkOutsToday;
  final int visitorPassCheckIns;

  final List<ReceptionBooking> arrivals;
  final List<ReceptionBooking> departures;
  final List<ReceptionAlert> alerts;
  final ReceptionRoomStatus roomStatus;

  static const empty = ReceptionOverview(
    receptionistName: 'Reception',
    receptionistRole: 'Reception',
    arrivalsToday: 0,
    checkInsToday: 0,
    checkOutsToday: 0,
    visitorPassCheckIns: 0,
    arrivals: [],
    departures: [],
    alerts: [],
    roomStatus: ReceptionRoomStatus(),
  );

  factory ReceptionOverview.fromJson(Map<String, dynamic> json) {
    final receptionist =
        (json['receptionist'] as Map?)?.cast<String, dynamic>() ?? const {};
    final stats = (json['stats'] as Map?)?.cast<String, dynamic>() ?? const {};

    List<T> parse<T>(String key, T Function(Map<String, dynamic>) build) =>
        ((json[key] as List?) ?? const [])
            .whereType<Map>()
            .map((e) => build(e.cast<String, dynamic>()))
            .toList();

    return ReceptionOverview(
      receptionistName: receptionist['name'] as String? ?? 'Reception',
      receptionistRole: receptionist['role'] as String? ?? 'Reception',
      arrivalsToday: (stats['arrivals_today'] as num?)?.toInt() ?? 0,
      checkInsToday: (stats['check_ins_today'] as num?)?.toInt() ?? 0,
      checkOutsToday: (stats['check_outs_today'] as num?)?.toInt() ?? 0,
      visitorPassCheckIns: (stats['visitor_pass_check_ins'] as num?)?.toInt() ?? 0,
      arrivals: parse('arrivals', ReceptionBooking.fromJson),
      departures: parse('departures', ReceptionBooking.fromJson),
      alerts: parse('alerts', ReceptionAlert.fromJson),
      roomStatus: ReceptionRoomStatus.fromJson(
        (json['room_status'] as Map?)?.cast<String, dynamic>() ?? const {},
      ),
    );
  }
}
