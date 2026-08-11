import 'package:flutter/foundation.dart';

/// A drink on the Bar & Lounge menu (`GET /bar/menu`) — the same catalog the
/// guest tablet's Place Order screen and the admin Bar & Lounge dashboard
/// manage, shown here with availability toggling for staff.
@immutable
class BarMenuItem {
  const BarMenuItem({
    required this.id,
    required this.slug,
    required this.name,
    required this.description,
    required this.category,
    required this.categoryLabel,
    required this.department,
    required this.price,
    required this.priceLabel,
    required this.isAlcoholic,
    required this.isActive,
    required this.imageUrl,
  });

  final int id;
  final String slug;
  final String name;
  final String? description;
  final String category;
  final String categoryLabel;

  /// 'food' or 'drink' — which side of the POS this item belongs on.
  final String department;
  final int price;
  final String priceLabel;
  final bool isAlcoholic;
  final bool isActive;
  final String? imageUrl;

  bool get isFood => department == 'food';

  factory BarMenuItem.fromJson(Map<String, dynamic> json) => BarMenuItem(
    id: (json['id'] as num?)?.toInt() ?? 0,
    slug: json['slug'] as String? ?? '',
    name: json['name'] as String? ?? '',
    description: json['description'] as String?,
    category: json['category'] as String? ?? 'drinks',
    categoryLabel: json['category_label'] as String? ?? 'Drinks',
    department: json['department'] as String? ?? 'drink',
    price: (json['price'] as num?)?.toInt() ?? 0,
    priceLabel: json['price_label'] as String? ?? 'NGN 0',
    isAlcoholic: json['is_alcoholic'] as bool? ?? false,
    isActive: json['is_active'] as bool? ?? true,
    imageUrl: json['image_url'] as String?,
  );
}
