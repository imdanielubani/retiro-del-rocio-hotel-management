import 'package:flutter/foundation.dart';

/// How a guest actually paid an outstanding balance settled at the desk in
/// person — picked by reception before tapping "Mark as Paid", not assumed.
enum ReceptionDeskPaymentMethod {
  cash('cash', 'Cash'),
  card('card', 'Card'),
  bankTransfer('bank_transfer', 'Bank Transfer');

  const ReceptionDeskPaymentMethod(this.apiValue, this.label);

  final String apiValue;
  final String label;
}

/// Where the pre-checkout inspection reception asked for stands.
enum ReceptionInspectionStatus {
  /// Reception hasn't asked housekeeping to inspect the room yet.
  notRequested,

  /// Housekeeping has been asked and hasn't cleared it yet.
  pending,

  /// Housekeeping has inspected and cleared the room.
  completed;

  static ReceptionInspectionStatus fromApi(String? value) => switch (value) {
    'pending' => pending,
    'completed' => completed,
    _ => notRequested,
  };
}

/// One of the guest's still-open housekeeping requests or maintenance
/// faults, shown as a heads-up on the pre-checkout screen — it doesn't
/// block checkout, the desk is just made aware before releasing the room.
@immutable
class ReceptionDepartureOpenItem {
  const ReceptionDepartureOpenItem({required this.title, this.detail});

  final String title;
  final String? detail;

  factory ReceptionDepartureOpenItem.housekeeping(Map<String, dynamic> json) =>
      ReceptionDepartureOpenItem(
        title: json['type_label'] as String? ?? 'Request',
        detail: json['notes'] as String?,
      );

  factory ReceptionDepartureOpenItem.workOrder(Map<String, dynamic> json) =>
      ReceptionDepartureOpenItem(
        title: json['title'] as String? ?? 'Fault',
        detail: json['status_label'] as String?,
      );
}

/// One visitor pass still active against the stay — informational only,
/// checkout closes it automatically.
@immutable
class ReceptionDepartureVisitor {
  const ReceptionDepartureVisitor({
    required this.visitorName,
    required this.statusLabel,
  });

  final String visitorName;
  final String statusLabel;

  factory ReceptionDepartureVisitor.fromJson(Map<String, dynamic> json) =>
      ReceptionDepartureVisitor(
        visitorName: json['visitor_name'] as String? ?? 'Visitor',
        statusLabel: json['status_label'] as String? ?? '',
      );
}

/// Everything the desk should see before checking a guest out
/// (`GET /reception/bookings/{id}/departure-readiness`): the outstanding
/// balance (a hard block, enforced server-side too), the guest's open
/// housekeeping requests and maintenance faults (a heads-up), and any
/// visitor passes still active against the stay (informational).
@immutable
class ReceptionDepartureReadiness {
  const ReceptionDepartureReadiness({
    required this.due,
    required this.dueLabel,
    required this.canCheckOut,
    required this.inspectionStatus,
    required this.openRequests,
    required this.openWorkOrders,
    required this.activeVisitorPasses,
  });

  final int due;
  final String dueLabel;

  /// True once the balance is settled *and* housekeeping has completed the
  /// inspection reception requested — both are hard blocks, enforced
  /// server-side too.
  final bool canCheckOut;
  final ReceptionInspectionStatus inspectionStatus;
  final List<ReceptionDepartureOpenItem> openRequests;
  final List<ReceptionDepartureOpenItem> openWorkOrders;
  final List<ReceptionDepartureVisitor> activeVisitorPasses;

  bool get hasOpenItems => openRequests.isNotEmpty || openWorkOrders.isNotEmpty;

  static const empty = ReceptionDepartureReadiness(
    due: 0,
    dueLabel: 'NGN 0',
    canCheckOut: false,
    inspectionStatus: ReceptionInspectionStatus.notRequested,
    openRequests: [],
    openWorkOrders: [],
    activeVisitorPasses: [],
  );

  factory ReceptionDepartureReadiness.fromJson(Map<String, dynamic> json) {
    final openRequests = (json['open_requests'] as List?) ?? const [];
    final openWorkOrders = (json['open_work_orders'] as List?) ?? const [];
    final activeVisitorPasses =
        (json['active_visitor_passes'] as List?) ?? const [];

    return ReceptionDepartureReadiness(
      due: (json['due'] as num?)?.toInt() ?? 0,
      dueLabel: json['due_label'] as String? ?? 'NGN 0',
      canCheckOut: json['can_check_out'] as bool? ?? false,
      inspectionStatus: ReceptionInspectionStatus.fromApi(
        json['inspection_status'] as String?,
      ),
      openRequests: openRequests
          .whereType<Map>()
          .map(
            (e) => ReceptionDepartureOpenItem.housekeeping(
              e.cast<String, dynamic>(),
            ),
          )
          .toList(),
      openWorkOrders: openWorkOrders
          .whereType<Map>()
          .map(
            (e) =>
                ReceptionDepartureOpenItem.workOrder(e.cast<String, dynamic>()),
          )
          .toList(),
      activeVisitorPasses: activeVisitorPasses
          .whereType<Map>()
          .map(
            (e) =>
                ReceptionDepartureVisitor.fromJson(e.cast<String, dynamic>()),
          )
          .toList(),
    );
  }
}
