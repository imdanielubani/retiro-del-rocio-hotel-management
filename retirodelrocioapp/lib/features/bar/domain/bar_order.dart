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
    required this.roomLabel,
    required this.isVip,
    required this.guestName,
    required this.source,
    required this.hasFood,
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
    this.estimatedReadyLabel,
    this.estimatedReadyMinutes,
    this.estimatedReadyOverdue = false,
  });

  final int id;
  final String code;
  final String reference;
  final int? barTabId;
  final String? tabCode;
  final String? tableLabel;

  /// "Room 101" for a guest-tablet room order, else null.
  final String? roomLabel;
  final bool isVip;
  final String? guestName;

  /// `pos` (rung up on this tablet) or `guest_tablet` (placed from a room).
  final String source;

  /// Whether this order includes any Kitchen (food) item — a drinks-only
  /// order has no preparing stage, it goes straight from New to Served.
  final bool hasFood;

  final List<BarOrderItem> items;
  final String itemsLabel;
  final int itemCount;
  final String? placedAtLabel;
  final String? placedAtShort;

  /// pending · confirmed · preparing · ready · on_way · delivered · cancelled.
  final String status;
  final String statusLabel;

  /// new · preparing · ready · served — the Live Orders board's columns.
  /// "ready" means the Kitchen has it waiting for pickup — a food item
  /// on this order isn't at the table yet even though a drink might be.
  final String boardColumn;
  final String boardColumnLabel;

  final String totalLabel;
  final String? assignedToName;
  final bool requiresAgeVerification;
  final bool ageVerified;
  final String paymentStatus;
  final String paymentStatusLabel;

  /// "7:45 PM" — when the Kitchen expects a food item on this order ready,
  /// so the waiter can tell the guest a real time.
  final String? estimatedReadyLabel;

  /// Minutes remaining until [estimatedReadyLabel], or 0 once due.
  final int? estimatedReadyMinutes;

  /// True once the estimate has passed and it still isn't ready.
  final bool estimatedReadyOverdue;

  bool get isPos => source == 'pos';

  bool get needsAgeCheck => requiresAgeVerification && !ageVerified;

  /// Wherever this order is headed — the tab's table, or the guest's room —
  /// takes priority over the guest's name so a waiter can tell at a glance
  /// where to deliver without opening the order.
  String get destinationLabel => tableLabel ?? roomLabel ?? guestName ?? code;

  factory BarOrder.fromJson(Map<String, dynamic> json) => BarOrder(
    id: (json['id'] as num?)?.toInt() ?? 0,
    code: json['code'] as String? ?? '',
    reference: json['reference'] as String? ?? '',
    barTabId: (json['bar_tab_id'] as num?)?.toInt(),
    tabCode: json['tab_code'] as String?,
    tableLabel: json['table_label'] as String?,
    roomLabel: json['room_label'] as String?,
    isVip: json['is_vip'] as bool? ?? false,
    guestName: json['guest_name'] as String?,
    source: json['source'] as String? ?? 'guest_tablet',
    hasFood: json['has_food'] as bool? ?? false,
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
    estimatedReadyLabel: json['estimated_ready_label'] as String?,
    estimatedReadyMinutes: (json['estimated_ready_minutes'] as num?)?.toInt(),
    estimatedReadyOverdue: json['estimated_ready_overdue'] as bool? ?? false,
  );
}
