import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_order_item.dart';

/// A food ticket the Kitchen Tablet manages — whether placed by a guest from
/// their room tablet's Place Order screen, or rung up mixed with drinks via
/// the Bar Tablet's POS. Both are the exact same backend record
/// (`GET /kitchen/orders`), so the board treats them identically except for
/// [source] and [tableLabel]/[roomLabel].
@immutable
class KitchenOrder {
  const KitchenOrder({
    required this.id,
    required this.code,
    required this.reference,
    required this.tableLabel,
    required this.roomLabel,
    required this.guestName,
    required this.source,
    required this.hasDrinks,
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
    required this.paymentStatus,
    required this.paymentStatusLabel,
  });

  final int id;
  final String code;
  final String reference;

  /// "Table 4" if this ticket rode along on a Bar Tablet POS tab, else null.
  final String? tableLabel;

  /// "Room 101" for a guest-tablet room order, else null.
  final String? roomLabel;
  final String? guestName;

  /// `pos` (rung up on the Bar Tablet, mixed with drinks) or `guest_tablet`.
  final String source;

  /// Whether this ticket also includes a drink — a mixed order the Bar
  /// Tablet is handling too.
  final bool hasDrinks;

  final List<KitchenOrderItem> items;
  final String itemsLabel;
  final int itemCount;
  final String? placedAtLabel;
  final String? placedAtShort;

  /// pending · confirmed · preparing · ready · on_way · delivered · cancelled.
  final String status;
  final String statusLabel;

  /// new · preparing · served — the Live Board's 3 columns.
  final String boardColumn;
  final String boardColumnLabel;

  final String totalLabel;
  final String? assignedToName;
  final String paymentStatus;
  final String paymentStatusLabel;

  bool get isPos => source == 'pos';

  /// Wherever this ticket is headed — the tab's table, or the guest's room.
  String get destinationLabel => tableLabel ?? roomLabel ?? guestName ?? code;

  factory KitchenOrder.fromJson(Map<String, dynamic> json) => KitchenOrder(
    id: (json['id'] as num?)?.toInt() ?? 0,
    code: json['code'] as String? ?? '',
    reference: json['reference'] as String? ?? '',
    tableLabel: json['table_label'] as String?,
    roomLabel: json['room_label'] as String?,
    guestName: json['guest_name'] as String?,
    source: json['source'] as String? ?? 'guest_tablet',
    hasDrinks: json['has_drinks'] as bool? ?? false,
    items: ((json['items'] as List?) ?? const [])
        .map((i) => KitchenOrderItem.fromJson((i as Map).cast()))
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
    paymentStatus: json['payment_status'] as String? ?? 'pending',
    paymentStatusLabel: json['payment_status_label'] as String? ?? 'Pending',
  );
}
