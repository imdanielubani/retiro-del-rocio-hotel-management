import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_order.dart';

/// An open running balance at the bar — the Tabs screen list row and Tab
/// Detail payload (`GET /bar/tabs`, `GET /bar/tabs/{id}`).
@immutable
class BarTab {
  const BarTab({
    required this.id,
    required this.code,
    required this.tableLabel,
    required this.guestName,
    required this.isVip,
    required this.status,
    required this.statusLabel,
    required this.orderCount,
    required this.totalLabel,
    required this.bartenderName,
    required this.openedByName,
    required this.openedAtLabel,
    required this.settledAtLabel,
    this.paymentMethod,
    this.paymentStatus,
    this.notes,
    this.reservationCode,
    this.chargedRoomLabel,
    this.orders = const [],
  });

  final int id;
  final String code;
  final String? tableLabel;
  final String? guestName;
  final bool isVip;

  /// open · settled · void.
  final String status;
  final String statusLabel;

  final int orderCount;
  final String totalLabel;
  final String? bartenderName;
  final String? openedByName;
  final String? openedAtLabel;
  final String? settledAtLabel;
  final String? paymentMethod;
  final String? paymentStatus;
  final String? notes;

  /// The table/lounge reservation this tab was opened from, if any.
  final String? reservationCode;

  /// "Room 204", if this tab was settled "Charge to Room".
  final String? chargedRoomLabel;
  final List<BarOrder> orders;

  bool get isOpen => status == 'open';

  factory BarTab.fromJson(Map<String, dynamic> json) => BarTab(
    id: (json['id'] as num?)?.toInt() ?? 0,
    code: json['code'] as String? ?? '',
    tableLabel: json['table_label'] as String?,
    guestName: json['guest_name'] as String?,
    isVip: json['is_vip'] as bool? ?? false,
    status: json['status'] as String? ?? 'open',
    statusLabel: json['status_label'] as String? ?? 'Open',
    orderCount: (json['order_count'] as num?)?.toInt() ?? 0,
    totalLabel: json['total_label'] as String? ?? '₦0',
    bartenderName: json['bartender_name'] as String?,
    openedByName: json['opened_by_name'] as String?,
    openedAtLabel: json['opened_at_label'] as String?,
    settledAtLabel: json['settled_at_label'] as String?,
    paymentMethod: json['payment_method'] as String?,
    paymentStatus: json['payment_status'] as String?,
    notes: json['notes'] as String?,
    reservationCode: json['reservation_code'] as String?,
    chargedRoomLabel: json['charged_room_label'] as String?,
    orders: ((json['orders'] as List?) ?? const [])
        .map((o) => BarOrder.fromJson((o as Map).cast()))
        .toList(),
  );
}
