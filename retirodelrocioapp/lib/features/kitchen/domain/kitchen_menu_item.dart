import 'package:flutter/foundation.dart';

/// A dish on the Kitchen menu (`GET /kitchen/menu`) — the same catalog the
/// guest tablet's Place Order screen and the admin Kitchen dashboard
/// manage, shown here with availability toggling for staff.
@immutable
class KitchenMenuItem {
  const KitchenMenuItem({
    required this.id,
    required this.slug,
    required this.name,
    required this.description,
    required this.category,
    required this.categoryLabel,
    required this.price,
    required this.priceLabel,
    required this.prepLabel,
    required this.isActive,
    required this.imageUrl,
  });

  final int id;
  final String slug;
  final String name;
  final String? description;
  final String category;
  final String categoryLabel;
  final int price;
  final String priceLabel;
  final String? prepLabel;
  final bool isActive;
  final String? imageUrl;

  factory KitchenMenuItem.fromJson(Map<String, dynamic> json) =>
      KitchenMenuItem(
        id: (json['id'] as num?)?.toInt() ?? 0,
        slug: json['slug'] as String? ?? '',
        name: json['name'] as String? ?? '',
        description: json['description'] as String?,
        category: json['category'] as String? ?? 'mains',
        categoryLabel: json['category_label'] as String? ?? 'Mains',
        price: (json['price'] as num?)?.toInt() ?? 0,
        priceLabel: json['price_label'] as String? ?? 'NGN 0',
        prepLabel: json['prep_label'] as String?,
        isActive: json['is_active'] as bool? ?? true,
        imageUrl: json['image_url'] as String?,
      );
}
