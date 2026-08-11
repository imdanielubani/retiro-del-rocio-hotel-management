import 'package:flutter/foundation.dart';

/// One visitor pass on the reception tablet's Visitor Pass screen
/// (`GET /reception/visitors`) — read-only, covering every visitor invited
/// or arrived, not just today's.
@immutable
class ReceptionVisitor {
  const ReceptionVisitor({
    required this.id,
    required this.reference,
    required this.visitorName,
    required this.initials,
    required this.hostName,
    required this.roomNumber,
    required this.suiteName,
    required this.email,
    required this.phone,
    required this.status,
    required this.statusLabel,
    required this.invitedLabel,
    required this.arrivalLabel,
    required this.isInside,
  });

  final int id;
  final String reference;
  final String visitorName;
  final String initials;
  final String? hostName;
  final String? roomNumber;
  final String? suiteName;
  final String? email;
  final String? phone;
  final String status;
  final String statusLabel;
  final String? invitedLabel;
  final String? arrivalLabel;
  final bool isInside;

  factory ReceptionVisitor.fromJson(Map<String, dynamic> json) => ReceptionVisitor(
    id: (json['id'] as num?)?.toInt() ?? 0,
    reference: json['reference'] as String? ?? '',
    visitorName: json['visitor_name'] as String? ?? 'Visitor',
    initials: json['initials'] as String? ?? '?',
    hostName: json['host_name'] as String?,
    roomNumber: json['room_number'] as String?,
    suiteName: json['suite_name'] as String?,
    email: json['email'] as String?,
    phone: json['phone'] as String?,
    status: json['status'] as String? ?? 'pending',
    statusLabel: json['status_label'] as String? ?? 'Pending',
    invitedLabel: json['invited_label'] as String?,
    arrivalLabel: json['arrival_label'] as String?,
    isInside: json['is_inside'] as bool? ?? false,
  );
}

/// The Visitor Pass list plus its headline counters.
@immutable
class ReceptionVisitorsOverview {
  const ReceptionVisitorsOverview({
    required this.expected,
    required this.inside,
    required this.today,
    required this.visitors,
  });

  final int expected;
  final int inside;
  final int today;
  final List<ReceptionVisitor> visitors;

  static const empty = ReceptionVisitorsOverview(
    expected: 0,
    inside: 0,
    today: 0,
    visitors: [],
  );

  factory ReceptionVisitorsOverview.fromJson(Map<String, dynamic> json) {
    final summary = (json['summary'] as Map?)?.cast<String, dynamic>() ?? const {};

    return ReceptionVisitorsOverview(
      expected: (summary['expected'] as num?)?.toInt() ?? 0,
      inside: (summary['inside'] as num?)?.toInt() ?? 0,
      today: (summary['today'] as num?)?.toInt() ?? 0,
      visitors: ((json['visitors'] as List?) ?? const [])
          .map((v) => ReceptionVisitor.fromJson((v as Map).cast()))
          .toList(),
    );
  }
}
