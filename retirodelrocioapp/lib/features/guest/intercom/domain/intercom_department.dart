import 'package:flutter/material.dart';

/// One department the guest can voice-call from the Intercom screen
/// (Figma 335:4684 / 335:5021). Mirrors the icon-per-role convention already
/// used for staff chat (`staffChatRoleIcon`) rather than the raw 🛎️ emoji the
/// design mocks up, so the glyph matches what staff/reception see of the
/// same department elsewhere in the app.
@immutable
class IntercomDepartment {
  const IntercomDepartment({
    required this.id,
    required this.label,
    required this.tagline,
    required this.icon,
  });

  final String id;
  final String label;
  final String tagline;
  final IconData icon;
}

abstract final class IntercomDepartments {
  const IntercomDepartments._();

  static const reception = IntercomDepartment(
    id: 'reception',
    label: 'Reception',
    tagline: 'Front desk assistance',
    icon: Icons.support_agent_rounded,
  );

  static const restaurant = IntercomDepartment(
    id: 'restaurant',
    label: 'Restaurant',
    tagline: 'Room service & reservations',
    icon: Icons.restaurant_rounded,
  );

  static const security = IntercomDepartment(
    id: 'security',
    label: 'Security',
    tagline: 'Safety & access control',
    icon: Icons.shield_rounded,
  );

  static const barLounge = IntercomDepartment(
    id: 'bar-lounge',
    label: 'Bar & Lounge',
    tagline: 'Front desk assistance',
    icon: Icons.local_bar_rounded,
  );

  /// Order matches the Figma grid: Reception, Restaurant, Security, then
  /// Bar & Lounge wrapping onto the second row.
  static const List<IntercomDepartment> all = [
    reception,
    restaurant,
    security,
    barLounge,
  ];
}
