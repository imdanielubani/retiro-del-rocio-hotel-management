import 'package:flutter/foundation.dart';

/// The Kitchen Tablet dashboard headline stats (`GET /kitchen/overview`).
@immutable
class KitchenOverview {
  const KitchenOverview({
    required this.newCount,
    required this.preparingCount,
    required this.readyCount,
    required this.servedToday,
  });

  final int newCount;
  final int preparingCount;

  /// Tickets in the `served` board column, still today — the expo view's
  /// "ready to run" count.
  final int readyCount;
  final int servedToday;

  static const empty = KitchenOverview(
    newCount: 0,
    preparingCount: 0,
    readyCount: 0,
    servedToday: 0,
  );

  factory KitchenOverview.fromJson(Map<String, dynamic> json) {
    final stats = (json['stats'] as Map?)?.cast<String, dynamic>() ?? const {};
    return KitchenOverview(
      newCount: (stats['new'] as num?)?.toInt() ?? 0,
      preparingCount: (stats['preparing'] as num?)?.toInt() ?? 0,
      readyCount: (stats['ready'] as num?)?.toInt() ?? 0,
      servedToday: (stats['served_today'] as num?)?.toInt() ?? 0,
    );
  }
}
