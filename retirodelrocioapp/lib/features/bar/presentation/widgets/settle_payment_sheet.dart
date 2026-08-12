import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/bar/application/bar_providers.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_tab.dart';
import 'package:retirodelrocioapp/features/bar/domain/in_house_booking.dart';

/// The result of the "Settle Payment" bottom sheet: the chosen method, plus
/// the booking it was charged to when [method] is `room_charge`.
class SettlePaymentResult {
  const SettlePaymentResult(this.method, {this.bookingId});

  final String method;
  final int? bookingId;
}

/// The "Settle Payment" bottom sheet — picks how a closed tab is paid.
/// Picking "Charge to Room" leads into a second step to find the in-house
/// guest's room. Returns null if dismissed at any point.
Future<SettlePaymentResult?> showSettlePaymentSheet(
  BuildContext context, {
  required BarTab tab,
  required String token,
}) {
  return showModalBottomSheet<SettlePaymentResult>(
    context: context,
    isScrollControlled: true,
    backgroundColor: const Color(0xFF1E1E1E),
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (_) => _SettlePaymentSheet(tab: tab, token: token),
  );
}

class _SettlePaymentSheet extends ConsumerStatefulWidget {
  const _SettlePaymentSheet({required this.tab, required this.token});

  final BarTab tab;
  final String token;

  @override
  ConsumerState<_SettlePaymentSheet> createState() =>
      _SettlePaymentSheetState();
}

class _SettlePaymentSheetState extends ConsumerState<_SettlePaymentSheet> {
  static const _methods = [
    ('cash', Icons.payments_rounded, 'Cash'),
    ('card', Icons.credit_card_rounded, 'Card'),
    ('bank_transfer', Icons.account_balance_rounded, 'Bank Transfer'),
    ('room_charge', Icons.hotel_rounded, 'Charge to Room'),
  ];

  bool _pickingRoom = false;
  List<InHouseBooking> _rooms = const [];
  bool _searching = false;
  String _search = '';

  Future<void> _pickMethod(String method) async {
    if (method != 'room_charge') {
      Navigator.of(context).pop(SettlePaymentResult(method));
      return;
    }

    setState(() => _pickingRoom = true);
    await _searchRooms('');
  }

  Future<void> _searchRooms(String query) async {
    setState(() {
      _search = query;
      _searching = true;
    });

    final rooms = await ref
        .read(barRepositoryProvider)
        .inHouseBookings(widget.token, search: query.isEmpty ? null : query);

    if (!mounted) return;
    setState(() {
      _rooms = rooms;
      _searching = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: SafeArea(child: _pickingRoom ? _roomPicker() : _methodPicker()),
    );
  }

  Widget _methodPicker() {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 4),
          child: Text(
            'Settle Payment',
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
          child: Text(
            '${widget.tab.tableLabel ?? widget.tab.code} · ${widget.tab.totalLabel}',
            style: AppTypography.style(
              color: AppColors.gold,
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        for (final (value, icon, label) in _methods)
          ListTile(
            leading: Icon(icon, color: AppColors.gold),
            title: Text(
              label,
              style: AppTypography.style(color: Colors.white, fontSize: 15),
            ),
            trailing: value == 'room_charge'
                ? Icon(
                    Icons.chevron_right_rounded,
                    color: Colors.white.withValues(alpha: 0.4),
                  )
                : null,
            onTap: () => _pickMethod(value),
          ),
        const SizedBox(height: 8),
      ],
    );
  }

  Widget _roomPicker() {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 4),
          child: Row(
            children: [
              IconButton(
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(),
                onPressed: () => setState(() => _pickingRoom = false),
                icon: const Icon(
                  Icons.arrow_back_rounded,
                  color: Colors.white70,
                  size: 20,
                ),
              ),
              const SizedBox(width: 10),
              Text(
                'Charge to Room',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 12),
          child: Text(
            'Find the in-house guest to charge ${widget.tab.totalLabel} to.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.55),
              fontSize: 13,
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: TextField(
            autofocus: true,
            onChanged: _searchRooms,
            style: AppTypography.style(color: Colors.white, fontSize: 15),
            decoration: InputDecoration(
              hintText: 'Search guest name or room…',
              hintStyle: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.3),
                fontSize: 14,
              ),
              prefixIcon: Icon(
                Icons.search_rounded,
                color: Colors.white.withValues(alpha: 0.4),
                size: 20,
              ),
              filled: true,
              fillColor: Colors.white.withValues(alpha: 0.06),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide.none,
              ),
              contentPadding: const EdgeInsets.symmetric(vertical: 14),
            ),
          ),
        ),
        const SizedBox(height: 8),
        ConstrainedBox(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.of(context).size.height * 0.4,
          ),
          child: _searching
              ? const Padding(
                  padding: EdgeInsets.all(24),
                  child: Center(
                    child: CircularProgressIndicator(color: AppColors.gold),
                  ),
                )
              : _rooms.isEmpty
              ? Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text(
                    _search.isEmpty
                        ? 'No in-house guests found.'
                        : 'No match for "$_search".',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.5),
                      fontSize: 14,
                    ),
                  ),
                )
              : ListView.builder(
                  shrinkWrap: true,
                  itemCount: _rooms.length,
                  itemBuilder: (_, i) {
                    final room = _rooms[i];
                    return ListTile(
                      leading: const Icon(
                        Icons.meeting_room_outlined,
                        color: AppColors.gold,
                      ),
                      title: Text(
                        room.guestName,
                        style: AppTypography.style(
                          color: Colors.white,
                          fontSize: 15,
                        ),
                      ),
                      subtitle: Text(
                        room.roomLabel,
                        style: AppTypography.style(
                          color: Colors.white.withValues(alpha: 0.5),
                          fontSize: 12,
                        ),
                      ),
                      onTap: () => Navigator.of(context).pop(
                        SettlePaymentResult(
                          'room_charge',
                          bookingId: room.bookingId,
                        ),
                      ),
                    );
                  },
                ),
        ),
        const SizedBox(height: 12),
      ],
    );
  }
}
