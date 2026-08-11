import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';

const Color _goldText = Color(0xFF0A0F1E);

/// The month calendar on the Extend Stay screen (Figma 137:1157 / 140:1803).
///
/// It anchors the current stay — the check-in filled gold, the nights in between
/// a faint gold band, the current checkout ringed with a dot — and lets the guest
/// pick a new checkout: any day strictly after the current checkout. Month
/// navigation is owned by the parent so the whole flow reads from one place.
class ExtendCalendar extends StatelessWidget {
  const ExtendCalendar({
    super.key,
    required this.month,
    required this.checkIn,
    required this.currentCheckOut,
    required this.selected,
    required this.onSelect,
    required this.onMonth,
  });

  /// Any day within the month being shown.
  final DateTime month;
  final DateTime? checkIn;
  final DateTime currentCheckOut;
  final DateTime? selected;
  final ValueChanged<DateTime> onSelect;

  /// +1 / -1 to step months.
  final ValueChanged<int> onMonth;

  bool _sameDay(DateTime? a, DateTime b) =>
      a != null && a.year == b.year && a.month == b.month && a.day == b.day;

  DateTime get _checkoutDay => DateTime(
    currentCheckOut.year,
    currentCheckOut.month,
    currentCheckOut.day,
  );

  DateTime? get _checkInDay => checkIn == null
      ? null
      : DateTime(checkIn!.year, checkIn!.month, checkIn!.day);

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.2),
          width: 0.8,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _monthHeader(),
          const SizedBox(height: 24),
          _weekdayRow(),
          const SizedBox(height: 8),
          _grid(),
          const SizedBox(height: 16),
          _legend(),
        ],
      ),
    );
  }

  Widget _monthHeader() {
    return Row(
      children: [
        _arrow(Icons.chevron_left_rounded, () => onMonth(-1)),
        Expanded(
          child: Text(
            DateFormat('MMMM yyyy').format(month),
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        _arrow(Icons.chevron_right_rounded, () => onMonth(1)),
      ],
    );
  }

  Widget _arrow(IconData icon, VoidCallback onTap) {
    return Material(
      color: Colors.transparent,
      shape: CircleBorder(
        side: BorderSide(
          color: Colors.white.withValues(alpha: 0.15),
          width: 0.8,
        ),
      ),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 32,
          height: 32,
          child: Icon(
            icon,
            size: 18,
            color: Colors.white.withValues(alpha: 0.85),
          ),
        ),
      ),
    );
  }

  Widget _weekdayRow() {
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    return Row(
      children: [
        for (final d in days)
          Expanded(
            child: Text(
              d,
              textAlign: TextAlign.center,
              style: AppTypography.style(
                color: Colors.white,
                fontSize: 16,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
      ],
    );
  }

  Widget _grid() {
    final first = DateTime(month.year, month.month, 1);
    final daysInMonth = DateTime(month.year, month.month + 1, 0).day;
    // Dart weekday: Mon=1 … Sun=7. Blanks before the 1st to align to Monday.
    final leading = first.weekday - 1;

    final cells = <Widget>[];
    for (var i = 0; i < leading; i++) {
      cells.add(const Expanded(child: SizedBox(height: 44)));
    }
    for (var day = 1; day <= daysInMonth; day++) {
      cells.add(
        Expanded(child: _dayCell(DateTime(month.year, month.month, day))),
      );
    }
    while (cells.length % 7 != 0) {
      cells.add(const Expanded(child: SizedBox(height: 44)));
    }

    final rows = <Widget>[];
    for (var i = 0; i < cells.length; i += 7) {
      rows.add(Row(children: cells.sublist(i, i + 7)));
    }
    return Column(children: rows);
  }

  Widget _dayCell(DateTime day) {
    final isCheckIn = _sameDay(_checkInDay, day);
    final isCurrentOut = _sameDay(_checkoutDay, day);
    final isSelected = _sameDay(selected, day);
    // The nights of the current stay, between check-in and checkout (exclusive).
    final inStayBand =
        _checkInDay != null &&
        day.isAfter(_checkInDay!) &&
        day.isBefore(_checkoutDay);
    final selectable = day.isAfter(_checkoutDay);

    // The chosen new checkout and the check-in both read as a solid gold pill.
    final filled = isSelected || isCheckIn;

    Color bg = Colors.transparent;
    Color textColor;
    FontWeight weight = FontWeight.w400;

    if (filled) {
      bg = AppColors.gold;
      textColor = _goldText;
      weight = FontWeight.w700;
    } else if (isCurrentOut) {
      bg = AppColors.gold.withValues(alpha: 0.2);
      textColor = AppColors.gold;
      weight = FontWeight.w700;
    } else if (inStayBand) {
      bg = AppColors.gold.withValues(alpha: 0.06);
      textColor = Colors.white.withValues(alpha: 0.2);
    } else if (selectable) {
      textColor = Colors.white;
    } else {
      textColor = Colors.white.withValues(alpha: 0.2);
    }

    final number = Container(
      height: 40,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Text(
        '${day.day}',
        style: AppTypography.style(
          color: textColor,
          fontSize: 14,
          fontWeight: weight,
        ),
      ),
    );

    // The current checkout carries a small dot beneath it.
    final cell = Stack(
      alignment: Alignment.center,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 3),
          child: number,
        ),
        if (isCurrentOut)
          Positioned(
            bottom: 0,
            child: Container(
              width: 4,
              height: 4,
              decoration: const BoxDecoration(
                color: AppColors.gold,
                shape: BoxShape.circle,
              ),
            ),
          ),
      ],
    );

    return SizedBox(
      height: 44,
      child: selectable
          ? Padding(
              padding: const EdgeInsets.symmetric(vertical: 2),
              child: Material(
                color: Colors.transparent,
                borderRadius: BorderRadius.circular(14),
                child: InkWell(
                  onTap: () => onSelect(day),
                  borderRadius: BorderRadius.circular(14),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 3),
                    child: number,
                  ),
                ),
              ),
            )
          : cell,
    );
  }

  Widget _legend() {
    return Row(
      children: [
        _legendDot(filled: true, label: 'Check-in / New Checkout'),
        const SizedBox(width: 16),
        _legendDot(filled: false, label: 'Current Checkout'),
      ],
    );
  }

  Widget _legendDot({required bool filled, required String label}) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 12,
          height: 12,
          decoration: BoxDecoration(
            color: filled
                ? AppColors.gold
                : AppColors.gold.withValues(alpha: 0.3),
            shape: BoxShape.circle,
            border: filled
                ? null
                : Border.all(color: AppColors.gold, width: 0.8),
          ),
        ),
        const SizedBox(width: 8),
        Text(
          label,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 11,
          ),
        ),
      ],
    );
  }
}
