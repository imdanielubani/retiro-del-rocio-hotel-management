import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/features/guest/bills/domain/bill.dart'
    show BillCategory, BillSummaryLine;

/// One checked-in guest's outstanding room-charge balance, for the reception
/// Bills list (`GET /reception/bills`).
@immutable
class ReceptionBillRow {
  const ReceptionBillRow({
    required this.bookingId,
    required this.guestName,
    required this.roomLabel,
    required this.checkOutLabel,
    required this.totalDueLabel,
    required this.hasBalance,
  });

  final int bookingId;
  final String guestName;
  final String roomLabel;
  final String? checkOutLabel;
  final String totalDueLabel;
  final bool hasBalance;

  factory ReceptionBillRow.fromJson(Map<String, dynamic> json) => ReceptionBillRow(
    bookingId: (json['booking_id'] as num?)?.toInt() ?? 0,
    guestName: json['guest_name'] as String? ?? 'Guest',
    roomLabel: json['room_label'] as String? ?? '—',
    checkOutLabel: json['check_out_label'] as String?,
    totalDueLabel: json['total_due_label'] as String? ?? 'NGN 0',
    hasBalance: json['has_balance'] as bool? ?? false,
  );
}

/// The Bills list plus its headline totals.
@immutable
class ReceptionBillsOverview {
  const ReceptionBillsOverview({
    required this.totalOutstandingLabel,
    required this.guestsWithBalance,
    required this.rows,
  });

  final String totalOutstandingLabel;
  final int guestsWithBalance;
  final List<ReceptionBillRow> rows;

  static const empty = ReceptionBillsOverview(
    totalOutstandingLabel: 'NGN 0',
    guestsWithBalance: 0,
    rows: [],
  );

  factory ReceptionBillsOverview.fromJson(Map<String, dynamic> json) {
    final summary = (json['summary'] as Map?)?.cast<String, dynamic>() ?? const {};

    return ReceptionBillsOverview(
      totalOutstandingLabel: summary['total_outstanding_label'] as String? ?? 'NGN 0',
      guestsWithBalance: (summary['guests_with_balance'] as num?)?.toInt() ?? 0,
      rows: ((json['bills'] as List?) ?? const [])
          .map((r) => ReceptionBillRow.fromJson((r as Map).cast()))
          .toList(),
    );
  }
}

/// One guest's itemised folio for the desk to review (`GET
/// /reception/bookings/{booking}/bill`) — the same real numbers the guest
/// tablet's own My Bills screen shows them.
@immutable
class ReceptionBillDetail {
  const ReceptionBillDetail({
    required this.guestName,
    required this.roomName,
    required this.unitLabel,
    required this.checkInLabel,
    required this.checkOutLabel,
    required this.categories,
    required this.summaryLines,
    required this.totalDueLabel,
  });

  final String guestName;
  final String? roomName;
  final String? unitLabel;
  final String? checkInLabel;
  final String? checkOutLabel;
  final List<BillCategory> categories;
  final List<BillSummaryLine> summaryLines;
  final String totalDueLabel;

  factory ReceptionBillDetail.fromJson(Map<String, dynamic> json) {
    final reservation = (json['reservation'] as Map?)?.cast<String, dynamic>() ?? const {};
    final summary = (json['summary'] as Map?)?.cast<String, dynamic>() ?? const {};

    return ReceptionBillDetail(
      guestName: reservation['guest_name'] as String? ?? 'Guest',
      roomName: reservation['room_name'] as String?,
      unitLabel: reservation['unit_label'] as String?,
      checkInLabel: reservation['check_in_label'] as String?,
      checkOutLabel: reservation['check_out_label'] as String?,
      categories: ((json['categories'] as List?) ?? const [])
          .map((c) => BillCategory.fromJson((c as Map).cast()))
          .toList(),
      summaryLines: ((summary['lines'] as List?) ?? const [])
          .map((l) => BillSummaryLine.fromJson((l as Map).cast()))
          .toList(),
      totalDueLabel: summary['total_due_label'] as String? ?? 'NGN 0',
    );
  }
}
