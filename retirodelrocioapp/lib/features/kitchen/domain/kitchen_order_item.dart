import 'package:flutter/foundation.dart';

/// One dish line on a [KitchenOrder] — enough detail for the ticket board
/// and the Void/Cancel Item dialog.
@immutable
class KitchenOrderItem {
  const KitchenOrderItem({
    required this.menuItemId,
    required this.name,
    required this.price,
    required this.qty,
    required this.note,
    required this.category,
    required this.voided,
  });

  final int? menuItemId;
  final String name;
  final int price;
  final int qty;
  final String? note;
  final String? category;
  final bool voided;

  int get lineTotal => price * qty;

  factory KitchenOrderItem.fromJson(Map<String, dynamic> json) =>
      KitchenOrderItem(
        menuItemId: (json['menu_item_id'] as num?)?.toInt(),
        name: json['name'] as String? ?? '',
        price: (json['price'] as num?)?.toInt() ?? 0,
        qty: (json['qty'] as num?)?.toInt() ?? 1,
        note: json['note'] as String?,
        category: json['category'] as String?,
        voided: json['voided'] as bool? ?? false,
      );
}
