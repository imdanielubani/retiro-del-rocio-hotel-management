import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/features/security/domain/security_incident.dart';
import 'package:retirodelrocioapp/features/security/domain/security_visitor.dart';

/// Everything the security dashboard shows, fetched in one call from
/// `GET /security/overview` (Figma 204:3089 / 257:1133).
@immutable
class SecurityOverview {
  const SecurityOverview({
    required this.officerName,
    required this.officerRole,
    required this.activeIncidents,
    required this.visitorsToday,
    required this.verifiedPasses,
    required this.incidents,
    required this.visitors,
    required this.passRequests,
  });

  final String officerName;
  final String officerRole;

  // Headline counters.
  final int activeIncidents;
  final int visitorsToday;
  final int verifiedPasses;

  final List<SecurityIncident> incidents;
  final List<SecurityVisitor> visitors;
  final List<VisitorPassRequest> passRequests;

  /// The single most urgent open incident, if any — the banner at the top of
  /// Open Incidents. An unacknowledged (active) one always wins.
  SecurityIncident? get topIncident {
    if (incidents.isEmpty) return null;
    return incidents.firstWhere(
      (i) => i.isActive,
      orElse: () => incidents.first,
    );
  }

  bool get hasOpenIncident => incidents.isNotEmpty;

  static const empty = SecurityOverview(
    officerName: 'Security',
    officerRole: 'Security Office',
    activeIncidents: 0,
    visitorsToday: 0,
    verifiedPasses: 0,
    incidents: [],
    visitors: [],
    passRequests: [],
  );

  factory SecurityOverview.fromJson(Map<String, dynamic> json) {
    final officer = (json['officer'] as Map?)?.cast<String, dynamic>() ?? const {};
    final stats = (json['stats'] as Map?)?.cast<String, dynamic>() ?? const {};

    List<T> parse<T>(String key, T Function(Map<String, dynamic>) build) =>
        ((json[key] as List?) ?? const [])
            .whereType<Map>()
            .map((e) => build(e.cast<String, dynamic>()))
            .toList();

    return SecurityOverview(
      officerName: officer['name'] as String? ?? 'Security',
      officerRole: officer['role'] as String? ?? 'Security Office',
      activeIncidents: (stats['active_incidents'] as num?)?.toInt() ?? 0,
      visitorsToday: (stats['visitors_today'] as num?)?.toInt() ?? 0,
      verifiedPasses: (stats['verified_passes'] as num?)?.toInt() ?? 0,
      incidents: parse('incidents', SecurityIncident.fromJson),
      visitors: parse('visitors', SecurityVisitor.fromJson),
      passRequests: parse('pass_requests', VisitorPassRequest.fromJson),
    );
  }
}
