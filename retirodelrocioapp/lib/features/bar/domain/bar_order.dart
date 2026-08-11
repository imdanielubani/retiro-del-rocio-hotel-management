import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_order_item.dart';

/// A drink order the Bar Tablet manages — whether rung up on this tablet's
/// own POS (against an open [BarTab]) or placed by a guest from their room
/// tablet's Place Order screen. Both are the exact same backend record
/// (`GET /bar/orders`), so the board treats them identically except for
/// [source] and [tableLabel].
@immutable
class BarOrder {
  const BarOrder({
    required this.id,
    required this.code,
    required this.reference,
    required this.barTabId,
    required this.tabCode,
    required this.tableLabel,
    required this.isVip,
    required this.guestName,
    required this.source,
    required this.items,
    required this.itemsLabel,
    required this.itemCount,
    required this.placedAtLabel,
    required this.placedAtShort,
    required this.status,
    required this.statusLabel,
    required this.boardColumn,
    required this.boardColumnLabel,
    required this.totalLabel,
    required this.assignedToName,
    required this.requiresAgeVerification,
    required this.ageVerified,
    required this.paymentStatus,
    required this.paymentStatusLabel,
  });

  final int id;
  final String code;
  final String reference;
  final int? barTabId;
  final String? tabCode;
  final String? tableLabel;
  final bool isVip;
  final String? guestName;

  /// `pos` (rung up on this tablet) or `guest_tablet` (placed from a room).
  final String source;

  final List<BarOrderItem> items;
  final String itemsLabel;
  final int itemCount;
  final String? placedAtLabel;
  final String? placedAtShort;

  /// pending · confirmed · preparing · ready · on_way · delivered · cancelled.
  final String status;
  final String statusLabel;

  /// new · preparing · served — the Live Orders board's 3 columns.
  final String boardColumn;
  final String boardColumnLabel;

  final String totalLabel;
  final String? assignedToName;
  final bool requiresAgeVerification;
  final bool ageVerified;
  final String paymentStatus;
  final String paymentStatusLabel;

  bool get isPos => source == 'pos';

  bool get needsAgeCheck => requiresAgeVerification && !ageVerified;

  factory BarOrder.fromJson(Map<String, dynamic> json) => BarOrder(
    id: (json['id'] as num?)?.toInt() ?? 0,
    code: json['code'] as String? ?? '',
    reference: json['reference'] as String? ?? '',
    barTabId: (json['bar_tab_id'] as num?)?.toInt(),
    tabCode: json['tab_code'] as String?,
    tableLabel: json['table_label'] as String?,
    isVip: json['is_vip'] as bool? ?? false,
    guestName: json['guest_name'] as String?,
    source: json['source'] as String? ?? 'guest_tablet',
    items: ((json['items'] as List?) ?? const [])
        .map((i) => BarOrderItem.fromJson((i as Map).cast()))
        .toList(),
    itemsLabel: json['items_label'] as String? ?? 'Order',
    itemCount: (json['item_count'] as num?)?.toInt() ?? 0,
    placedAtLabel: json['placed_at_label'] as String?,
    placedAtShort: json['placed_at_short'] as String?,
    status: json['status'] as String? ?? '',
    statusLabel: json['status_label'] as String? ?? '',
    boardColumn: json['board_column'] as String? ?? 'new',
    boardColumnLabel: json['board_column_label'] as String? ?? 'New',
    totalLabel: json['total_label'] as String? ?? '₦0',
    assignedToName: json['assigned_to_name'] as String?,
    requiresAgeVerification:
        json['requires_age_verification'] as bool? ?? false,
    ageVerified: json['age_verified'] as bool? ?? false,
    paymentStatus: json['payment_status'] as String? ?? 'pending',
    paymentStatusLabel: json['payment_status_label'] as String? ?? 'Pending',
  );
}
