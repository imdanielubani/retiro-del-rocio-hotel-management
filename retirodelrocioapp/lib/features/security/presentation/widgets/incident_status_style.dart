import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/features/security/domain/security_incident.dart';

/// The colour, label and SOS glyph the design assigns to each incident status
/// (Figma 225:9610 red / amber / green), kept in one place so the log rows and
/// the detail panel never drift apart.
class IncidentStatusStyle {
  const IncidentStatusStyle._(this.color, this.label, this.sosAsset);

  final Color color;
  final String label;
  final String sosAsset;

  static const _red = Color(0xFFFF0000);
  static const _amber = Color(0xFFFFB11B);
  static const _green = Color(0xFF00FF00);
  static const _grey = Color(0xFF9CA3AF);

  factory IncidentStatusStyle.of(SecurityIncident incident) {
    return switch (incident.status) {
      IncidentStatus.active => const IncidentStatusStyle._(
        _red,
        'Unacknowledged',
        'assets/icons/sos red.png',
      ),
      IncidentStatus.acknowledged => const IncidentStatusStyle._(
        _amber,
        'Acknowledged',
        'assets/icons/sos yellow.png',
      ),
      IncidentStatus.resolved => const IncidentStatusStyle._(
        _green,
        'Resolved',
        'assets/icons/sos green.png',
      ),
      IncidentStatus.cancelled => const IncidentStatusStyle._(
        _grey,
        'Cancelled',
        'assets/icons/sos green.png',
      ),
    };
  }
}
