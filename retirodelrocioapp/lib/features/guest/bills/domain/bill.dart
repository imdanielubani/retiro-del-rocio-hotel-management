import 'package:flutter/foundation.dart';

/// One itemised charge line inside a [BillCategory] (e.g. "Room Rate").
@immutable
class BillLineItem {
  const BillLineItem({
    required this.label,
    this.sub,
    required this.amountLabel,
  });

  final String label;
  final String? sub;
  final String amountLabel;

  factory BillLineItem.fromJson(Map<String, dynamic> json) => BillLineItem(
    label: json['label'] as String? ?? '',
    sub: json['sub'] as String?,
    amountLabel: json['amount_label'] as String? ?? 'NGN 0',
  );
}

/// A charge category on the My Bills screen — Room Charges, Spa & Wellness,
/// Restaurant & Bar, Cinema, Gym. [hasCharges] false renders the dimmed
/// "No charges" card the design shows for a category with nothing booked yet.
@immutable
class BillCategory {
  const BillCategory({
    required this.key,
    required this.label,
    required this.itemCount,
    required this.amountLabel,
    required this.hasCharges,
    required this.items,
  });

  final String key;
  final String label;
  final int itemCount;
  final String? amountLabel;
  final bool hasCharges;
  final List<BillLineItem> items;

  factory BillCategory.fromJson(Map<String, dynamic> json) => BillCategory(
    key: json['key'] as String? ?? '',
    label: json['label'] as String? ?? '',
    itemCount: (json['item_count'] as num?)?.toInt() ?? 0,
    amountLabel: json['amount_label'] as String?,
    hasCharges: json['has_charges'] as bool? ?? false,
    items: ((json['items'] as List?) ?? const [])
        .map((i) => BillLineItem.fromJson((i as Map).cast()))
        .toList(),
  );
}

/// A row in the Bill Summary card, e.g. "Room Charges" — "NGN 21,250".
@immutable
class BillSummaryLine {
  const BillSummaryLine({required this.label, required this.amountLabel});

  final String label;
  final String amountLabel;

  factory BillSummaryLine.fromJson(Map<String, dynamic> json) =>
      BillSummaryLine(
        label: json['label'] as String? ?? '',
        amountLabel: json['amount_label'] as String? ?? 'NGN 0',
      );
}

/// The checkout countdown card at the bottom of the Bill Summary.
@immutable
class BillCheckoutReminder {
  const BillCheckoutReminder({
    required this.checkOutLabel,
    required this.daysRemainingLabel,
  });

  final String? checkOutLabel;
  final String daysRemainingLabel;

  factory BillCheckoutReminder.fromJson(Map<String, dynamic> json) =>
      BillCheckoutReminder(
        checkOutLabel: json['check_out_label'] as String?,
        daysRemainingLabel: json['days_remaining_label'] as String? ?? '',
      );
}

/// The checked-in guest's itemised folio (`GET /tablets/my-bills`).
@immutable
class Bill {
  const Bill({
    required this.roomName,
    required this.unitLabel,
    required this.categories,
    required this.summaryLines,
    required this.totalDueLabel,
    required this.checkoutReminder,
    required this.canPay,
  });

  final String? roomName;
  final String? unitLabel;
  final List<BillCategory> categories;
  final List<BillSummaryLine> summaryLines;
  final String totalDueLabel;
  final BillCheckoutReminder checkoutReminder;
  final bool canPay;

  static const empty = Bill(
    roomName: null,
    unitLabel: null,
    categories: [],
    summaryLines: [],
    totalDueLabel: 'NGN 0',
    checkoutReminder: BillCheckoutReminder(
      checkOutLabel: null,
      daysRemainingLabel: '',
    ),
    canPay: false,
  );

  factory Bill.fromJson(Map<String, dynamic> json) => Bill(
    roomName: (json['reservation'] as Map?)?['room_name'] as String?,
    unitLabel: (json['reservation'] as Map?)?['unit_label'] as String?,
    categories: ((json['categories'] as List?) ?? const [])
        .map((c) => BillCategory.fromJson((c as Map).cast()))
        .toList(),
    summaryLines: (((json['summary'] as Map?)?['lines'] as List?) ?? const [])
        .map((l) => BillSummaryLine.fromJson((l as Map).cast()))
        .toList(),
    totalDueLabel:
        (json['summary'] as Map?)?['total_due_label'] as String? ?? 'NGN 0',
    checkoutReminder: BillCheckoutReminder.fromJson(
      ((json['checkout_reminder'] as Map?) ?? const {}).cast(),
    ),
    canPay: json['can_pay'] as bool? ?? false,
  );
}

/// The priced quote returned when opening a Paystack charge to pre-settle the
/// guest's outstanding balance (`POST /tablets/my-bills/initialize`).
@immutable
class BillPaymentQuote {
  const BillPaymentQuote({
    required this.authorizationUrl,
    required this.reference,
    required this.callbackUrl,
    required this.totalLabel,
  });

  final String authorizationUrl;
  final String reference;
  final String callbackUrl;
  final String totalLabel;

  factory BillPaymentQuote.fromJson(Map<String, dynamic> json) =>
      BillPaymentQuote(
        authorizationUrl: json['authorization_url'] as String? ?? '',
        reference: json['reference'] as String? ?? '',
        callbackUrl: json['callback_url'] as String? ?? '',
        totalLabel: json['total_label'] as String? ?? 'NGN 0',
      );
}

/// The confirmed payment, returned once the Paystack charge is verified —
/// drives the "Bill Confirmed!" success screen.
@immutable
class BillPaymentConfirmation {
  const BillPaymentConfirmation({
    required this.reference,
    required this.totalLabel,
  });

  final String reference;
  final String totalLabel;

  factory BillPaymentConfirmation.fromJson(Map<String, dynamic> json) =>
      BillPaymentConfirmation(
        reference: json['reference'] as String? ?? '',
        totalLabel: json['total_label'] as String? ?? 'NGN 0',
      );
}
