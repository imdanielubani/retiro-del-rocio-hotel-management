import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/reception/application/reception_providers.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_guest.dart';
import 'package:retirodelrocioapp/features/reception/notifications/application/reception_notification_providers.dart';
import 'package:retirodelrocioapp/features/reception/notifications/presentation/screens/reception_notification_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/reception_navigation.dart';
import 'package:retirodelrocioapp/features/reception/presentation/screens/reception_guest_profile_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_checkout_dialog.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_nav_rail.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_scaffold.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_widgets.dart';

/// The Guests module — a searchable list of everyone who has stayed, each row
/// opening their profile.
class ReceptionGuestsScreen extends ConsumerStatefulWidget {
  const ReceptionGuestsScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<ReceptionGuestsScreen> createState() =>
      _ReceptionGuestsScreenState();
}

class _ReceptionGuestsScreenState extends ConsumerState<ReceptionGuestsScreen> {
  String _search = '';

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) ReceptionNavigation.afterLogout(context);
  }

  /// Check a guest out straight from the list — no need to open their profile
  /// first. Only called when [ReceptionGuestSummary.activeBookingId] is set.
  /// Opens the pre-checkout confirmation; the dialog performs the checkout
  /// itself.
  Future<void> _checkOut(ReceptionGuestSummary guest) async {
    final bookingId = guest.activeBookingId;
    if (bookingId == null) return;

    final done = await showReceptionCheckOutDialog(
      context,
      session: widget.session,
      bookingId: bookingId,
      guestName: guest.name,
    );
    if (done == true && mounted) _toast('${guest.name} checked out.');
  }

  void _toast(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: error
            ? const Color(0xFF7F1D1D)
            : const Color(0xFF14532D),
        content: Text(
          message,
          style: AppTypography.style(color: Colors.white, fontSize: 14),
        ),
      ),
    );
  }

  List<ReceptionGuestSummary> _filter(List<ReceptionGuestSummary> all) {
    final q = _search.trim().toLowerCase();
    if (q.isEmpty) return all;
    return all.where((g) {
      return g.name.toLowerCase().contains(q) ||
          (g.email ?? '').toLowerCase().contains(q) ||
          (g.phone ?? '').toLowerCase().contains(q);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    // Live booking-change socket, so a new reservation appears here at once.
    ref.watch(receptionBookingsRealtimeProvider(_token));

    final guestsAsync = ref.watch(receptionGuestsProvider(_token));
    final all = guestsAsync.value ?? const <ReceptionGuestSummary>[];
    final unreadNotifications = ref.watch(
      receptionUnreadNotificationsProvider(_token),
    );

    return ReceptionScaffold(
      session: widget.session,
      active: ReceptionNavItem.guests,
      onNav: (item) => ReceptionNavigation.select(
        context,
        widget.session,
        item,
        current: ReceptionNavItem.guests,
      ),
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: 'Guests',
      trailing: ReceptionSearchField(
        hint: 'Search name, email or phone',
        onChanged: (v) => setState(() => _search = v),
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _summaryCards(all),
          const SizedBox(height: 16),
          Expanded(
            child: guestsAsync.when(
              loading: () => all.isNotEmpty
                  ? _list(all)
                  : const Center(child: CircularProgressIndicator(color: AppColors.gold)),
              error: (_, _) => all.isNotEmpty
                  ? _list(all)
                  : Center(
                      child: TextButton(
                        onPressed: () => ref.invalidate(receptionGuestsProvider(_token)),
                        child: const Text('Could not load guests. Retry', style: TextStyle(color: AppColors.gold)),
                      ),
                    ),
              data: _list,
            ),
          ),
        ],
      ),
    );
  }

  Widget _list(List<ReceptionGuestSummary> all) {
    final guests = _filter(all);
    if (guests.isEmpty) {
      return const ReceptionSectionEmpty(
        icon: Icons.person_search_rounded,
        message: 'No guests match your search',
      );
    }
    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(receptionGuestsProvider(_token)),
      child: GridView.builder(
        padding: const EdgeInsets.only(bottom: 24),
        gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
          maxCrossAxisExtent: 560,
          mainAxisExtent: 84,
          crossAxisSpacing: 16,
          mainAxisSpacing: 14,
        ),
        itemCount: guests.length,
        itemBuilder: (_, i) {
          final guest = guests[i];
          return ReceptionGuestCard(
            guest: guest,
            onTap: () => _openProfile(guest),
            onCheckOut: guest.activeBookingId != null ? () => _checkOut(guest) : null,
          );
        },
      ),
    );
  }

  /// Total guests on the books, and how many are currently in-house — the
  /// hotel-wide totals, unaffected by the search box below them.
  Widget _summaryCards(List<ReceptionGuestSummary> all) {
    final inHouse = all.where((g) => g.inHouse).length;
    return Row(
      children: [
        Expanded(child: ReceptionStatCard(label: 'TOTAL GUESTS', value: all.length, accent: AppColors.gold)),
        const SizedBox(width: 16),
        Expanded(child: ReceptionStatCard(label: 'IN HOUSE', value: inHouse, accent: kReceptionBlue)),
      ],
    );
  }

  void _openProfile(ReceptionGuestSummary guest) {
    ReceptionNavigation.push(
      context,
      'guest-profile',
      ReceptionGuestProfileScreen(session: widget.session, guestKey: guest.key),
    );
  }

  void _openNotifications() {
    ReceptionNavigation.push(
      context,
      'notifications',
      ReceptionNotificationScreen(
        session: widget.session,
        current: ReceptionNavItem.guests,
      ),
    );
  }
}
