import 'package:flutter/foundation.dart';

/// One drink line on a [BarOrder] — enough detail for the board, the Void
/// Item dialog, and the age-verification gate.
@immutable
class BarOrderItem {
  const BarOrderItem({
    required this.menuItemId,
    required this.name,
    required this.price,
    required this.qty,
    required this.note,
    required this.isAlcoholic,
    required this.voided,
  });

  final int? menuItemId;
  final String name;
  final int price;
  final int qty;
  final String? note;
  final bool isAlcoholic;
  final bool voided;

  int get lineTotal => price * qty;

  factory BarOrderItem.fromJson(Map<String, dynamic> json) => BarOrderItem(
    menuItemId: (json['menu_item_id'] as num?)?.toInt(),
    name: json['name'] as String? ?? '',
    price: (json['price'] as num?)?.toInt() ?? 0,
    qty: (json['qty'] as num?)?.toInt() ?? 1,
    note: json['note'] as String?,
    isAlcoholic: json['is_alcoholic'] as bool? ?? false,
    voided: json['voided'] as bool? ?? false,
  );
}
