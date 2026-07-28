import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/reception/application/reception_providers.dart';
import 'package:retirodelrocioapp/features/reception/data/reception_repository.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_guest.dart';
import 'package:retirodelrocioapp/features/reception/notifications/application/reception_notification_providers.dart';
import 'package:retirodelrocioapp/features/reception/notifications/presentation/screens/reception_notification_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/reception_navigation.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_nav_rail.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_scaffold.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_widgets.dart';

/// A single guest's record — contact, lifetime stats, derived preferences and
/// full stay history.
class ReceptionGuestProfileScreen extends ConsumerStatefulWidget {
  const ReceptionGuestProfileScreen({
    super.key,
    required this.session,
    required this.guestKey,
  });

  final StaffSession session;
  final String guestKey;

  @override
  ConsumerState<ReceptionGuestProfileScreen> createState() =>
      _ReceptionGuestProfileScreenState();
}

class _ReceptionGuestProfileScreenState
    extends ConsumerState<ReceptionGuestProfileScreen> {
  late Future<ReceptionGuestProfile> _future;

  /// True while a check-out request from this screen is in flight, so the
  /// button shows a spinner and can't be double-tapped.
  bool _checkingOut = false;

  String get _token => widget.session.token;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<ReceptionGuestProfile> _load() =>
      ref.read(receptionRepositoryProvider).guestProfile(_token, widget.guestKey);

  void _retry() => setState(() => _future = _load());

  /// Check the guest out from their profile — the only path that works
  /// however their stay is shaped: overdue, extended, or simply leaving
  /// early. Reloads the profile on success so the button disappears once
  /// `in_house` flips to false.
  Future<void> _checkOutGuest(int bookingId, String guestName) async {
    setState(() => _checkingOut = true);
    try {
      await ref.read(receptionActionsProvider(_token)).checkOut(bookingId);
      if (!mounted) return;
      _toast('$guestName checked out.');
      _retry();
    } on ReceptionException catch (e) {
      if (mounted) _toast(e.message, error: true);
    } catch (_) {
      if (mounted) {
        _toast('Something went wrong. Please try again.', error: true);
      }
    } finally {
      if (mounted) setState(() => _checkingOut = false);
    }
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

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) ReceptionNavigation.afterLogout(context);
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

  @override
  Widget build(BuildContext context) {
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
      onBack: () => Navigator.of(context).pop(),
      title: 'Guest Profile',
      body: FutureBuilder<ReceptionGuestProfile>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator(color: AppColors.gold));
          }
          if (snapshot.hasError || !snapshot.hasData) {
            return Center(
              child: TextButton(
                onPressed: _retry,
                child: const Text('Could not load the guest. Retry',
                    style: TextStyle(color: AppColors.gold)),
              ),
            );
          }
          return _content(snapshot.data!);
        },
      ),
    );
  }

  Widget _content(ReceptionGuestProfile p) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(
          flex: 5,
          child: ListView(
            padding: const EdgeInsets.only(right: 20, bottom: 24),
            children: [
              _headerCard(p),
              const SizedBox(height: 16),
              _statsRow(p),
              const SizedBox(height: 16),
              _preferences(p),
            ],
          ),
        ),
        Expanded(
          flex: 4,
          child: ReceptionSectionPanel(
            title: 'STAY HISTORY',
            child: p.history.isEmpty
                ? const ReceptionSectionEmpty(
                    icon: Icons.history_rounded,
                    message: 'No stays on record',
                  )
                : ListView.separated(
                    padding: EdgeInsets.zero,
                    itemCount: p.history.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 12),
                    itemBuilder: (_, i) => ReceptionBookingRowCard(row: p.history[i]),
                  ),
          ),
        ),
      ],
    );
  }

  Widget _headerCard(ReceptionGuestProfile p) {
    final contact = [
      if ((p.email ?? '').isNotEmpty) p.email!,
      if ((p.phone ?? '').isNotEmpty) p.phone!,
    ];

    return Container(
      padding: const EdgeInsets.all(19),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.white.withValues(alpha: 0.08), width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ReceptionAvatar(initials: p.initials, size: 60),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            p.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppTypography.style(
                              color: Colors.white,
                              fontSize: 20,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        if (p.inHouse) ...[
                          const SizedBox(width: 10),
                          const ReceptionStatusPill(status: 'checked_in', label: 'In-House'),
                        ],
                      ],
                    ),
                    const SizedBox(height: 8),
                    for (final c in contact) ...[
                      Text(
                        c,
                        style: AppTypography.style(
                          color: Colors.white.withValues(alpha: 0.5),
                          fontSize: 13,
                        ),
                      ),
                      const SizedBox(height: 2),
                    ],
                  ],
                ),
              ),
            ],
          ),
          // Lets the desk check this guest out from their profile — the only
          // path that works whatever their stay looks like (overdue, extended,
          // or an early departure), not just when they're due out today.
          if (p.inHouse && p.activeBookingId != null) ...[
            const SizedBox(height: 16),
            _checkOutButton(p.activeBookingId!, p.name),
          ],
        ],
      ),
    );
  }

  Widget _checkOutButton(int bookingId, String guestName) {
    return SizedBox(
      width: double.infinity,
      height: 44,
      child: Material(
        color: Colors.white.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          onTap: _checkingOut ? null : () => _checkOutGuest(bookingId, guestName),
          borderRadius: BorderRadius.circular(10),
          child: Center(
            child: _checkingOut
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                  )
                : Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.logout_rounded, size: 16, color: Colors.white),
                      const SizedBox(width: 8),
                      Text(
                        'Check Out Guest',
                        style: AppTypography.style(
                          color: Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
          ),
        ),
      ),
    );
  }

  Widget _statsRow(ReceptionGuestProfile p) {
    return Row(
      children: [
        Expanded(child: _statTile('${p.stats.totalStays}', 'Stays', AppColors.gold)),
        const SizedBox(width: 12),
        Expanded(child: _statTile('${p.stats.totalNights}', 'Nights', kReceptionBlue)),
        const SizedBox(width: 12),
        Expanded(child: _statTile(p.stats.totalSpendLabel, 'Spend', kReceptionGreen)),
        const SizedBox(width: 12),
        Expanded(child: _statTile(p.stats.firstSeenLabel ?? '—', 'Guest since', kReceptionSlate)),
      ],
    );
  }

  Widget _statTile(String value, String label, Color accent) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: accent.withValues(alpha: 0.22), width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              value,
              style: AppTypography.style(
                color: accent,
                fontSize: 20,
                fontWeight: FontWeight.w800,
                height: 1,
              ),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            label,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.5),
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }

  Widget _preferences(ReceptionGuestProfile p) {
    final prefs = p.preferences;
    final rows = <(IconData, String, String)>[
      if ((prefs.favouriteRoom ?? '').isNotEmpty)
        (Icons.hotel_rounded, 'Favourite room', prefs.favouriteRoom!),
      if (prefs.usualPartySize != null)
        (
          Icons.groups_rounded,
          'Usual party size',
          '${prefs.usualPartySize} ${prefs.usualPartySize == 1 ? 'guest' : 'guests'}'
        ),
      (
        Icons.flight_takeoff_rounded,
        'Airport pickup',
        prefs.usesAirportPickup ? 'Usually arranged' : 'Not used',
      ),
    ];

    return ReceptionSectionPanel(
      title: 'PREFERENCES',
      expand: false,
      child: Column(
        children: [
          for (var i = 0; i < rows.length; i++) ...[
            if (i > 0) const SizedBox(height: 12),
            Row(
              children: [
                Icon(rows[i].$1, size: 16, color: AppColors.gold.withValues(alpha: 0.8)),
                const SizedBox(width: 12),
                Text(
                  rows[i].$2,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.5),
                    fontSize: 13,
                  ),
                ),
                const SizedBox(width: 12),
                // Expanded (not Spacer + Flexible) so the value sits flush right
                // and gets the whole remaining width rather than half of it.
                Expanded(
                  child: Text(
                    rows[i].$3,
                    textAlign: TextAlign.right,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.85),
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
