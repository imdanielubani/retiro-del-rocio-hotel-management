import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/bar/application/bar_providers.dart';
import 'package:retirodelrocioapp/features/bar/data/bar_repository.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_tab.dart';
import 'package:retirodelrocioapp/features/bar/domain/restaurant_reservation_lookup.dart';

/// "New Tab from Reservation" — a waiter types in the code a walk-in gives
/// them at the door, confirms it's the right guest, and pushes it straight
/// into a new, pre-filled tab. Returns the opened [BarTab], or null if
/// dismissed.
Future<BarTab?> showReservationLookupSheet(
  BuildContext context, {
  required String token,
}) {
  return showModalBottomSheet<BarTab>(
    context: context,
    isScrollControlled: true,
    backgroundColor: const Color(0xFF1E1E1E),
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (_) => _ReservationLookupSheet(token: token),
  );
}

class _ReservationLookupSheet extends ConsumerStatefulWidget {
  const _ReservationLookupSheet({required this.token});

  final String token;

  @override
  ConsumerState<_ReservationLookupSheet> createState() =>
      _ReservationLookupSheetState();
}

class _ReservationLookupSheetState
    extends ConsumerState<_ReservationLookupSheet> {
  final _codeController = TextEditingController();
  RestaurantReservationLookup? _result;
  bool _searching = false;
  bool _confirming = false;
  String? _error;

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _lookUp() async {
    final code = _codeController.text.trim();
    if (code.isEmpty) return;

    setState(() {
      _searching = true;
      _error = null;
      _result = null;
    });

    final found = await ref
        .read(barRepositoryProvider)
        .lookupReservation(widget.token, code);

    if (!mounted) return;
    setState(() {
      _searching = false;
      _result = found;
      _error = found == null
          ? 'No table or lounge reservation found with that code.'
          : null;
    });
  }

  Future<void> _confirm() async {
    final reservation = _result;
    if (reservation == null) return;

    setState(() => _confirming = true);
    try {
      final tab = await ref
          .read(barActionsProvider(widget.token))
          .confirmReservationToTab(reservation.id);
      if (mounted) Navigator.of(context).pop(tab);
    } on BarException catch (e) {
      if (!mounted) return;
      setState(() {
        _confirming = false;
        _error = e.message;
      });
    }
  }

  void _reset() {
    setState(() {
      _result = null;
      _error = null;
      _codeController.clear();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 4),
              child: Text(
                'New Tab from Reservation',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
              child: Text(
                'Every walk-in books a table or lounge ahead of arriving — '
                'enter their reservation code to seat them.',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.55),
                  fontSize: 13,
                  height: 1.4,
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: _result == null
                  ? _searchRow()
                  : _reservationSummary(_result!),
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
                child: Text(
                  _error!,
                  style: AppTypography.style(
                    color: const Color(0xFFEF4444),
                    fontSize: 13,
                  ),
                ),
              ),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget _searchRow() {
    return Row(
      children: [
        Expanded(
          child: TextField(
            controller: _codeController,
            autofocus: true,
            textCapitalization: TextCapitalization.characters,
            onSubmitted: (_) => _lookUp(),
            style: AppTypography.style(color: Colors.white, fontSize: 15),
            decoration: InputDecoration(
              hintText: 'RES-482913-RDR',
              hintStyle: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.3),
                fontSize: 14,
              ),
              filled: true,
              fillColor: Colors.white.withValues(alpha: 0.06),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide.none,
              ),
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 14,
                vertical: 14,
              ),
            ),
          ),
        ),
        const SizedBox(width: 10),
        Material(
          color: AppColors.gold,
          borderRadius: BorderRadius.circular(12),
          child: InkWell(
            borderRadius: BorderRadius.circular(12),
            onTap: _searching ? null : _lookUp,
            child: Container(
              width: 52,
              height: 52,
              alignment: Alignment.center,
              child: _searching
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Color(0xFF0A0F1E),
                      ),
                    )
                  : const Icon(Icons.search_rounded, color: Color(0xFF0A0F1E)),
            ),
          ),
        ),
      ],
    );
  }

  Widget _reservationSummary(RestaurantReservationLookup reservation) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.05),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      reservation.customerName,
                      style: AppTypography.style(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color:
                          (reservation.canOpenTab
                                  ? AppColors.gold
                                  : const Color(0xFFEF4444))
                              .withValues(alpha: 0.16),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      reservation.statusLabel,
                      style: AppTypography.style(
                        color: reservation.canOpenTab
                            ? AppColors.gold
                            : const Color(0xFFEF4444),
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                reservation.code,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 12,
                ),
              ),
              const SizedBox(height: 12),
              _row(
                Icons.event_seat_rounded,
                [
                  reservation.areaLabel,
                  if (reservation.tableLabel != null) reservation.tableLabel!,
                  if (reservation.floorLabel != null) reservation.floorLabel!,
                ].join(' · '),
              ),
              const SizedBox(height: 8),
              _row(
                Icons.groups_rounded,
                '${reservation.guestsLabel} · ${reservation.reservedDateLabel ?? ''} ${reservation.reservedTimeLabel}',
              ),
              if (reservation.customerPhone != null) ...[
                const SizedBox(height: 8),
                _row(Icons.call_rounded, reservation.customerPhone!),
              ],
            ],
          ),
        ),
        const SizedBox(height: 14),
        if (reservation.canOpenTab)
          SizedBox(
            width: double.infinity,
            height: 50,
            child: Material(
              color: AppColors.gold,
              borderRadius: BorderRadius.circular(12),
              child: InkWell(
                borderRadius: BorderRadius.circular(12),
                onTap: _confirming ? null : _confirm,
                child: Center(
                  child: _confirming
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Color(0xFF0A0F1E),
                          ),
                        )
                      : Text(
                          'Confirm & Open Tab',
                          style: AppTypography.style(
                            color: const Color(0xFF0A0F1E),
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                ),
              ),
            ),
          )
        else
          TextButton(
            onPressed: _reset,
            child: Text(
              'Look Up a Different Code',
              style: AppTypography.style(color: AppColors.gold, fontSize: 14),
            ),
          ),
      ],
    );
  }

  Widget _row(IconData icon, String text) {
    return Row(
      children: [
        Icon(icon, size: 15, color: Colors.white.withValues(alpha: 0.4)),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            text,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.75),
              fontSize: 13,
            ),
          ),
        ),
      ],
    );
  }
}
