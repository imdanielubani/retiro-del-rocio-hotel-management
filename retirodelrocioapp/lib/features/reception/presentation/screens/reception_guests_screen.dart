import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/reception/application/reception_providers.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_guest.dart';
import 'package:retirodelrocioapp/features/reception/presentation/reception_navigation.dart';
import 'package:retirodelrocioapp/features/reception/presentation/screens/reception_guest_profile_screen.dart';
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
      title: 'Guests',
      trailing: ReceptionSearchField(
        hint: 'Search name, email or phone',
        onChanged: (v) => setState(() => _search = v),
      ),
      body: guestsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: AppColors.gold)),
        error: (_, _) => Center(
          child: TextButton(
            onPressed: () => ref.invalidate(receptionGuestsProvider(_token)),
            child: const Text('Could not load guests. Retry', style: TextStyle(color: AppColors.gold)),
          ),
        ),
        data: (all) {
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
              itemBuilder: (_, i) => ReceptionGuestCard(
                guest: guests[i],
                onTap: () => _openProfile(guests[i]),
              ),
            ),
          );
        },
      ),
    );
  }

  void _openProfile(ReceptionGuestSummary guest) {
    ReceptionNavigation.push(
      context,
      'guest-profile',
      ReceptionGuestProfileScreen(session: widget.session, guestKey: guest.key),
    );
  }
}
