import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/features/security/domain/security_incident.dart';

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
    this.isWalkIn = false,
    this.originLabel,
    this.isDueToday = false,
    this.isOverdue = false,
    this.overdueLabel,
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

  /// True when the guest booked at the front desk (a walk-in).
  final bool isWalkIn;

  /// "Walk-in" / "Phone" for a desk booking, null for an ordinary online one.
  final String? originLabel;

  /// True for a still-checked-in guest whose checkout is today — the
  /// departures list holds every checked-in guest, not just today's, so this
  /// is what lets the tablet visually separate "act now" from "coming up".
  final bool isDueToday;

  /// True for a still-checked-in guest whose checkout date has passed — a
  /// departure the desk must act on, not just one due later today.
  final bool isOverdue;

  /// "Overdue by 2 days", present only when [isOverdue] is true.
  final String? overdueLabel;

  factory ReceptionBooking.fromJson(
    Map<String, dynamic> json,
  ) => ReceptionBooking(
    id: (json['id'] as num?)?.toInt() ?? 0,
    reference: json['reference'] as String? ?? '',
    guestName: json['guest_name'] as String? ?? 'Guest',
    roomLabel: json['room_label'] as String? ?? '',
    dateLabel: json['date_label'] as String? ?? '',
    status: json['status'] as String? ?? '',
    statusLabel: json['status_label'] as String? ?? '',
    isWalkIn: json['is_walk_in'] as bool? ?? false,
    originLabel: (json['origin_label'] as String?)?.trim().isNotEmpty == true
        ? json['origin_label'] as String
        : null,
    isDueToday: json['is_due_today'] as bool? ?? false,
    isOverdue: json['is_overdue'] as bool? ?? false,
    overdueLabel: (json['overdue_label'] as String?)?.trim().isNotEmpty == true
        ? json['overdue_label'] as String
        : null,
  );
}

/// One row in the Alerts panel.
@immutable
class ReceptionAlert {
  const ReceptionAlert({
    required this.id,
    required this.type,
    required this.title,
    required this.timeLabel,
    required this.severity,
  });

  final int id;

  /// 'sos', 'overdue_departure', 'housekeeping_request' or
  /// 'maintenance_request' — `id` alone can collide across types since each
  /// is a different table's primary key, so dedup keys must use both.
  final String type;
  final String title;
  final String timeLabel;
  final AlertSeverity severity;

  factory ReceptionAlert.fromJson(Map<String, dynamic> json) => ReceptionAlert(
    id: (json['id'] as num?)?.toInt() ?? 0,
    type: json['type'] as String? ?? 'sos',
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
    required this.departuresToday,
    required this.visitorPassCheckIns,
    this.overdueDepartures = 0,
    required this.arrivals,
    required this.departures,
    required this.alerts,
    required this.incidents,
    required this.roomStatus,
  });

  final String receptionistName;
  final String receptionistRole;

  final int arrivalsToday;
  final int checkInsToday;

  /// Everyone scheduled to leave today, whether or not they've actually
  /// checked out yet — arrivalsToday's departure-side counterpart, not the
  /// count of completed checkout actions.
  final int departuresToday;
  final int visitorPassCheckIns;

  /// Still checked in with a checkout date already in the past — a departure
  /// the desk must catch up on, not just one due later today.
  final int overdueDepartures;

  final List<ReceptionBooking> arrivals;
  final List<ReceptionBooking> departures;
  final List<ReceptionAlert> alerts;

  /// The open SOS incidents in full incident shape — the same hotel-wide alerts
  /// the security dashboard sees. Drives the priority SOS overlay on the desk.
  final List<SecurityIncident> incidents;

  final ReceptionRoomStatus roomStatus;

  static const empty = ReceptionOverview(
    receptionistName: 'Reception',
    receptionistRole: 'Reception',
    arrivalsToday: 0,
    checkInsToday: 0,
    departuresToday: 0,
    visitorPassCheckIns: 0,
    overdueDepartures: 0,
    arrivals: [],
    departures: [],
    alerts: [],
    incidents: [],
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
      departuresToday: (stats['departures_today'] as num?)?.toInt() ?? 0,
      visitorPassCheckIns:
          (stats['visitor_pass_check_ins'] as num?)?.toInt() ?? 0,
      overdueDepartures: (stats['overdue_departures'] as num?)?.toInt() ?? 0,
      arrivals: parse('arrivals', ReceptionBooking.fromJson),
      departures: parse('departures', ReceptionBooking.fromJson),
      alerts: parse('alerts', ReceptionAlert.fromJson),
      incidents: parse('incidents', SecurityIncident.fromJson),
      roomStatus: ReceptionRoomStatus.fromJson(
        (json['room_status'] as Map?)?.cast<String, dynamic>() ?? const {},
      ),
    );
  }
}
