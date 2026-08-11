import 'package:flutter/foundation.dart';

/// A bookable dish or drink (`GET /tablets/dining/menu`), sourced from the
/// restaurant's admin-managed menu.
@immutable
class MenuItem {
  const MenuItem({
    required this.id,
    required this.slug,
    required this.name,
    required this.description,
    required this.category,
    required this.categoryLabel,
    required this.price,
    required this.priceLabel,
    required this.prepMinutes,
    required this.prepLabel,
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
  final int? prepMinutes;
  final String? prepLabel;
  final String? imageUrl;

  factory MenuItem.fromJson(Map<String, dynamic> json) => MenuItem(
    id: (json['id'] as num?)?.toInt() ?? 0,
    slug: json['slug'] as String? ?? '',
    name: json['name'] as String? ?? '',
    description: json['description'] as String?,
    category: json['category'] as String? ?? 'mains',
    categoryLabel: json['category_label'] as String? ?? 'Mains',
    price: (json['price'] as num?)?.toInt() ?? 0,
    priceLabel: json['price_label'] as String? ?? 'NGN 0',
    prepMinutes: (json['prep_minutes'] as num?)?.toInt(),
    prepLabel: json['prep_label'] as String?,
    imageUrl: json['image_url'] as String?,
  );
}

/// The full menu (`GET /tablets/dining/menu`): every bookable dish, the
/// fixed set of categories, and the flat room-service fee applied to every
/// order.
@immutable
class DiningMenu {
  const DiningMenu({
    required this.items,
    required this.categories,
    required this.serviceFee,
    required this.serviceFeeLabel,
  });

  final List<MenuItem> items;
  final List<String> categories;
  final int serviceFee;
  final String serviceFeeLabel;

  static const empty = DiningMenu(
    items: [],
    categories: [],
    serviceFee: 0,
    serviceFeeLabel: 'NGN 0',
  );

  factory DiningMenu.fromJson(Map<String, dynamic> json) => DiningMenu(
    items: ((json['items'] as List?) ?? const [])
        .map((m) => MenuItem.fromJson((m as Map).cast()))
        .toList(),
    categories: ((json['categories'] as List?) ?? const [])
        .map((c) => c as String)
        .toList(),
    serviceFee: (json['service_fee'] as num?)?.toInt() ?? 0,
    serviceFeeLabel: json['service_fee_label'] as String? ?? 'NGN 0',
  );
}

/// A dish the guest has added to their cart, with how many and an optional
/// note. Cart state lives on the ordering screen itself, the same way
/// Cinema keeps its snack selections local until the booking is placed.
@immutable
class CartLine {
  const CartLine({required this.item, required this.qty, this.note});

  final MenuItem item;
  final int qty;
  final String? note;

  int get lineTotal => item.price * qty;

  CartLine copyWith({int? qty, String? note}) =>
      CartLine(item: item, qty: qty ?? this.qty, note: note ?? this.note);

  Map<String, dynamic> toJson() => {
    'menu_item_id': item.id,
    'qty': qty,
    if (note != null && note!.trim().isNotEmpty) 'note': note!.trim(),
  };
}

/// How the guest wants to pay for a dining order.
enum DiningPaymentMethod { room, paystack }

/// The order's position in the kitchen lifecycle the My Orders progress
/// tracker renders (Figma 130:415): Received → Preparing → Ready → On Way →
/// Done. [stepNumber] is 1-based, matching the tracker's numbered steps
/// (deliberately not named `index` — that name is reserved by `Enum`).
enum DiningOrderStage {
  received(1),
  preparing(2),
  ready(3),
  onWay(4),
  done(5);

  const DiningOrderStage(this.stepNumber);
  final int stepNumber;

  static DiningOrderStage fromStatus(String status) => switch (status) {
    'preparing' => DiningOrderStage.preparing,
    'ready' => DiningOrderStage.ready,
    'on_way' => DiningOrderStage.onWay,
    'delivered' => DiningOrderStage.done,
    _ => DiningOrderStage.received,
  };
}

/// One dish within a placed order (`GET /tablets/dining/orders`) — enough
/// detail (id, price, qty) to rebuild a [CartLine] for "Reorder", unlike
/// [MenuItem] which always reflects today's live menu.
@immutable
class OrderLineItem {
  const OrderLineItem({
    required this.menuItemId,
    required this.name,
    required this.price,
    required this.qty,
    required this.imageUrl,
  });

  final int? menuItemId;
  final String name;
  final int price;
  final int qty;
  final String? imageUrl;

  factory OrderLineItem.fromJson(Map<String, dynamic> json) => OrderLineItem(
    menuItemId: (json['menu_item_id'] as num?)?.toInt(),
    name: json['name'] as String? ?? '',
    price: (json['price'] as num?)?.toInt() ?? 0,
    qty: (json['qty'] as num?)?.toInt() ?? 1,
    imageUrl: json['image_url'] as String?,
  );
}

/// One of the guest's own confirmed dining orders
/// (`GET /tablets/dining/orders`) — past or upcoming, however it was paid.
/// Drives the "My Orders" list (Figma 130:415).
@immutable
class DiningOrderSummary {
  const DiningOrderSummary({
    required this.id,
    required this.code,
    required this.reference,
    required this.items,
    required this.itemsLabel,
    required this.itemCount,
    required this.placedAtLabel,
    required this.status,
    required this.statusLabel,
    required this.isActive,
    required this.etaLabel,
    required this.chargedToRoom,
    required this.paymentMethodLabel,
    required this.totalLabel,
  });

  final int id;

  /// Friendly display code, e.g. "ORD-2601-003" — distinct from [reference]
  /// (the Paystack-safe unique string).
  final String code;
  final String reference;
  final List<OrderLineItem> items;
  final String itemsLabel;
  final int itemCount;
  final String? placedAtLabel;

  /// confirmed · preparing · ready · on_way · delivered · cancelled.
  final String status;
  final String statusLabel;

  /// Still moving through the kitchen — the progress tracker only renders
  /// for these; a delivered/cancelled order shows just its final status pill.
  final bool isActive;
  final String? etaLabel;
  final bool chargedToRoom;
  final String paymentMethodLabel;
  final String totalLabel;

  DiningOrderStage get stage => DiningOrderStage.fromStatus(status);

  factory DiningOrderSummary.fromJson(Map<String, dynamic> json) =>
      DiningOrderSummary(
        id: (json['id'] as num?)?.toInt() ?? 0,
        code: json['code'] as String? ?? '',
        reference: json['reference'] as String? ?? '',
        items: ((json['items'] as List?) ?? const [])
            .map((i) => OrderLineItem.fromJson((i as Map).cast()))
            .toList(),
        itemsLabel: json['items_label'] as String? ?? 'Dining order',
        itemCount: (json['item_count'] as num?)?.toInt() ?? 0,
        placedAtLabel: json['placed_at_label'] as String?,
        status: json['status'] as String? ?? '',
        statusLabel: json['status_label'] as String? ?? '',
        isActive: json['is_active'] as bool? ?? false,
        etaLabel: json['eta_label'] as String?,
        chargedToRoom: json['charged_to_room'] as bool? ?? false,
        paymentMethodLabel: json['payment_method_label'] as String? ?? '',
        totalLabel: json['total_label'] as String? ?? 'NGN 0',
      );
}

/// The priced quote returned when opening a Paystack charge for a dining
/// order (`POST /tablets/dining/initialize`).
@immutable
class DiningOrderQuote {
  const DiningOrderQuote({
    required this.authorizationUrl,
    required this.reference,
    required this.callbackUrl,
    required this.subtotalLabel,
    required this.vatLabel,
    required this.serviceFeeLabel,
    required this.totalLabel,
  });

  final String authorizationUrl;
  final String reference;
  final String callbackUrl;
  final String subtotalLabel;
  final String vatLabel;
  final String serviceFeeLabel;
  final String totalLabel;

  factory DiningOrderQuote.fromJson(Map<String, dynamic> json) =>
      DiningOrderQuote(
        authorizationUrl: json['authorization_url'] as String? ?? '',
        reference: json['reference'] as String? ?? '',
        callbackUrl: json['callback_url'] as String? ?? '',
        subtotalLabel: json['subtotal_label'] as String? ?? 'NGN 0',
        vatLabel: json['vat_label'] as String? ?? 'NGN 0',
        serviceFeeLabel: json['service_fee_label'] as String? ?? 'NGN 0',
        totalLabel: json['total_label'] as String? ?? 'NGN 0',
      );
}

/// The confirmed order, returned by both the room-charge and the Paystack
/// confirm endpoints — drives the "Order Confirmed" success screen.
@immutable
class DiningOrderConfirmation {
  const DiningOrderConfirmation({
    required this.reference,
    required this.itemsLabel,
    required this.itemCount,
    required this.totalLabel,
  });

  final String reference;
  final String itemsLabel;
  final int itemCount;
  final String totalLabel;

  factory DiningOrderConfirmation.fromJson(Map<String, dynamic> json) =>
      DiningOrderConfirmation(
        reference: json['reference'] as String? ?? '',
        itemsLabel: json['items_label'] as String? ?? 'Dining order',
        itemCount: (json['item_count'] as num?)?.toInt() ?? 0,
        totalLabel: json['total_label'] as String? ?? 'NGN 0',
      );
}
