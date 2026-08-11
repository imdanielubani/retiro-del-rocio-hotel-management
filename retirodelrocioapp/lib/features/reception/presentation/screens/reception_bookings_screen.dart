import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/reception/application/reception_providers.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_booking_row.dart';
import 'package:retirodelrocioapp/features/reception/notifications/application/reception_notification_providers.dart';
import 'package:retirodelrocioapp/features/reception/notifications/presentation/screens/reception_notification_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/reception_navigation.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_nav_rail.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_scaffold.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_widgets.dart';

/// The Bookings module — the front desk's read-only view of every room booking
/// the admin manages, narrowable by status and search.
class ReceptionBookingsScreen extends ConsumerStatefulWidget {
  const ReceptionBookingsScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<ReceptionBookingsScreen> createState() =>
      _ReceptionBookingsScreenState();
}

/// A status filter tab: its label and the booking `status` it keeps (null = all).
typedef _Filter = ({String label, String? status});

class _ReceptionBookingsScreenState
    extends ConsumerState<ReceptionBookingsScreen> {
  static const List<_Filter> _filters = [
    (label: 'All', status: null),
    (label: 'Pending', status: 'pending'),
    (label: 'Confirmed', status: 'paid'),
    (label: 'Checked In', status: 'checked_in'),
    (label: 'Checked Out', status: 'checked_out'),
    (label: 'Cancelled', status: 'cancelled'),
  ];

  String? _status;
  String _search = '';

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) ReceptionNavigation.afterLogout(context);
  }

  List<ReceptionBookingRow> _filter(List<ReceptionBookingRow> all) {
    final q = _search.trim().toLowerCase();
    return all.where((b) {
      if (_status != null && b.status != _status) return false;
      if (q.isEmpty) return true;
      return b.guestName.toLowerCase().contains(q) ||
          b.roomLabel.toLowerCase().contains(q) ||
          b.reference.toLowerCase().contains(q);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    // Live booking-change socket, so a new reservation appears here at once.
    ref.watch(receptionBookingsRealtimeProvider(_token));

    final bookingsAsync = ref.watch(receptionBookingsProvider(_token));
    final unreadNotifications = ref.watch(
      receptionUnreadNotificationsProvider(_token),
    );

    return ReceptionScaffold(
      session: widget.session,
      active: ReceptionNavItem.bookings,
      onNav: (item) => ReceptionNavigation.select(
        context,
        widget.session,
        item,
        current: ReceptionNavItem.bookings,
      ),
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: 'Bookings',
      trailing: ReceptionSearchField(
        hint: 'Search guest, room or reference',
        onChanged: (v) => setState(() => _search = v),
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _filterBar(),
          const SizedBox(height: 16),
          Expanded(
            child: bookingsAsync.when(
              loading: () => const Center(child: CircularProgressIndicator(color: AppColors.gold)),
              error: (_, _) => Center(
                child: TextButton(
                  onPressed: () => ref.invalidate(receptionBookingsProvider(_token)),
                  child: const Text('Could not load bookings. Retry',
                      style: TextStyle(color: AppColors.gold)),
                ),
              ),
              data: (all) {
                final rows = _filter(all);
                if (rows.isEmpty) {
                  return const ReceptionSectionEmpty(
                    icon: Icons.event_busy_rounded,
                    message: 'No bookings match this filter',
                  );
                }
                return RefreshIndicator(
                  color: AppColors.gold,
                  onRefresh: () async => ref.invalidate(receptionBookingsProvider(_token)),
                  child: GridView.builder(
                    padding: const EdgeInsets.only(bottom: 24),
                    gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                      maxCrossAxisExtent: 420,
                      mainAxisExtent: 150,
                      crossAxisSpacing: 16,
                      mainAxisSpacing: 16,
                    ),
                    itemCount: rows.length,
                    itemBuilder: (_, i) => ReceptionBookingRowCard(row: rows[i]),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  void _openNotifications() {
    ReceptionNavigation.push(
      context,
      'notifications',
      ReceptionNotificationScreen(
        session: widget.session,
        current: ReceptionNavItem.bookings,
      ),
    );
  }

  Widget _filterBar() {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final f in _filters) ...[
            _chip(f),
            const SizedBox(width: 10),
          ],
        ],
      ),
    );
  }

  Widget _chip(_Filter f) {
    final selected = _status == f.status;
    return Material(
      color: selected ? AppColors.gold.withValues(alpha: 0.15) : Colors.white.withValues(alpha: 0.04),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => setState(() => _status = f.status),
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.4)
                  : Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          child: Text(
            f.label,
            style: AppTypography.style(
              color: selected ? AppColors.gold : Colors.white.withValues(alpha: 0.6),
              fontSize: 13,
              fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
            ),
          ),
        ),
      ),
    );
  }
}
