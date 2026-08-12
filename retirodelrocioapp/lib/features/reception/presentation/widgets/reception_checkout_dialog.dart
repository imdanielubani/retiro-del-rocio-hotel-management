import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/reception/application/reception_providers.dart';
import 'package:retirodelrocioapp/features/reception/data/reception_repository.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_departure_readiness.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_widgets.dart'
    show kReceptionBlue, kReceptionGreen;

const Color _red = Color(0xFFEF4444);

/// Opens the pre-checkout confirmation for [bookingId]: the desk sees the
/// outstanding balance first (with a one-tap "already paid" settlement),
/// then requests a room inspection from housekeeping — Check Out only
/// unlocks once housekeeping has actually cleared it. Returns true if the
/// guest was checked out.
Future<bool?> showReceptionCheckOutDialog(
  BuildContext context, {
  required StaffSession session,
  required int bookingId,
  required String guestName,
  String? roomLabel,
}) {
  return showDialog<bool>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.7),
    builder: (_) => _CheckOutDialog(
      session: session,
      bookingId: bookingId,
      guestName: guestName,
      roomLabel: roomLabel,
    ),
  );
}

class _CheckOutDialog extends ConsumerStatefulWidget {
  const _CheckOutDialog({
    required this.session,
    required this.bookingId,
    required this.guestName,
    this.roomLabel,
  });

  final StaffSession session;
  final int bookingId;
  final String guestName;
  final String? roomLabel;

  @override
  ConsumerState<_CheckOutDialog> createState() => _CheckOutDialogState();
}

class _CheckOutDialogState extends ConsumerState<_CheckOutDialog> {
  ReceptionDepartureReadiness? _data;
  bool _loading = true;
  String? _loadError;

  bool _busy = false;
  bool _settlingBill = false;
  bool _requestingInspection = false;
  String? _actionError;

  /// How the guest paid — reception must pick this before "Mark as Paid"
  /// enables, so the desk always records the real method instead of it
  /// defaulting to something that may not be true.
  ReceptionDeskPaymentMethod? _paymentMethod;

  Timer? _pollTimer;

  String get _token => widget.session.token;

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  /// Loads (or reloads) the readiness check. [silent] is used for the
  /// background poll while an inspection is pending — it never flashes the
  /// spinner or surfaces a transient network hiccup as a visible error.
  Future<void> _refresh({bool silent = false}) async {
    if (!silent) {
      setState(() {
        _loading = true;
        _loadError = null;
      });
    }
    try {
      final data = await ref
          .read(receptionActionsProvider(_token))
          .departureReadiness(widget.bookingId);
      if (!mounted) return;
      setState(() {
        _data = data;
        _loading = false;
      });
      _syncPolling();
    } on ReceptionException catch (e) {
      if (!mounted) return;
      if (!silent) setState(() => _loadError = e.message);
    } catch (_) {
      if (!mounted) return;
      if (!silent)
        setState(() => _loadError = 'Could not load checkout details.');
    }
  }

  /// While housekeeping hasn't cleared a requested inspection yet, poll for
  /// it every few seconds — the same "poll as backstop" rule the rest of the
  /// tablet follows — so the dialog unlocks itself the moment they mark it
  /// complete, without reception needing to close and reopen it.
  void _syncPolling() {
    final pending =
        _data?.inspectionStatus == ReceptionInspectionStatus.pending;
    if (pending && _pollTimer == null) {
      _pollTimer = Timer.periodic(
        const Duration(seconds: 6),
        (_) => _refresh(silent: true),
      );
    } else if (!pending && _pollTimer != null) {
      _pollTimer?.cancel();
      _pollTimer = null;
    }
  }

  Future<void> _settleBill() async {
    final method = _paymentMethod;
    if (method == null) return;

    setState(() {
      _settlingBill = true;
      _actionError = null;
    });
    try {
      final data = await ref
          .read(receptionActionsProvider(_token))
          .settleBill(widget.bookingId, method);
      if (mounted) setState(() => _data = data);
      _syncPolling();
    } on ReceptionException catch (e) {
      if (mounted) setState(() => _actionError = e.message);
    } catch (_) {
      if (mounted)
        setState(
          () => _actionError = 'Something went wrong. Please try again.',
        );
    } finally {
      if (mounted) setState(() => _settlingBill = false);
    }
  }

  Future<void> _requestInspection() async {
    setState(() {
      _requestingInspection = true;
      _actionError = null;
    });
    try {
      final data = await ref
          .read(receptionActionsProvider(_token))
          .requestInspection(widget.bookingId);
      if (mounted) setState(() => _data = data);
      _syncPolling();
    } on ReceptionException catch (e) {
      if (mounted) setState(() => _actionError = e.message);
    } catch (_) {
      if (mounted)
        setState(
          () => _actionError = 'Something went wrong. Please try again.',
        );
    } finally {
      if (mounted) setState(() => _requestingInspection = false);
    }
  }

  Future<void> _submit() async {
    setState(() {
      _busy = true;
      _actionError = null;
    });
    try {
      await ref
          .read(receptionActionsProvider(_token))
          .checkOut(widget.bookingId);
      if (mounted) Navigator.of(context).pop(true);
    } on ReceptionException catch (e) {
      setState(() => _actionError = e.message);
    } catch (_) {
      setState(() => _actionError = 'Something went wrong. Please try again.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final media = MediaQuery.of(context);
    final maxHeight = (media.size.height - media.viewInsets.bottom - 48).clamp(
      280.0,
      640.0,
    );

    return Dialog(
      backgroundColor: const Color(0xFF161616),
      insetPadding: const EdgeInsets.all(24),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: 560, maxHeight: maxHeight),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _header(),
              const SizedBox(height: 20),
              Flexible(
                child: SingleChildScrollView(
                  child: _loading
                      ? const Padding(
                          padding: EdgeInsets.symmetric(vertical: 40),
                          child: Center(
                            child: CircularProgressIndicator(
                              color: AppColors.gold,
                            ),
                          ),
                        )
                      : (_loadError != null || _data == null)
                      ? _loadFailed()
                      : _readiness(_data!),
                ),
              ),
              if (_actionError != null) ...[
                const SizedBox(height: 12),
                Text(
                  _actionError!,
                  style: AppTypography.style(color: _red, fontSize: 13),
                ),
              ],
              const SizedBox(height: 20),
              _footer(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _header() {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Check Out',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 22,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                [
                  widget.guestName,
                  if ((widget.roomLabel ?? '').isNotEmpty) widget.roomLabel!,
                ].join(' · '),
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.5),
                  fontSize: 13,
                ),
              ),
            ],
          ),
        ),
        Material(
          color: Colors.white.withValues(alpha: 0.08),
          shape: const CircleBorder(),
          child: InkWell(
            onTap: _busy ? null : () => Navigator.of(context).pop(false),
            customBorder: const CircleBorder(),
            child: const SizedBox(
              width: 34,
              height: 34,
              child: Icon(Icons.close_rounded, size: 18, color: Colors.white),
            ),
          ),
        ),
      ],
    );
  }

  Widget _loadFailed() {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 32),
      child: Column(
        children: [
          Text(
            _loadError ?? 'Could not load checkout details.',
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.6),
              fontSize: 14,
            ),
          ),
          const SizedBox(height: 12),
          TextButton(
            onPressed: () => _refresh(),
            child: const Text('Retry', style: TextStyle(color: AppColors.gold)),
          ),
        ],
      ),
    );
  }

  Widget _readiness(ReceptionDepartureReadiness data) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (data.due > 0) _balanceBlock(data),
        if (data.hasOpenItems) ...[
          const SizedBox(height: 12),
          _openItemsBlock(data),
        ],
        if (data.activeVisitorPasses.isNotEmpty) ...[
          const SizedBox(height: 12),
          _visitorsBlock(data),
        ],
        const SizedBox(height: 12),
        _inspectionBlock(data),
      ],
    );
  }

  Widget _balanceBlock(ReceptionDepartureReadiness data) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _red.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _red.withValues(alpha: 0.25), width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.receipt_long_rounded, size: 16, color: _red),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Outstanding balance',
                      style: AppTypography.style(
                        color: _red,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${data.dueLabel} must be settled before this guest can check out.',
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.6),
                        fontSize: 12.5,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            'GUEST ALREADY PAID — HOW?',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 10.5,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.6,
            ),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final method in ReceptionDeskPaymentMethod.values)
                _methodChip(method),
            ],
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            height: 36,
            child: Material(
              color: _paymentMethod != null
                  ? AppColors.gold
                  : Colors.white.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(10),
              child: InkWell(
                onTap: (_paymentMethod != null && !_settlingBill)
                    ? _settleBill
                    : null,
                borderRadius: BorderRadius.circular(10),
                child: Center(
                  child: _settlingBill
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.black,
                          ),
                        )
                      : Text(
                          'Mark as Paid',
                          style: AppTypography.style(
                            color: _paymentMethod != null
                                ? Colors.black
                                : Colors.white.withValues(alpha: 0.4),
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _methodChip(ReceptionDeskPaymentMethod method) {
    final selected = _paymentMethod == method;
    return Material(
      color: selected
          ? AppColors.gold.withValues(alpha: 0.16)
          : Colors.white.withValues(alpha: 0.05),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: _settlingBill
            ? null
            : () => setState(() => _paymentMethod = method),
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.5)
                  : Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          child: Text(
            method.label,
            style: AppTypography.style(
              color: selected
                  ? AppColors.gold
                  : Colors.white.withValues(alpha: 0.65),
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }

  Widget _openItemsBlock(ReceptionDepartureReadiness data) {
    final items = [...data.openRequests, ...data.openWorkOrders];
    return _block(
      icon: Icons.build_rounded,
      color: AppColors.gold,
      title: 'Still open (${items.length})',
      body: items.map((i) => i.title).join(', '),
    );
  }

  Widget _visitorsBlock(ReceptionDepartureReadiness data) {
    return _block(
      icon: Icons.badge_rounded,
      color: kReceptionBlue,
      title: 'Active visitor passes (${data.activeVisitorPasses.length})',
      body:
          '${data.activeVisitorPasses.map((v) => v.visitorName).join(', ')} — closed automatically on checkout.',
    );
  }

  /// The room-inspection step — locked until the balance clears, then walks
  /// reception through asking housekeeping to inspect and waiting for them
  /// to clear it. This is the real gate on Check Out, not a self-attested
  /// checkbox: housekeeping has to actually mark the request complete.
  Widget _inspectionBlock(ReceptionDepartureReadiness data) {
    if (data.due > 0) {
      return _block(
        icon: Icons.lock_outline_rounded,
        color: Colors.white.withValues(alpha: 0.4),
        title: 'Room inspection',
        body: 'Settle the balance above first.',
        muted: true,
      );
    }

    return switch (data.inspectionStatus) {
      ReceptionInspectionStatus.notRequested => Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.gold.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: AppColors.gold.withValues(alpha: 0.25),
            width: 0.8,
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(
                  Icons.meeting_room_outlined,
                  size: 16,
                  color: AppColors.gold,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Room inspection needed',
                        style: AppTypography.style(
                          color: AppColors.gold,
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        'Ask housekeeping to inspect the room before this guest leaves.',
                        style: AppTypography.style(
                          color: Colors.white.withValues(alpha: 0.6),
                          fontSize: 12.5,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              height: 38,
              child: Material(
                color: AppColors.gold,
                borderRadius: BorderRadius.circular(10),
                child: InkWell(
                  onTap: _requestingInspection ? null : _requestInspection,
                  borderRadius: BorderRadius.circular(10),
                  child: Center(
                    child: _requestingInspection
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.black,
                            ),
                          )
                        : Text(
                            'Request Inspection',
                            style: AppTypography.style(
                              color: Colors.black,
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
      ReceptionInspectionStatus.pending => Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: kReceptionBlue.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: kReceptionBlue.withValues(alpha: 0.25),
            width: 0.8,
          ),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(
              width: 16,
              height: 16,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: kReceptionBlue,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Waiting for housekeeping',
                    style: AppTypography.style(
                      color: kReceptionBlue,
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    'Housekeeping has been notified — this unlocks the moment they clear the room.',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.6),
                      fontSize: 12.5,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      ReceptionInspectionStatus.completed => _block(
        icon: Icons.check_circle_rounded,
        color: kReceptionGreen,
        title: 'Room inspected',
        body: 'Housekeeping has cleared the room — ready to check out.',
      ),
    };
  }

  Widget _block({
    required IconData icon,
    required Color color,
    required String title,
    required String body,
    bool muted = false,
  }) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: muted
            ? Colors.white.withValues(alpha: 0.03)
            : color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: muted
              ? Colors.white.withValues(alpha: 0.08)
              : color.withValues(alpha: 0.25),
          width: 0.8,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 16, color: color),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: AppTypography.style(
                    color: color,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  body,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.6),
                    fontSize: 12.5,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _footer() {
    final canSubmit = (_data?.canCheckOut ?? false) && !_busy;
    return Row(
      children: [
        TextButton(
          onPressed: _busy ? null : () => Navigator.of(context).pop(false),
          child: Text(
            'Cancel',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.6),
              fontSize: 14,
            ),
          ),
        ),
        const Spacer(),
        Material(
          color: canSubmit
              ? AppColors.gold
              : Colors.white.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(10),
          child: InkWell(
            onTap: canSubmit ? _submit : null,
            borderRadius: BorderRadius.circular(10),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 12),
              alignment: Alignment.center,
              child: _busy
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.black,
                      ),
                    )
                  : Text(
                      'Check Out',
                      style: AppTypography.style(
                        color: canSubmit
                            ? Colors.black
                            : Colors.white.withValues(alpha: 0.4),
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
            ),
          ),
        ),
      ],
    );
  }
}
