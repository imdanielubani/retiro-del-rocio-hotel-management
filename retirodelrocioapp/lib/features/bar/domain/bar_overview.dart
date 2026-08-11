import 'package:flutter/foundation.dart';

/// The Bar Tablet dashboard headline stats (`GET /bar/overview`).
@immutable
class BarOverview {
  const BarOverview({
    required this.newCount,
    required this.preparingCount,
    required this.servedToday,
    required this.openTabs,
    required this.vipTabs,
  });

  final int newCount;
  final int preparingCount;
  final int servedToday;
  final int openTabs;
  final int vipTabs;

  static const empty = BarOverview(
    newCount: 0,
    preparingCount: 0,
    servedToday: 0,
    openTabs: 0,
    vipTabs: 0,
  );

  factory BarOverview.fromJson(Map<String, dynamic> json) {
    final stats = (json['stats'] as Map?)?.cast<String, dynamic>() ?? const {};
    return BarOverview(
      newCount: (stats['new'] as num?)?.toInt() ?? 0,
      preparingCount: (stats['preparing'] as num?)?.toInt() ?? 0,
      servedToday: (stats['served_today'] as num?)?.toInt() ?? 0,
      openTabs: (stats['open_tabs'] as num?)?.toInt() ?? 0,
      vipTabs: (stats['vip_tabs'] as num?)?.toInt() ?? 0,
    );
  }
}
